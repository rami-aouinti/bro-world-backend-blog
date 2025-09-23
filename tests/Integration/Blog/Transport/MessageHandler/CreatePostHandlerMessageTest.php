<?php

declare(strict_types=1);

namespace App\Tests\Integration\Blog\Transport\MessageHandler;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\Interfaces\UserElasticsearchServiceInterface;
use App\Blog\Application\Service\UserCacheService;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Message\CreatePostMessenger;
use App\Blog\Transport\MessageHandler\CreatePostHandlerMessage;
use App\Tests\TestCase\WebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use function array_column;
use function json_decode;
use function json_encode;
use function sprintf;

final class CreatePostHandlerMessageTest extends WebTestCase
{
    public function testCreatingPostInvalidatesAndRefreshesCache(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $userSearchStub = new class implements UserElasticsearchServiceInterface {
            public function searchUsers(string $query): array
            {
                return [];
            }

            public function searchUser(string $id): ?array
            {
                return [
                    'id' => $id,
                    'username' => sprintf('user_%s', $id),
                    'email' => sprintf('%s@example.test', $id),
                ];
            }
        };

        $userCache = new UserCacheService(new ArrayAdapter(), $userSearchStub);
        $container->set(UserElasticsearchServiceInterface::class, $userSearchStub);
        $container->set(UserCacheService::class, $userCache);
        $container->set(UserProxy::class, new UserProxy(new MockHttpClient(new MockResponse(json_encode([]))), $userCache));

        /** @var TagAwareCacheInterface&CacheItemPoolInterface $cache */
        $cache = $container->get('cache.app.taggable');
        $cache->clear();

        $cacheKey = 'posts_page_1_limit_5';
        $client->request('GET', '/public/post', ['page' => 1, 'limit' => 5]);
        $this->assertResponseIsSuccessful();

        $initialResponse = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $initialTitles = array_column($initialResponse['data'], 'title');

        $this->assertTrue($cache->hasItem($cacheKey));

        $newTitle = sprintf('Post cache test %s', Uuid::uuid4()->toString());
        $this->assertNotContains($newTitle, $initialTitles);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $blog = $entityManager->getRepository(Blog::class)->findOneBy([]);
        self::assertNotNull($blog);

        $post = (new Post())
            ->setTitle($newTitle)
            ->setSlug(sprintf('post-cache-test-%s', Uuid::uuid4()->toString()))
            ->setSummary('Cached summary')
            ->setContent('Cached content')
            ->setUrl('https://example.test/post-cache-test')
            ->setAuthor(Uuid::uuid4())
            ->setBlog($blog)
            ->setPublishedAt(new DateTimeImmutable());

        $message = new CreatePostMessenger($post, null);

        /** @var CreatePostHandlerMessage $handler */
        $handler = $container->get(CreatePostHandlerMessage::class);
        $handler($message);

        $this->assertFalse($cache->hasItem($cacheKey));

        $client->request('GET', '/public/post', ['page' => 1, 'limit' => 5]);
        $this->assertResponseIsSuccessful();

        $updatedResponse = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $updatedTitles = array_column($updatedResponse['data'], 'title');

        $this->assertContains($newTitle, $updatedTitles);

        $this->assertTrue($cache->hasItem($cacheKey));
        $cachedItem = $cache->getItem($cacheKey);
        /** @var array{data: array<int, array<string, mixed>>} $cachedData */
        $cachedData = $cachedItem->get();
        $this->assertContains($newTitle, array_column($cachedData['data'], 'title'));
    }
}
