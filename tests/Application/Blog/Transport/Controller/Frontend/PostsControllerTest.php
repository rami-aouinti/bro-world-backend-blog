<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Frontend;

use App\Blog\Application\Service\Interfaces\UserElasticsearchServiceInterface;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use App\Tests\TestCase\WebTestCase;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use const JSON_THROW_ON_ERROR;

final class PostsControllerTest extends WebTestCase
{
    /**
     * @throws Throwable
     */
    public function testFeedCacheIsInvalidatedAfterReaction(): void
    {
        $client = static::createClient();

        static::getContainer()->set(
            UserElasticsearchServiceInterface::class,
            new class () implements UserElasticsearchServiceInterface {
                public function searchUsers(string $query): array
                {
                    return [];
                }

                public function searchUser(string $id): ?array
                {
                    return [
                        'id' => $id,
                    ];
                }
            }
        );

        $client->request('GET', '/public/post', [
            'limit' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $firstPayload = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertNotEmpty($firstPayload['data']);

        $postData = $firstPayload['data'][0];
        $postId = $postData['id'];
        $initialReactions = $postData['reactions_count'];

        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get(ManagerRegistry::class);
        $entityManager = $registry->getManager();

        $post = $registry->getRepository(Post::class)->find($postId);
        self::assertInstanceOf(Post::class, $post);

        $reaction = new Reaction();
        $reaction->setPost($post);
        $reaction->setUser(Uuid::uuid4());
        $reaction->setType('wow');

        $entityManager->persist($reaction);
        $entityManager->flush();
        $entityManager->clear();

        $client->request('GET', '/public/post', [
            'limit' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $secondPayload = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        $freshData = null;
        foreach ($secondPayload['data'] as $item) {
            if ($item['id'] === $postId) {
                $freshData = $item;
                break;
            }
        }

        self::assertNotNull($freshData, 'The post should be present in the refreshed payload.');
        self::assertSame(
            $initialReactions + 1,
            $freshData['reactions_count'],
            'Cache should be invalidated so reaction count is refreshed.'
        );
    }
}
