<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Post;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\Comment\CommentResponseHelper;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function max;

/**
 * Responsible for building the payloads used to warm blog related caches.
 */
final readonly class PostCachePayloadBuilder
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
        private CommentRepositoryInterface $commentRepository,
        private UserProxy $userProxy,
        private CommentResponseHelper $commentResponseHelper,
        private PostFeedResponseBuilder $postFeedResponseBuilder,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function buildPostFeedPayload(int $page, int $limit): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        $posts = $this->postRepository->findWithRelations($limit, $offset);
        $total = $this->postRepository->countPosts();

        return $this->postFeedResponseBuilder->build($posts, $page, $limit, $total);
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function buildPostCommentsPayload(string $postId, int $page, int $limit): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        $comments = $this->postRepository->getRootComments($postId, $limit, $offset);
        $total = $this->postRepository->countComments($postId);

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

        return [
            'comments' => $data,
            'total' => $total,
            'page' => $page,
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function buildPostLikesPayload(string $postId, ?Post $post = null): array
    {
        $post ??= $this->postRepository->find($postId);

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

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function buildPostReactionsPayload(string $postId, ?Post $post = null): array
    {
        $post ??= $this->postRepository->find($postId);
        $reactions = $post?->getReactions()?->toArray() ?? [];

        $userIds = array_map(static fn ($reaction) => $reaction->getUser()->toString(), $reactions);
        $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

        $reactionsPayload = $this->commentResponseHelper->buildReactionList($reactions, $users);

        return [
            'reactions' => $reactionsPayload,
            'total' => count($reactionsPayload),
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function buildCommentLikesPayload(string $commentId, ?Comment $comment = null): array
    {
        $comment ??= $this->commentRepository->find($commentId);

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

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function buildCommentReactionsPayload(string $commentId, ?Comment $comment = null): array
    {
        $comment ??= $this->commentRepository->find($commentId);
        $reactions = $comment?->getReactions()?->toArray() ?? [];

        $userIds = array_map(static fn ($reaction) => $reaction->getUser()->toString(), $reactions);
        $users = $this->userProxy->batchSearchUsers(array_unique($userIds));

        $reactionsPayload = $this->commentResponseHelper->buildReactionList($reactions, $users);

        return [
            'reactions' => $reactionsPayload,
            'total' => count($reactionsPayload),
        ];
    }
}
