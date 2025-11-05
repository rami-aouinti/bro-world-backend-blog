<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Frontend;

use Bro\WorldCoreBundle\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BlogControllerTest extends WebTestCase
{
    private const string URL = '/public/blog';

    /**
     * @throws Throwable
     */
    #[TestDox('GET /public/blog exposes the public feed of blogs')]
    public function testThatPublicBlogFeedIsAccessible(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::URL);

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
}
