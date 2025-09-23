<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Api;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StatisticsControllerTest extends WebTestCase
{
    private const string URL = self::API_URL_PREFIX . '/v1/statistics';

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/statistics requires authentication')]
    public function testThatStatisticsRequireAuthentication(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::URL);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /api/v1/statistics returns cached aggregates for administrator users')]
    public function testThatAdminReceivesStatistics(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $client->request('GET', self::URL);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertArrayHasKey('postsPerMonth', $payload);
        self::assertArrayHasKey('blogsPerMonth', $payload);
        self::assertArrayHasKey('likesPerMonth', $payload);
        self::assertArrayHasKey('commentsPerMonth', $payload);
    }
}
