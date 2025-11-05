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

final class EditBlogControllerTest extends WebTestCase
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
    #[TestDox('PATCH /v1/platform/blog/{id} allows blog owners to update their blog')]
    public function testOwnerCanUpdateBlog(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');

        $client->request('POST', self::BASE_PATH, [
            'title' => 'Owner Blog ' . uniqid('', true),
            'description' => 'Initial description',
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
        self::assertNotNull($blog, 'Blog was not created successfully.');

        $client->request(
            method: 'PATCH',
            uri: self::BASE_PATH . '/' . $blog->getId(),
            content: JSON::encode([
                'title' => 'Updated Title',
                'description' => 'Updated Subtitle',
            ])
        );

        $updateResponse = $client->getResponse();
        $updateContent = $updateResponse->getContent();
        self::assertNotFalse($updateContent);
        self::assertSame(Response::HTTP_OK, $updateResponse->getStatusCode(), "Response:\n" . $updateResponse);

        $payload = JSON::decode($updateContent, true);
        self::assertSame('Updated Title', $payload['title']);
        self::assertSame('Updated Subtitle', $payload['description']);

        /** @var Blog|null $updatedBlog */
        $updatedBlog = $this->entityManager->getRepository(Blog::class)->findOneBy([
            'slug' => $payload['slug'],
        ]);
        self::assertNotNull($updatedBlog);
        self::assertSame('Updated Title', $updatedBlog->getTitle());
        self::assertSame('Updated Subtitle', $updatedBlog->getBlogSubtitle());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('PATCH /v1/platform/blog/{id} denies access for non-owners')]
    public function testNonOwnerCannotUpdateBlog(): void
    {
        $ownerClient = $this->getTestClient('john-admin', 'password-admin');
        $ownerClient->request('POST', self::BASE_PATH, [
            'title' => 'Foreign Blog ' . uniqid('', true),
            'description' => 'Initial description',
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
        $attackerClient->request(
            method: 'PATCH',
            uri: self::BASE_PATH . '/' . $blog->getId(),
            content: JSON::encode([
                'title' => 'Hijacked Title',
            ])
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $attackerClient->getResponse()->getStatusCode());

        /** @var Blog|null $unchangedBlog */
        $unchangedBlog = $this->entityManager->getRepository(Blog::class)->find($blog->getId());
        self::assertNotNull($unchangedBlog);
        self::assertSame($blog->getTitle(), $unchangedBlog->getTitle());
    }
}
