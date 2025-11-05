<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Blog;
use App\Tests\TestCase\WebTestCase;
use Bro\WorldCoreBundle\Domain\Utils\JSON;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function uniqid;

final class DeleteBlogControllerTest extends WebTestCase
{
    private const string BASE_PATH = '/v1/platform/blog';

    private EntityManagerInterface $entityManager;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('DELETE /v1/platform/blog/{id} removes a blog for its owner')]
    public function testOwnerCanDeleteBlog(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $client->request('POST', self::BASE_PATH, [
            'title' => 'Blog To Delete ' . uniqid('', true),
            'description' => 'Disposable blog',
        ]);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $createdPayload = JSON::decode($content, true);
        $slug = $createdPayload['slug'] ?? null;
        self::assertIsString($slug);

        /** @var Blog|null $blog */
        $blog = $this->entityManager->getRepository(Blog::class)->findOneBy([
            'slug' => $slug,
        ]);
        self::assertNotNull($blog);

        $client->request('DELETE', self::BASE_PATH . '/' . $blog->getId());

        $deleteResponse = $client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $deleteResponse->getStatusCode(), "Response:\n" . $deleteResponse);

        /** @var Blog|null $deletedBlog */
        $deletedBlog = $this->entityManager->getRepository(Blog::class)->find($blog->getId());
        self::assertNull($deletedBlog);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('DELETE /v1/platform/blog/{id} rejects non-owner requests')]
    public function testNonOwnerCannotDeleteBlog(): void
    {
        $ownerClient = $this->getTestClient('john-admin', 'password-admin');
        $ownerClient->request('POST', self::BASE_PATH, [
            'title' => 'Protected Blog ' . uniqid('', true),
            'description' => 'Should survive',
        ]);

        $response = $ownerClient->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $createdPayload = JSON::decode($content, true);
        $slug = $createdPayload['slug'] ?? null;
        self::assertIsString($slug);

        /** @var Blog|null $blog */
        $blog = $this->entityManager->getRepository(Blog::class)->findOneBy([
            'slug' => $slug,
        ]);
        self::assertNotNull($blog);

        $attackerClient = $this->getTestClient('john-user', 'password-user');
        $attackerClient->request('DELETE', self::BASE_PATH . '/' . $blog->getId());

        self::assertSame(Response::HTTP_FORBIDDEN, $attackerClient->getResponse()->getStatusCode());

        /** @var Blog|null $stillThere */
        $stillThere = $this->entityManager->getRepository(Blog::class)->find($blog->getId());
        self::assertNotNull($stillThere);
    }
}
