<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Blog;

use App\Blog\Application\Service\Comment\CommentService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;

/**
 * @package App\Blog\Application\Service
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class ManagementService
{
    public function __construct(
        private ElasticsearchServiceInterface $elasticsearchClient,
        private CommentService $commentService
    ) {
    }

    public function indexHitsToAssoc(array $response): array
    {
        return array_column(array_map(
            fn ($hit) => $hit['_source'],
            $response['hits']['hits'] ?? []
        ), null, 'id');
    }

    /**
     * @return callable|mixed|array
     */
    public function getIndexesVars(string $index, array $Ids): mixed
    {
        return $this->elasticsearchClient->search($index, [
            'query' => [
                'match' => [
                    'id' => implode(' ', $Ids),
                ],
            ],
        ], 0, count($Ids));
    }

    public function enrichPostsWithAuthorsAndMedias(array $posts): array
    {
        $authorIds = array_unique(array_map(fn ($p) => $p->getAuthor()->toString(), $posts));
        $mediaIds = array_unique(array_merge(...array_map(
            fn ($p) => array_map(fn ($id) => $id->toString(), $p->getMediaIds()),
            $posts
        )));

        $users = $this->indexHitsToAssoc(
            $this->getIndexesVars('users', $authorIds)
        );
        $medias = $this->indexHitsToAssoc(
            $this->getIndexesVars('medias', $mediaIds)
        );

        return array_map(function ($post) use ($users, $medias) {
            $postArray = $post->toArray();
            $postArray['author'] = $users[$post->getAuthor()->toString()] ?? null;
            $postArray['medias'] = array_filter(array_map(
                fn ($id) => $medias[$id->toString()] ?? null,
                $post->getMediaIds()
            ));
            $postArray['comments'] = array_map(
                fn ($comment) => $this->commentService->commentToArray($comment, $users),
                $post->getComments()->toArray()
            );

            return $postArray;
        }, $posts);
    }
}
