<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @package App\Blog\Application\Service
 */
readonly class CommentCacheService
{
    private const DEFAULT_TTL = 20;

    public function __construct(private TagAwareCacheInterface $cache)
    {
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function getPostComments(string $postId, int $page, int $limit, callable $callback, ?string $context = null): mixed
    {
        return $this->getCachedPayload(
            prefix: 'post_comments',
            resourceId: $postId,
            page: $page,
            limit: $limit,
            tags: ['posts', 'comments'],
            callback: $callback,
            context: $context,
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function getPostLikes(string $postId, callable $callback): mixed
    {
        return $this->getCachedPayload(
            prefix: 'post_likes',
            resourceId: $postId,
            tags: ['posts', 'likes'],
            callback: $callback,
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function getPostReactions(string $postId, callable $callback): mixed
    {
        return $this->getCachedPayload(
            prefix: 'post_reactions',
            resourceId: $postId,
            tags: ['posts', 'reactions'],
            callback: $callback,
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function getCommentLikes(string $commentId, callable $callback, ?int $page = null, ?int $limit = null): mixed
    {
        return $this->getCachedPayload(
            prefix: 'comment_likes',
            resourceId: $commentId,
            page: $page,
            limit: $limit,
            tags: ['comments', 'likes'],
            callback: $callback,
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function getCommentReactions(string $commentId, callable $callback, ?int $page = null, ?int $limit = null): mixed
    {
        return $this->getCachedPayload(
            prefix: 'comment_reactions',
            resourceId: $commentId,
            page: $page,
            limit: $limit,
            tags: ['comments', 'reactions'],
            callback: $callback,
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
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
