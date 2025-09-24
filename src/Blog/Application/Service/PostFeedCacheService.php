<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use function sprintf;

final readonly class PostFeedCacheService
{
    private const DEFAULT_TTL = 20;
    private const CACHE_TAGS = ['posts', 'comments', 'likes', 'reactions'];

    public function __construct(private TagAwareCacheInterface $cache)
    {
    }

    /**
     * @template T
     *
     * @param callable():T $warmUp
     *
     * @return T
     */
    public function get(int $page, int $limit, callable $warmUp): mixed
    {
        return $this->cache->get($this->buildCacheKey($page, $limit), function (ItemInterface $item) use ($warmUp) {
            $item->tag(self::CACHE_TAGS);
            $item->expiresAfter(self::DEFAULT_TTL);

            return $warmUp();
        });
    }

    public function delete(int $page, int $limit): void
    {
        $this->cache->delete($this->buildCacheKey($page, $limit));
    }

    public function invalidateTags(): void
    {
        $this->cache->invalidateTags(self::CACHE_TAGS);
    }

    private function buildCacheKey(int $page, int $limit): string
    {
        return sprintf('posts_page_%d_limit_%d', $page, $limit);
    }
}
