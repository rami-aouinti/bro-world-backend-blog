<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Frontend;

use Bro\WorldCoreBundle\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GetBlogControllerTest extends WebTestCase
{
    /**
     * @throws Throwable
     */
    #[TestDox('GET /v1/platform/blog/{slug} exposes a single blog snapshot')]
    public function testThatBlogCanBeFetchedBySlug(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', '/v1/platform/blog/public');

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertArrayHasKey('id', $payload);
        self::assertArrayHasKey('title', $payload);
        self::assertArrayHasKey('slug', $payload);
        self::assertArrayHasKey('blogSubTitle', $payload);
    }
}
