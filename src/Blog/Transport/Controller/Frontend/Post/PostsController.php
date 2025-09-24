<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Post;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\CommentResponseHelper;
use App\Blog\Application\Service\PostFeedResponseBuilder;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @package App\Blog\Transport\Controller\Frontend
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class PostsController
{
    public function __construct(
        private TagAwareCacheInterface $cache,
        private PostRepositoryInterface $postRepository,
        private CommentRepositoryInterface $commentRepository,
        private UserProxy $userProxy,
        private CommentResponseHelper $commentResponseHelper,
        private PostFeedResponseBuilder $postFeedResponseBuilder,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/public/post', name: 'public_post_index', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);
        $offset = ($page - 1) * $limit;
        $cacheKey = "posts_page_{$page}_limit_{$limit}";

        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit, $offset, $page) {
            $item->tag(['posts']);
            $item->expiresAfter(20);

            $posts = $this->postRepository->findWithRelations($limit, $offset);
            $total = $this->postRepository->countPosts();

            return $this->postFeedResponseBuilder->build($posts, $page, $limit, $total);
        });

        return new JsonResponse($result);
    }

    /**
     * Lazy-load endpoint for comments (includes `isLiked` and `reactions_count`).
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    #[Route('/public/post/{id}/comments', name: 'public_post_comments', methods: ['GET'])]
    public function comments(string $id, Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);
        $offset = ($page - 1) * $limit;

        $comments = $this->postRepository->getRootComments($id, $limit, $offset);
        $total = $this->postRepository->countComments($id);

        $userIds = [];
        foreach ($comments as $comment) {
            $userIds[] = $comment->getAuthor()->toString();
            foreach ($comment->getLikes() as $like) {
                $userIds[] = $like->getUser()->toString();
            }
            foreach ($comment->getReactions() as $reaction) {
                $userIds[] = $reaction->getUser()->toString();
            }
        }

        $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

        $data = array_map(
            fn (Comment $comment) => $this->commentResponseHelper->buildCommentThread(
                $comment,
                $users,
                includeLikesCount: true,
            ),
            $comments,
        );

        return new JsonResponse([
            'comments' => $data,
            'total' => $total,
            'page' => $page,
        ]);
    }

    /**
     * Lazy-load endpoint for a post's likes.
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransactionRequiredException
     * @throws TransportExceptionInterface
     */
    #[Route('/public/post/{id}/likes', name: 'public_post_likes', methods: ['GET'])]
    public function likes(string $id): JsonResponse
    {
        $post = $this->postRepository->find($id);

        $likes = $post?->getLikes()?->toArray() ?? [];
        $reactions = $post?->getReactions()?->toArray() ?? [];

        $userIds = array_merge(
            array_map(static fn ($like) => $like->getUser()->toString(), $likes),
            array_map(static fn ($reaction) => $reaction->getUser()->toString(), $reactions),
        );

        $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

        $likesPayload = $this->commentResponseHelper->buildLikeList($likes, $users);
        $reactionsPayload = $this->commentResponseHelper->buildReactionList($reactions, $users);

        return new JsonResponse([
            'likes' => $likesPayload,
            'reactions' => $reactionsPayload,
            'total_likes' => count($likesPayload),
            'total_reactions' => count($reactionsPayload),
        ]);
    }

    /**
     * Lazy-load endpoint for a comment's likes.
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransactionRequiredException
     * @throws TransportExceptionInterface
     */
    #[Route('/public/comment/{id}/likes', name: 'public_comment_likes', methods: ['GET'])]
    public function commentLikes(string $id): JsonResponse
    {
        $comment = $this->commentRepository->find($id);

        $likes = $comment?->getLikes()?->toArray() ?? [];
        $reactions = $comment?->getReactions()?->toArray() ?? [];

        $userIds = array_merge(
            array_map(static fn ($like) => $like->getUser()->toString(), $likes),
            array_map(static fn ($reaction) => $reaction->getUser()->toString(), $reactions),
        );

        $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

        $likesPayload = $this->commentResponseHelper->buildLikeList($likes, $users);
        $reactionsPayload = $this->commentResponseHelper->buildReactionList($reactions, $users);

        return new JsonResponse([
            'likes' => $likesPayload,
            'reactions' => $reactionsPayload,
            'total_likes' => count($likesPayload),
            'total_reactions' => count($reactionsPayload),
        ]);
    }

    /**
     * Lazy-load endpoint for a post's reactions.
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransactionRequiredException
     * @throws TransportExceptionInterface
     */
    #[Route('/public/post/{id}/reactions', name: 'public_post_reactions', methods: ['GET'])]
    public function reactions(string $id): JsonResponse
    {
        $post = $this->postRepository->find($id);

        $reactions = $post?->getReactions()?->toArray() ?? [];
        $userIds = array_map(static fn ($reaction) => $reaction->getUser()->toString(), $reactions);
        $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

        $reactionsPayload = $this->commentResponseHelper->buildReactionList($reactions, $users);

        return new JsonResponse([
            'reactions' => $reactionsPayload,
            'total' => count($reactionsPayload),
        ]);
    }

    /**
     * Lazy-load endpoint for a comment's reactions.
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws NotSupported
     */
    #[Route('/public/comment/{id}/reactions', name: 'public_comment_reactions', methods: ['GET'])]
    public function commentReactions(string $id): JsonResponse
    {
        /** @var Comment|null $comment */
        $comment = $this->commentRepository->find($id);
        if (!$comment) {
            return new JsonResponse([
                'error' => 'Comment not found',
            ], 404);
        }

        $reactions = $comment->getReactions()->toArray();
        $userIds = array_map(static fn ($reaction) => $reaction->getUser()->toString(), $reactions);
        $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

        $reactionsPayload = $this->commentResponseHelper->buildReactionList($reactions, $users);

        return new JsonResponse([
            'reactions' => $reactionsPayload,
            'total' => count($reactionsPayload),
        ]);
    }
}
