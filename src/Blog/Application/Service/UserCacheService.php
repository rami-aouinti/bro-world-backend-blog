<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\Interfaces\UserCacheServiceInterface;
use App\Blog\Application\Service\Interfaces\UserElasticsearchServiceInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @package App\User\User\Application\Service
 * @author Rami Aouinti <rami.aouinti@tkdeutschland.de>
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
    public function search(string $query): array
    {
        $cacheKey = 'search_users_' . md5($query);

        return $this->userCache->get($cacheKey, function (ItemInterface $item) use ($query) {
            $item->expiresAfter(600);

            return $this->userElasticsearchService->searchUsers($query);
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function searchUser(string $id): array
    {
        $cacheKey = 'search_user_' . md5($id);

        return $this->userCache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter(600);

            return $this->userElasticsearchService->searchUser($id);
        });
    }
}
