<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Post;

use App\Blog\Application\Service\CommentCacheService;
use App\Blog\Application\Service\PostCachePayloadBuilder;
use App\Blog\Application\Service\PostFeedCacheService;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @package App\Blog\Transport\Controller\Frontend
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class PostsController
{
    public function __construct(
        private CommentRepositoryInterface $commentRepository,
        private PostFeedCacheService $postFeedCacheService,
        private CommentCacheService $commentCacheService,
        private PostCachePayloadBuilder $postCachePayloadBuilder,
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
        $result = $this->postFeedCacheService->get($page, $limit, fn () => $this->postCachePayloadBuilder->buildPostFeedPayload($page, $limit));

        return new JsonResponse($result);
    }

    /**
     * Lazy-load endpoint for comments (includes `isLiked` and `reactions_count`).
     *
     * @param string  $id
     * @param Request $request
     *
     * @throws InvalidArgumentException
     * @return JsonResponse
     */
    #[Route('/public/post/{id}/comments', name: 'public_post_comments', methods: ['GET'])]
    public function comments(string $id, Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);

        $payload = $this->commentCacheService->getPostComments(
            $id,
            $page,
            $limit,
            fn () => $this->postCachePayloadBuilder->buildPostCommentsPayload($id, $page, $limit)
        );

        return new JsonResponse($payload);
    }

    /**
     * Lazy-load endpoint for a post's likes.
     *
     * @param string $id
     *
     * @throws InvalidArgumentException
     * @return JsonResponse
     */
    #[Route('/public/post/{id}/likes', name: 'public_post_likes', methods: ['GET'])]
    public function likes(string $id): JsonResponse
    {
        $payload = $this->commentCacheService->getPostLikes(
            $id,
            fn () => $this->postCachePayloadBuilder->buildPostLikesPayload($id)
        );

        return new JsonResponse($payload);
    }

    /**
     * Lazy-load endpoint for a comment's likes.
     *
     * @param string $id
     *
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @return JsonResponse
     */
    #[Route('/public/comment/{id}/likes', name: 'public_comment_likes', methods: ['GET'])]
    public function commentLikes(string $id): JsonResponse
    {
        $comment = $this->commentRepository->find($id);

        $payload = $this->commentCacheService->getCommentLikes(
            $id,
            fn () => $this->postCachePayloadBuilder->buildCommentLikesPayload($id, $comment)
        );

        return new JsonResponse($payload);
    }

    /**
     * Lazy-load endpoint for a post's reactions.
     *
     * @param string $id
     *
     * @throws InvalidArgumentException
     * @return JsonResponse
     */
    #[Route('/public/post/{id}/reactions', name: 'public_post_reactions', methods: ['GET'])]
    public function reactions(string $id): JsonResponse
    {
        $payload = $this->commentCacheService->getPostReactions(
            $id,
            fn () => $this->postCachePayloadBuilder->buildPostReactionsPayload($id)
        );

        return new JsonResponse($payload);
    }

    /**
     * Lazy-load endpoint for a comment's reactions.
     *
     * @param string $id
     *
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @return JsonResponse
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

        $payload = $this->commentCacheService->getCommentReactions(
            $id,
            fn () => $this->postCachePayloadBuilder->buildCommentReactionsPayload($id, $comment)
        );

        return new JsonResponse($payload);
    }
}
