<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\PostFeedCacheService;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Message\CreatePostMessenger;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\Blog\Transport\MessageHandler\CreatePostHandlerMessage;
use App\Tests\TestCase\WebTestCase;
use DateTimeImmutable;
use JsonException;
use Override;
use PHPUnit\Framework\Attributes\TestDox;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function array_reduce;
use function array_search;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * @package App\Tests\Application\Blog\Transport\Controller
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class PostsControllerCacheTest extends WebTestCase
{
    private KernelBrowser $client;

    private PostFeedCacheService $postFeedCacheService;

    private CreatePostHandlerMessage $handler;

    private BlogRepository $blogRepository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();

        $this->client = static::createClient();

        $container = static::getContainer();
        $this->overrideUserProxy($container);

        $this->postFeedCacheService = $container->get(PostFeedCacheService::class);
        $this->handler = $container->get(CreatePostHandlerMessage::class);
        $this->blogRepository = $container->get(BlogRepository::class);
    }

    #[TestDox('Creating a post invalidates the posts cache so the listing endpoint returns it immediately.')]
    public function testCreatePostInvalidatesPostsCache(): void
    {
        $limit = 10;
        $page = 1;

        $this->postFeedCacheService->invalidateTags();
        $this->postFeedCacheService->delete($page, $limit);

        $initialPayload = $this->requestPosts($limit);
        self::assertArrayHasKey('data', $initialPayload);
        self::assertNotEmpty($initialPayload['data']);
        self::assertArrayHasKey('page', $initialPayload);
        self::assertArrayHasKey('limit', $initialPayload);
        self::assertArrayHasKey('count', $initialPayload);
        self::assertSame($page, $initialPayload['page']);
        self::assertSame($limit, $initialPayload['limit']);

        $newSlug = sprintf('cache-invalidation-%s', Uuid::uuid4()->toString());
        self::assertNotContains($newSlug, array_column($initialPayload['data'], 'slug'));

        $post = $this->createPost($newSlug);

        ($this->handler)(new CreatePostMessenger($post, null));

        $updatedPayload = $this->requestPosts($limit);

        $slugs = array_column($updatedPayload['data'], 'slug');
        self::assertContains($newSlug, $slugs);

        self::assertArrayHasKey('page', $updatedPayload);
        self::assertArrayHasKey('limit', $updatedPayload);
        self::assertArrayHasKey('count', $updatedPayload);
        self::assertSame($page, $updatedPayload['page']);
        self::assertSame($limit, $updatedPayload['limit']);
        self::assertGreaterThanOrEqual($initialPayload['count'], $updatedPayload['count']);

        $newIndex = array_search($newSlug, $slugs, true);
        self::assertIsInt($newIndex);
        self::assertSame(0, $newIndex, 'The newly created post should appear first in the feed.');
    }

    private function requestPosts(int $limit): array
    {
        $this->client->request('GET', '/public/post', [
            'limit' => $limit,
        ]);

        $response = $this->client->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertTrue($response->isSuccessful());

        try {
            /** @var array{data: array<int, array<string, mixed>>} $payload */
            $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            self::fail('Failed to decode posts response: ' . $exception->getMessage());
        }

        return $payload;
    }

    private function createPost(string $slug): Post
    {
        $blog = $this->blogRepository->findOneBy([]);
        self::assertNotNull($blog);

        $post = new Post();
        $post->setTitle('Cache invalidation post');
        $post->setSlug($slug);
        $post->setSummary('Post used to verify cache invalidation.');
        $post->setContent('This post ensures the posts cache is refreshed immediately after creation.');
        $post->setUrl(sprintf('https://example.com/%s', $slug));
        $post->setAuthor($this->createAuthor());
        $post->setBlog($blog);
        $post->setPublishedAt(new DateTimeImmutable('+1 minute'));

        return $post;
    }

    private function createAuthor(): UuidInterface
    {
        return Uuid::uuid4();
    }

    private function overrideUserProxy(ContainerInterface $container): void
    {
        $userProxy = $this->createMock(UserProxy::class);

        $userProxy->method('searchUser')->willReturnCallback(
            static fn (string $id): array => [
                'id' => $id,
                'username' => 'user_' . $id,
            ],
        );

        $userProxy->method('batchSearchUsers')->willReturnCallback(
            static fn (array $ids): array => array_reduce(
                $ids,
                static function (array $carry, string $id): array {
                    $carry[$id] = [
                        'id' => $id,
                        'username' => 'user_' . $id,
                    ];

                    return $carry;
                },
                [],
            ),
        );

        $userProxy->method('getMedia')->willReturnCallback(
            static fn (string $id): array => [
                'id' => $id,
                'url' => 'https://media.example/' . $id,
            ],
        );

        $container->set(UserProxy::class, $userProxy);
        $container->set('App\\Blog\\Application\\ApiProxy\\UserProxy', $userProxy);
    }
}
