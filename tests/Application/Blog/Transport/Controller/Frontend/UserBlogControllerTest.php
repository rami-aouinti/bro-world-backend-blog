<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Frontend;

use Bro\WorldCoreBundle\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserBlogControllerTest extends WebTestCase
{
    private const string URL = '/v1/profile/blog';

    /**
     * @throws Throwable
     */
    #[TestDox('GET /v1/profile/blog requires a fully authenticated user')]
    public function testThatProfileBlogRequiresAuthentication(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::URL);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET /v1/profile/blog returns serialized blog data for logged user')]
    public function testThatAuthenticatedUserReceivesData(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $client->request('GET', self::URL);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);
    }
}
