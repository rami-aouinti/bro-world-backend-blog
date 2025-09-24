<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Comment;

use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @package App\Blog\Application\Service
 */
readonly class CommentCacheService
{
    private const int DEFAULT_TTL = 20;

    public function __construct(
        private TagAwareCacheInterface $cache
    ) {
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @throws InvalidArgumentException
     * @return T
     */
    public function getPostComments(string $postId, int $page, int $limit, callable $callback, ?string $context = null): mixed
    {
        return $this->getCachedPayload(
            prefix: 'post_comments',
            resourceId: $postId,
            callback: $callback,
            tags: ['posts', 'comments'],
            page: $page,
            limit: $limit,
            context: $context,
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @throws InvalidArgumentException
     * @return T
     */
    public function getPostLikes(string $postId, callable $callback): mixed
    {
        return $this->getCachedPayload(
            prefix: 'post_likes',
            resourceId: $postId,
            callback: $callback,
            tags: ['posts', 'likes'],
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @throws InvalidArgumentException
     * @return T
     */
    public function getPostReactions(string $postId, callable $callback): mixed
    {
        return $this->getCachedPayload(
            prefix: 'post_reactions',
            resourceId: $postId,
            callback: $callback,
            tags: ['posts', 'reactions'],
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @throws InvalidArgumentException
     * @return T
     */
    public function getCommentLikes(string $commentId, callable $callback, ?int $page = null, ?int $limit = null): mixed
    {
        return $this->getCachedPayload(
            prefix: 'comment_likes',
            resourceId: $commentId,
            callback: $callback,
            tags: ['comments', 'likes'],
            page: $page,
            limit: $limit,
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @throws InvalidArgumentException
     * @return T
     */
    public function getCommentReactions(string $commentId, callable $callback, ?int $page = null, ?int $limit = null): mixed
    {
        return $this->getCachedPayload(
            prefix: 'comment_reactions',
            resourceId: $commentId,
            callback: $callback,
            tags: ['comments', 'reactions'],
            page: $page,
            limit: $limit,
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @throws InvalidArgumentException
     * @return T
     */
    private function getCachedPayload(
        string $prefix,
        string $resourceId,
        callable $callback,
        array $tags,
        ?int $page = null,
        ?int $limit = null,
        ?string $context = null,
    ): mixed {
        $cacheKey = $this->buildCacheKey($prefix, $resourceId, $page, $limit, $context);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($tags, $callback) {
            $item->expiresAfter(self::DEFAULT_TTL);
            $item->tag($tags);

            return $callback();
        });
    }

    private function buildCacheKey(
        string $prefix,
        string $resourceId,
        ?int $page = null,
        ?int $limit = null,
        ?string $context = null,
    ): string {
        $parts = [$prefix, $resourceId];

        if ($context !== null) {
            $parts[] = $context;
        }

        if ($page !== null) {
            $parts[] = 'page_' . $page;
        }

        if ($limit !== null) {
            $parts[] = 'limit_' . $limit;
        }

        return implode('_', $parts);
    }
}
