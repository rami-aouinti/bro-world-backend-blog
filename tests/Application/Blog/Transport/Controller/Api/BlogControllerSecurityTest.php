<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Api;

use App\Blog\Application\Resource\BlogResource;
use Bro\WorldCoreBundle\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @package App\Tests\Application\Blog\Transport\Controller\Api
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class BlogControllerSecurityTest extends WebTestCase
{
    private const string BASE_URL = self::API_URL_PREFIX . '/v1/blog';

    private BlogResource $resource;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resource = static::getContainer()->get(BlogResource::class);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/blog requires authentication')]
    public function testThatBaseRouteRequiresAuthentication(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::BASE_URL);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/blog returns a list for administrator users')]
    public function testThatAdminCanListBlogs(): void
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
        self::assertArrayHasKey('slug', $first);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/blog/{id} returns a single blog for administrator users')]
    public function testThatAdminCanViewSingleBlog(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $blog = $this->resource->find()[0] ?? null;
        self::assertNotNull($blog, 'Fixture for blog entity is missing');

        $client->request('GET', self::BASE_URL . '/' . $blog->getId());
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertSame($blog->getId(), $payload['id']);
        self::assertSame($blog->getTitle(), $payload['title']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('DELETE /api/v1/blog/{id} returns 403 for non-admin user')]
    public function testThatNonAdminUsersCannotDeleteBlog(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');
        $blog = $this->resource->find()[0] ?? null;
        self::assertNotNull($blog, 'Fixture for blog entity is missing');

        $client->request('DELETE', self::BASE_URL . '/' . $blog->getId());

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
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
        yield ['GET', self::BASE_URL . '/ids'];
    }
}
