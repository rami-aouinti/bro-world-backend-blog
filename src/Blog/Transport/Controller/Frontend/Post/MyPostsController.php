<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Post;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\Comment\CommentCacheService;
use App\Blog\Application\Service\Comment\CommentResponseHelper;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Media;
use App\Blog\Infrastructure\Repository\CommentRepository;
use App\Blog\Infrastructure\Repository\PostRepository;
use App\General\Infrastructure\ValueObject\SymfonyUser;
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

use function array_slice;

/**
 * @package App\Blog\Transport\Controller\Frontend
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class MyPostsController
{
    public function __construct(
        private TagAwareCacheInterface $cache,
        private PostRepository $postRepository,
        private CommentRepository $commentRepository,
        private UserProxy $userProxy,
        private CommentResponseHelper $commentResponseHelper,
        private CommentCacheService $commentCacheService,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/v1/profile/post', name: 'profile_post_index', methods: [Request::METHOD_GET])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);
        $offset = ($page - 1) * $limit;
        $cacheKey = "posts_page_{$page}_limit_{$limit}_profile_{$symfonyUser->getUserIdentifier()}";

        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit, $offset, $page, $symfonyUser) {
            $item->tag(['posts']);
            $item->expiresAfter(20);

            $posts = $this->postRepository->findWithRelations($limit, $offset, $symfonyUser->getUserIdentifier());
            $total = $this->postRepository->countPosts($symfonyUser->getUserIdentifier());

            $userIds = [];
            foreach ($posts as $post) {
                $userIds[] = $post->getAuthor()->toString();
                foreach ($post->getReactions() as $reaction) {
                    $userIds[] = $reaction->getUser()->toString();
                }
                foreach ($post->getComments() as $comment) {
                    $userIds[] = $comment->getAuthor()->toString();
                    foreach ($comment->getReactions() as $reaction) {
                        $userIds[] = $reaction->getUser()->toString();
                    }
                }
            }
            $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

            $data = [];
            foreach ($posts as $post) {
                $data[] = [
                    'id' => $post->getId(),
                    'title' => $post->getTitle(),
                    'summary' => $post->getSummary(),
                    'content' => $post->getContent(),
                    'url' => $post->getUrl(),
                    'slug' => $post->getSlug(),
                    'medias' => $post->getMediaEntities()->map(fn (Media $m) => $m->toArray())->toArray(),
                    'isReacted' => $this->commentResponseHelper->getReactionTypeForUser(
                        $post->getReactions(),
                        $symfonyUser->getUserIdentifier(),
                    ),
                    'reactions_count' => count($post->getReactions()),
                    'totalComments' => count($post->getComments()),
                    'sharedFrom' => $post->getSharedFrom() ? [
                        'id' => $post->getSharedFrom()->getId(),
                        'title' => $post->getSharedFrom()->getTitle(),
                        'summary' => $post->getSharedFrom()->getSummary(),
                        'url' => $post->getSharedFrom()->getUrl(),
                        'slug' => $post->getSharedFrom()->getSlug(),
                        'medias' => $post->getSharedFrom()->getMediaEntities()->map(fn (Media $m) => $m->toArray())->toArray(),
                        'isReacted' => $this->commentResponseHelper->getReactionTypeForUser(
                            $post->getSharedFrom()->getReactions(),
                            $symfonyUser->getUserIdentifier(),
                        ),
                        'reactions_count' => count($post->getSharedFrom()->getReactions()),
                        'totalComments' => count($post->getSharedFrom()->getComments()),
                        'user' => $users[$post->getSharedFrom()->getAuthor()->toString()] ?? null,
                        'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
                        'reactions_preview' => array_slice(array_map(static function ($r) use ($users) {
                            return [
                                'id' => $r->getId(),
                                'type' => $r->getType(),
                                'user' => $users[$r->getUser()->toString()] ?? null,
                            ];
                        }, $post->getSharedFrom()->getReactions()->toArray()), 0, 2),
                        'comments_preview' => array_slice(array_map(function ($c) use ($users, $symfonyUser) {
                            return [
                                'id' => $c->getId(),
                                'content' => $c->getContent(),
                                'user' => $users[$c->getAuthor()->toString()] ?? null,
                                'isReacted' => $this->commentResponseHelper->getReactionTypeForUser(
                                    $c->getReactions(),
                                    $symfonyUser->getUserIdentifier(),
                                ),
                                'totalComments' => count($c->getChildren()),
                                'reactions_count' => count($c->getReactions()),
                                'publishedAt' => $c->getPublishedAt()?->format(DATE_ATOM),
                                'reactions_preview' => array_slice(array_map(static function ($r) use ($users) {
                                    return [
                                        'id' => $r->getId(),
                                        'type' => $r->getType(),
                                        'user' => $users[$r->getUser()->toString()] ?? null,
                                    ];
                                }, $c->getReactions()->toArray()), 0, 2),
                            ];
                        }, $post->getSharedFrom()->getComments()->toArray()), 0, 2),
                    ] : null,
                    'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
                    'user' => $users[$post->getAuthor()->toString()] ?? null,
                    'reactions_preview' => array_slice(array_map(static function ($r) use ($users) {
                        return [
                            'id' => $r->getId(),
                            'type' => $r->getType(),
                            'user' => $users[$r->getUser()->toString()] ?? null,
                        ];
                    }, $post->getReactions()->toArray()), 0, 2),
                    'comments_preview' => array_slice(array_map(function ($c) use ($users, $symfonyUser) {
                        return [
                            'id' => $c->getId(),
                            'content' => $c->getContent(),
                            'user' => $users[$c->getAuthor()->toString()] ?? null,
                            'isReacted' => $this->commentResponseHelper->getReactionTypeForUser(
                                $c->getReactions(),
                                $symfonyUser->getUserIdentifier(),
                            ),
                            'totalComments' => count($c->getChildren()),
                            'reactions_count' => count($c->getReactions()),
                            'publishedAt' => $c->getPublishedAt()?->format(DATE_ATOM),
                            'reactions_preview' => array_slice(array_map(static function ($r) use ($users) {
                                return [
                                    'id' => $r->getId(),
                                    'type' => $r->getType(),
                                    'user' => $users[$r->getUser()->toString()] ?? null,
                                ];
                            }, $c->getReactions()->toArray()), 0, 2),
                        ];
                    }, $post->getComments()->toArray()), 0, 2),
                ];
            }

            return [
                'data' => $data,
                'page' => $page,
                'limit' => $limit,
                'count' => $total,
            ];
        });

        return new JsonResponse($result);
    }

    /**
     * @return string|null
     */
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
    #[Route('/profile/post/{id}/comments', name: 'profile_post_comments', methods: ['GET'])]
    public function comments(string $id, SymfonyUser $symfonyUser, Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);
        $offset = ($page - 1) * $limit;

        $payload = $this->commentCacheService->getPostComments(
            $id,
            $page,
            $limit,
            function () use ($id, $limit, $offset, $page, $symfonyUser) {
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
                        $symfonyUser->getUserIdentifier(),
                    ),
                    $comments,
                );

                return [
                    'comments' => $data,
                    'total' => $total,
                    'page' => $page,
                ];
            },
            $symfonyUser->getUserIdentifier()
        );

        return new JsonResponse($payload);
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
    #[Route('/profile/post/{id}/likes', name: 'profile_post_likes', methods: ['GET'])]
    public function likes(string $id): JsonResponse
    {
        $payload = $this->commentCacheService->getPostLikes(
            $id,
            function () use ($id) {
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

                return [
                    'likes' => $likesPayload,
                    'reactions' => $reactionsPayload,
                    'total_likes' => count($likesPayload),
                    'total_reactions' => count($reactionsPayload),
                ];
            }
        );

        return new JsonResponse($payload);
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
    #[Route('/profile/comment/{id}/likes', name: 'profile_comment_likes', methods: ['GET'])]
    public function commentLikes(string $id): JsonResponse
    {
        $payload = $this->commentCacheService->getCommentLikes(
            $id,
            function () use ($id) {
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

                return [
                    'likes' => $likesPayload,
                    'reactions' => $reactionsPayload,
                    'total_likes' => count($likesPayload),
                    'total_reactions' => count($reactionsPayload),
                ];
            }
        );

        return new JsonResponse($payload);
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
    #[Route('/profile/post/{id}/reactions', name: 'profile_post_reactions', methods: ['GET'])]
    public function reactions(string $id): JsonResponse
    {
        $payload = $this->commentCacheService->getPostReactions(
            $id,
            function () use ($id) {
                $post = $this->postRepository->find($id);

                $reactions = $post?->getReactions()?->toArray() ?? [];
                $userIds = array_map(static fn ($reaction) => $reaction->getUser()->toString(), $reactions);
                $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

                $reactionsPayload = $this->commentResponseHelper->buildReactionList($reactions, $users);

                return [
                    'reactions' => $reactionsPayload,
                    'total' => count($reactionsPayload),
                ];
            }
        );

        return new JsonResponse($payload);
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
    #[Route('/profile/comment/{id}/reactions', name: 'profile_comment_reactions', methods: ['GET'])]
    public function commentReactions(string $id): JsonResponse
    {
        /** @var Comment|null $comment */
        $comment = $this->postRepository->getEntityManager()->getRepository(Comment::class)->find($id);
        if (!$comment) {
            return new JsonResponse([
                'error' => 'Comment not found',
            ], 404);
        }

        $payload = $this->commentCacheService->getCommentReactions(
            $id,
            function () use ($comment) {
                $reactions = $comment->getReactions()->toArray();
                $userIds = array_map(static fn ($reaction) => $reaction->getUser()->toString(), $reactions);
                $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

                $reactionsPayload = $this->commentResponseHelper->buildReactionList($reactions, $users);

                return [
                    'reactions' => $reactionsPayload,
                    'total' => count($reactionsPayload),
                ];
            }
        );

        return new JsonResponse($payload);
    }
}
