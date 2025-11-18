<?php

declare(strict_types=1);

namespace App\Blog\Application\ApiProxy;

use App\Blog\Application\Service\User\UserCacheService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function in_array;
use function sprintf;

/**
 * @package App\Blog\Application\ApiProxy
 * @author  Rami Aouinti
 */
readonly class UserProxy
{
    private const string USERS_CACHE_KEY = 'external_api_users';
    private const int USERS_CACHE_TTL = 300;
    private const int MEDIA_CACHE_TTL = 600;

    public function __construct(
        private HttpClientInterface $httpClient,
        private UserCacheService $userCacheService,
        private CacheInterface $remoteCache
    ) {
    }

    /**
     * Retrieves all users from the external API.
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getUsers(): array
    {
        return $this->remoteCache->get(self::USERS_CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::USERS_CACHE_TTL);

            $response = $this->httpClient->request('GET', 'https://bro-world.org/api/v1/user', [
                'headers' => [
                    'Authorization' => 'ApiKey MfnfDWHw3k3t7J2qFK8CZUg4jQiD4PuWWJpFAm49',
                ],
            ]);

            return $response->toArray();
        });
    }

    /**
     * Searches cached users with a keyword.
     *
     * @throws InvalidArgumentException
     */
    public function searchUsers(string $query): array
    {
        return $this->userCacheService->search($query);
    }

    /**
     * Looks up a specific user by ID.
     *
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function searchUser(string $id): ?array
    {
        $cachedUser = $this->userCacheService->searchUser($id);
        if ($cachedUser !== null) {
            return $cachedUser;
        }

        $users = $this->getUsers();
        foreach ($users as $user) {
            if (!isset($user['id'])) {
                continue;
            }

            $userId = (string)$user['id'];
            $this->userCacheService->save($userId, $user); // Adds every user to the cache.

            if ($userId === $id) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Retrieves several users by ID list and caches them with the "users" tag.
     *
     * @param string[] $ids
     * @return array<string, array> [userId => userData]
     *
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function batchSearchUsers(array $ids): array
    {
        $ids = array_unique($ids);
        $usersData = $this->userCacheService->searchMultiple($ids);

        $missing = array_diff($ids, array_keys($usersData));
        if (!empty($missing)) {
            $allUsers = $this->getUsers();
            foreach ($allUsers as $user) {
                if (in_array($user['id'], $missing, true)) {
                    $usersData[$user['id']] = $user;
                    // Stores the user with the "users" tag.
                    $this->userCacheService->save($user['id'], $user);
                }
            }
        }

        return $usersData;
    }

    /**
     * Retrieves media details by its ID.
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getMedia(string $mediaId): array
    {
        $cacheKey = sprintf('external_media_%s', md5($mediaId));

        return $this->remoteCache->get($cacheKey, function (ItemInterface $item) use ($mediaId) {
            $item->expiresAfter(self::MEDIA_CACHE_TTL);

            $response = $this->httpClient->request(
                'GET',
                "https://media.bro-world.org/v1/platform/media/{$mediaId}"
            );

            return $response->toArray();
        });
    }
}
