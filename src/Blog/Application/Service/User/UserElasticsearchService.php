<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\User;

use App\Blog\Application\Service\Interfaces\UserElasticsearchServiceInterface;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;

/**
 * @package App\User\Application\Service
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class UserElasticsearchService implements UserElasticsearchServiceInterface
{
    public function __construct(
        private ElasticsearchServiceInterface $elasticsearchService
    ) {
    }

    public function searchUsers(string $query): array
    {
        $response = $this->elasticsearchService->search(
            'users',
            [
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => ['username', 'firstName', 'lastName', 'email'],
                    ],
                ],
            ],
        );

        return array_map(static fn ($hit) => $hit['_source'], $response['hits']['hits']);
    }

    public function searchUser(string $id): array|null
    {
        $response = $this->elasticsearchService->search(
            'users',
            [
                'query' => [
                    'term' => [
                        'id.keyword' => [
                            'value' => $id,
                        ],
                    ],
                ],
            ],
        );

        $result = array_map(static fn ($hit) => $hit['_source'], $response['hits']['hits']);

        return $result[0] ?? null;
    }
}
