<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\PostFeedResponseBuilder;
use App\Blog\Application\Post\PostFeedResponseBuilder;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Infrastructure\Repository\CommentRepository;
use App\Blog\Infrastructure\Repository\PostRepository;
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
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function array_slice;

/**
 * Class LoggedPostsController
 *
 * @package App\Blog\Transport\Controller\Frontend
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class PostsController
{
    public function __construct(
        private TagAwareCacheInterface $cache,
        private PostRepository $postRepository,
        private CommentRepository $commentRepository,
        private UserProxy $userProxy,
        private PostFeedResponseBuilder $postFeedResponseBuilder
        private PostFeedResponseBuilder $postFeedResponseBuilder,
    ) {}

    /**
     *
     * @param Request     $request
     *
     * @throws InvalidArgumentException
     * @return JsonResponse
     */
    #[Route('/public/post', name: 'public_post_index', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = (int) $request->query->get('limit', 10);
        $offset = ($page - 1) * $limit;
        $cacheKey = "posts_page_{$page}_limit_{$limit}";

        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit, $offset, $page) {
            $item->tag(['posts']);
            $item->expiresAfter(20);

            $posts = $this->postRepository->findWithRelations($limit, $offset);
            $total = $this->postRepository->countPosts();

            return $this->postFeedResponseBuilder->build($posts, $page, $limit, $total);
            return $this->postFeedResponseBuilder->buildFeedResponse($posts, $page, $limit, $total);
        });

        return new JsonResponse($result);
    }

    /** ✅ Endpoint lazy load commentaires (avec `isLiked` et `reactions_count`)
     *
     * @param string      $id
     * @param Request     $request
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return JsonResponse
     */
    #[Route('/public/post/{id}/comments', name: 'public_post_comments', methods: ['GET'])]
    public function comments(string $id, Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = (int) $request->query->get('limit', 10);
        $offset = ($page - 1) * $limit;

        $comments = $this->postRepository->getRootComments($id, $limit, $offset);
        $total    = $this->postRepository->countComments($id);

        $userIds = array_merge(
            array_map(static fn($c) => $c->getAuthor()->toString(), $comments),
            ...array_map(static fn($c) => array_map(static fn($l) => $l->getUser()->toString(), $c->getLikes()->toArray()), $comments)
        );
        $users   = $this->userProxy->batchSearchUsers(array_unique($userIds));

        $data = array_map(function ($c) use ($users) {
            return [
                'id' => $c->getId(),
                'content' => $c->getContent(),
                'children' => $this->formatCommentRecursively($c, $users),
                'user' => $users[$c->getAuthor()->toString()] ?? null,
                'likes_count' => count($c->getLikes()),
                'isReacted' => null,
                'reactions_count' => count($c->getReactions()),
                'totalComments' => count($c->getChildren()),
                'reactions_preview' => array_slice(array_map(static function ($r) use ($users) {
                    return [
                        'id' => $r->getId(),
                        'type' => $r->getType(),
                        'user' => $users[$r->getUser()->toString()] ?? null,
                    ];
                }, $c->getReactions()->toArray()), 0, 2),
            ];
        }, $comments);

        return new JsonResponse(['comments' => $data, 'total' => $total, 'page' => $page]);
    }

    /**
     * @param       $comment
     * @param       $users
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    private function formatCommentRecursively($comment, $users): array
    {
        $authorId = $comment->getAuthor()->toString();

        $formatted = [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'likes' => [],
            'publishedAt' => $comment->getPublishedAt()?->format(DATE_ATOM),
            'user' => $this->userProxy->searchUser($authorId),
            'children' => [],
            'totalComments' => count($comment->getChildren()),
            'isReacted' => null,
            'reactions_count' => count($comment->getReactions()),
            'reactions_preview' => array_slice(array_map(static function ($r) use ($users) {
                return [
                    'id' => $r->getId(),
                    'type' => $r->getType(),
                    'user' => $users[$r->getUser()->toString()] ?? null,
                ];
            }, $comment->getReactions()->toArray()), 0, 2),
        ];
        foreach ($comment->getLikes() as $key => $like) {
            $formatted['likes'][$key]['id'] = $like->getId();
            $formatted['likes'][$key]['user']  = $this->userProxy->searchUser($like->getUser()->toString());
        }
        foreach ($comment->getChildren() as $child) {
            $formatted['children'][] = $this->formatCommentRecursively($child, $users);
        }

        return $formatted;
    }

    /** ✅ Endpoint lazy load likes d’un post
     *
     * @param string $id
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
     * @return JsonResponse
     */
    #[Route('/public/post/{id}/likes', name: 'public_post_likes', methods: ['GET'])]
    public function likes(string $id): JsonResponse
    {
        $post = $this->postRepository->find($id);
        $userIds = array_map(static fn($l) => $l->getUser()->toString(), $post?->getLikes()->toArray());
        $users   = $this->userProxy->batchSearchUsers($userIds);

        $likes = array_map(static fn($l) => [
            'id' => $l->getId(),
            'user' => $users[$l->getUser()->toString()] ?? null
        ], $post?->getLikes()->toArray());

        $reactions = array_map(static fn($r) => [
            'id' => $r->getId(),
            'type' => $r->getType(),
            'user' => $users[$r->getUser()->toString()] ?? null
        ], $post?->getReactions()->toArray());

        return new JsonResponse([
                'likes' => $likes,
                'reactions' => $reactions,
                'total_likes' => count($likes),
                'total_reactions' => count($reactions)
            ]
        );
    }

    /** ✅ Endpoint lazy load likes d’un post
     *
     * @param string $id
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
     * @return JsonResponse
     */
    #[Route('/public/comment/{id}/likes', name: 'public_comment_likes', methods: ['GET'])]
    public function commentLikes(string $id): JsonResponse
    {
        $comment = $this->commentRepository->find($id);
        $userIds = array_map(static fn($l) => $l->getUser()->toString(), $comment?->getLikes()->toArray());
        $users   = $this->userProxy->batchSearchUsers($userIds);

        $likes = array_map(static fn($l) => [
            'id' => $l->getId(),
            'user' => $users[$l->getUser()->toString()] ?? null
        ], $comment?->getLikes()->toArray());

        $reactions = array_map(static fn($r) => [
            'id' => $r->getId(),
            'type' => $r->getType(),
            'user' => $users[$r->getUser()->toString()] ?? null
        ], $comment?->getReactions()->toArray());

        return new JsonResponse([
                'likes' => $likes,
                'reactions' => $reactions,
                'total_likes' => count($likes),
                'total_reactions' => count($reactions)
            ]
        );
    }

    /** ✅ Endpoint lazy load reactions d’un post
     *
     * @param string $id
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
     * @return JsonResponse
     */
    #[Route('/public/post/{id}/reactions', name: 'public_post_reactions', methods: ['GET'])]
    public function reactions(string $id): JsonResponse
    {
        $post = $this->postRepository->find($id);
        $userIds = array_map(static fn($r) => $r->getUser()->toString(), $post?->getReactions()->toArray());
        $users   = $this->userProxy->batchSearchUsers($userIds);

        $reactions = array_map(static fn($r) => [
            'id' => $r->getId(),
            'type' => $r->getType(),
            'user' => $users[$r->getUser()->toString()] ?? null
        ], $post?->getReactions()->toArray());

        return new JsonResponse(['reactions' => $reactions, 'total' => count($reactions)]);
    }

    /** ✅ Nouveau : Endpoint reactions d’un commentaire
     *
     * @param string $id
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws NotSupported
     * @return JsonResponse
     */
    #[Route('/public/comment/{id}/reactions', name: 'public_comment_reactions', methods: ['GET'])]
    public function commentReactions(string $id): JsonResponse
    {
        /** @var Comment|null $comment */
        $comment = $this->postRepository->getEntityManager()->getRepository(Comment::class)->find($id);
        if (!$comment) {
            return new JsonResponse(['error' => 'Comment not found'], 404);
        }

        $userIds = array_map(static fn($r) => $r->getUser()->toString(), $comment->getReactions()->toArray());
        $users   = $this->userProxy->batchSearchUsers($userIds);

        $reactions = array_map(static fn($r) => [
            'id' => $r->getId(),
            'type' => $r->getType(),
            'user' => $users[$r->getUser()->toString()] ?? null
        ], $comment->getReactions()->toArray());

        return new JsonResponse(['reactions' => $reactions, 'total' => count($reactions)]);
    }
}
