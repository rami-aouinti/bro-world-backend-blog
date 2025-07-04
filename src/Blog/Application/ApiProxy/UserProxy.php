<?php

declare(strict_types=1);

namespace App\Blog\Application\ApiProxy;

use App\Blog\Application\Service\UserCacheService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class UserProxy
 *
 * @package App\Blog\Application\ApiProxy
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class UserProxy
{

    public function __construct(
        private HttpClientInterface $httpClient,
        private UserCacheService $userCacheService
    )
    {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getUsers(): array
    {
        $response = $this->httpClient->request('GET', "https://bro-world.org/api/v1/user", [
            'headers' => [
                'Authorization' => 'ApiKey kdSDHn7VvCLj5JmCJo2z9ThGWvviL1gwary8oWKd',
            ],
        ]);

        return $response->toArray();
    }

    /**
     * @param string $query
     *
     * @throws InvalidArgumentException
     * @return array
     */
    public function searchUsers(string $query): array
    {
        return $this->userCacheService->search($query);
    }

    /**
     * @param string $id
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array|null
     */
    public function searchUser(string $id): array|null
    {
        if ($this->userCacheService->searchUser($id) !== null) {
            return $this->userCacheService->searchUser($id);
        }
        $users = $this->getUsers();

        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user['id']] = $user;
        }

        return $usersById[$id] ?? null;
    }

    /**
     * @param string $query
     *
     * @throws InvalidArgumentException
     * @return array
     */
    public function searchMedias(string $query): array
    {
        return $this->userCacheService->search($query);
    }

    /**
     * @param $mediaId
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    public function getMedia($mediaId): array
    {
        $response = $this->httpClient->request(
            'GET',
            "https://media.bro-world.org/v1/platform/media/" . $mediaId
        );

        return $response->toArray();
    }
}
