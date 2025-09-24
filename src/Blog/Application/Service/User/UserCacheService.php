<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\User;

use App\Blog\Application\Service\Interfaces\UserCacheServiceInterface;
use App\Blog\Application\Service\Interfaces\UserElasticsearchServiceInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @package App\User\User\Application\Service
 * @author Rami Aouinti
 */
readonly class UserCacheService implements UserCacheServiceInterface
{
    public function __construct(
        private CacheInterface $userCache,
        private UserElasticsearchServiceInterface $userElasticsearchService
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function searchMultiple(array $ids): array
    {
        $results = [];
        foreach ($ids as $id) {
            $user = $this->searchUser($id);
            if ($user !== null) {
                $results[$id] = $user;
            }
        }

        return $results;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function search(string $query): array
    {
        $cacheKey = 'search_users_' . md5($query);

        return $this->userCache->get($cacheKey, function (ItemInterface $item) use ($query) {
            $item->expiresAfter(31536000);

            return $this->userElasticsearchService->searchUsers($query);
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function searchUser(string $id): array|null
    {
        $cacheKey = 'search_user_' . md5($id);

        return $this->userCache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter(31536000);

            return $this->userElasticsearchService->searchUser($id);
        });
    }

    /**
     * Manually stores a user in cache and tags it with "users".
     *
     * @throws InvalidArgumentException
     */
    public function save(string $id, array $user, int $ttl = 31536000): void
    {
        $cacheKey = 'search_user_' . md5($id);
        $this->userCache->delete($cacheKey);

        $this->userCache->get($cacheKey, function (ItemInterface $item) use ($user, $ttl) {
            $item->expiresAfter($ttl);

            // Adds the "users" tag when supported by the cache item.
            if (method_exists($item, 'tag')) {
                $item->tag(['users']);
            }

            return $user;
        });
    }
}
