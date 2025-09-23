<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Api;

use App\Blog\Application\Resource\LikeResource;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LikeControllerTest extends WebTestCase
{
    private const string BASE_URL = self::API_URL_PREFIX . '/v1/like';

    private LikeResource $resource;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resource = static::getContainer()->get(LikeResource::class);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/like requires authentication')]
    public function testThatBaseRouteRequiresAuthentication(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::BASE_URL);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/like returns existing likes for administrator users')]
    public function testThatAdminCanListLikes(): void
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
        self::assertArrayHasKey('user', $first);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/like/{id} returns a single like for administrator users')]
    public function testThatAdminCanViewSingleLike(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $like = $this->resource->find()[0] ?? null;
        self::assertNotNull($like, 'Fixture for like entity is missing');

        $client->request('GET', self::BASE_URL . '/' . $like->getId());
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertSame($like->getId(), $payload['id']);
        self::assertSame($like->getUser()->toString(), $payload['user']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/like/count returns an aggregate payload for administrator users')]
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
    #[TestDox('GET /api/v1/like/ids returns identifiers for administrator users')]
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
