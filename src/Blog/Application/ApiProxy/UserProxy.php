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

use function in_array;

/**
 * Class UserProxy
 *
 * @package App\Blog\Application\ApiProxy
 * @author  Rami Aouinti
 */
readonly class UserProxy
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private UserCacheService $userCacheService
    ) {}

    /**
     * Récupère tous les utilisateurs depuis l'API externe.
     *
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
                'Authorization' => 'ApiKey agYybuBZFsjXaCKBfjFWa2qFYMUshXZWFcz575KT',
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Recherche des utilisateurs depuis le cache avec un mot-clé.
     *
     * @throws InvalidArgumentException
     */
    public function searchUsers(string $query): array
    {
        return $this->userCacheService->search($query);
    }

    /**
     * Recherche un utilisateur spécifique par son ID.
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

            $userId = (string) $user['id'];
            $this->userCacheService->save($userId, $user); // Ajoute tous les users au cache

            if ($userId === $id) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Recherche des médias (fallback méthode, potentiellement inutile ici).
     *
     * @throws InvalidArgumentException
     */
    public function searchMedias(string $query): array
    {
        return $this->userCacheService->search($query);
    }

    /**
     * Récupère plusieurs utilisateurs à partir d'une liste d'IDs.
     * Mise en cache avec tag 'users' pour chaque nouvel utilisateur.
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
                    // ✅ stockage avec tag 'users'
                    $this->userCacheService->save($user['id'], $user);
                }
            }
        }

        return $usersData;
    }

    /**
     * Récupère les informations d’un média via son ID.
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getMedia(string $mediaId): array
    {
        $response = $this->httpClient->request(
            'GET',
            "https://media.bro-world.org/v1/platform/media/{$mediaId}"
        );

        return $response->toArray();
    }
}
