<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Api;

use App\Blog\Application\Resource\PostResource;
use App\Tests\TestCase\WebTestCase;
use Bro\WorldCoreBundle\Domain\Utils\JSON;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PostControllerTest extends WebTestCase
{
    private const string BASE_URL = self::API_URL_PREFIX . '/v1/post';

    private PostResource $resource;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resource = static::getContainer()->get(PostResource::class);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/post requires authentication')]
    public function testThatBaseRouteRequiresAuthentication(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::BASE_URL);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/post returns a feed for administrator users')]
    public function testThatAdminCanListPosts(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $client->request('GET', self::BASE_URL);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);
        self::assertNotEmpty($payload);
        $first = $payload[0];
        self::assertArrayHasKey('id', $first);
        self::assertArrayHasKey('title', $first);
        self::assertArrayHasKey('summary', $first);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/post/{id} returns a single post for administrator users')]
    public function testThatAdminCanViewSinglePost(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $post = $this->resource->find()[0] ?? null;
        self::assertNotNull($post, 'Fixture for post entity is missing');

        $client->request('GET', self::BASE_URL . '/' . $post->getId());
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertSame($post->getId(), $payload['id']);
        self::assertSame($post->getTitle(), $payload['title']);
        self::assertArrayHasKey('content', $payload);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/post/count returns an aggregate payload for administrator users')]
    public function testThatCountEndpointReturnsTotals(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $client->request('GET', self::BASE_URL . '/count');

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertArrayHasKey('count', $payload);
        self::assertGreaterThan(0, $payload['count']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/post/ids returns identifiers for administrator users')]
    public function testThatIdsEndpointReturnsIdentifiers(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $client->request('GET', self::BASE_URL . '/ids');

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);
        self::assertNotEmpty($payload);
        self::assertIsString($payload[0]);
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('dataProviderRestrictedEndpoints')]
    #[TestDox('`$method $path` returns 403 for non-admin user')]
    public function testThatNonAdminUsersAreForbidden(string $method, string $path): void
    {
        $client = $this->getTestClient('john-user', 'password-user');
        $client->request($method, $path);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * @return Generator<array{0: string, 1: string}>
     */
    public static function dataProviderRestrictedEndpoints(): Generator
    {
        yield ['GET', self::BASE_URL];
        yield ['GET', self::BASE_URL . '/count'];
        yield ['GET', self::BASE_URL . '/ids'];
    }
}
