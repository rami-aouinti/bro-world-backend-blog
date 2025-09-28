<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Api;

use App\Blog\Domain\Entity\Blog;
use App\Tests\TestCase\WebTestCase;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use const JSON_THROW_ON_ERROR;

/**
 * @package App\Tests\Application\Blog\Transport\Controller\Api
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
final class BlogControllerTest extends WebTestCase
{
    /**
     * @throws Throwable
     */
    public function testBlogCanBeCreatedThroughApi(): void
    {
        $client = static::createClient();
        $payload = [
            'title' => 'Test Blog ' . uniqid('', true),
            'blogSubtitle' => 'Subtitle for test blog',
            'author' => Uuid::uuid1()->toString(),
            'logo' => 'https://example.com/logo.png',
            'teams' => ['alpha', 'beta'],
            'visible' => false,
            'color' => '#123abc',
        ];

        $client->jsonRequest('POST', '/api/v1/blog', $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayHasKey('id', $data);

        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $blog = $registry->getRepository(Blog::class)->findOneBy([
            'title' => $payload['title'],
        ]);

        self::assertInstanceOf(Blog::class, $blog);
        self::assertSame($payload['blogSubtitle'], $blog->getBlogSubtitle());
        self::assertSame($payload['logo'], $blog->getLogo());
        self::assertSame($payload['teams'], $blog->getTeams());
        self::assertSame($payload['author'], $blog->getAuthor()->toString());
        self::assertSame($payload['visible'], $blog->isVisible());
        self::assertSame($payload['color'], $blog->getColor());
    }

    /**
     * @throws Throwable
     */
    public function testBlogCanBeUpdatedThroughApi(): void
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $repository = $registry->getRepository(Blog::class);
        $existing = $repository->findOneBy([
            'title' => 'public',
        ]);

        self::assertInstanceOf(Blog::class, $existing);

        $client = static::createClient();
        $payload = [
            'title' => 'Updated Blog ' . uniqid('', true),
            'blogSubtitle' => 'Updated subtitle',
            'logo' => 'https://example.com/updated-logo.png',
            'teams' => ['delta'],
            'visible' => true,
        ];

        $client->jsonRequest('PUT', '/api/v1/blog/' . $existing->getId(), $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $registry->getManager()->clear();
        $updated = $repository->find(Uuid::fromString($existing->getId()));

        self::assertInstanceOf(Blog::class, $updated);
        self::assertSame($payload['title'], $updated->getTitle());
        self::assertSame($payload['blogSubtitle'], $updated->getBlogSubtitle());
        self::assertSame($payload['logo'], $updated->getLogo());
        self::assertSame($payload['teams'], $updated->getTeams());
        self::assertSame($payload['visible'], $updated->isVisible());
    }

    /**
     * @throws Throwable
     */
    public function testBlogCanBeDeletedThroughApi(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $payload = [
            'title' => 'Delete Test Blog ' . uniqid('', true),
            'blogSubtitle' => 'Subtitle for delete test blog',
            'author' => Uuid::uuid1()->toString(),
            'logo' => 'https://example.com/delete-logo.png',
            'teams' => ['omega'],
            'visible' => true,
            'color' => '#abcdef',
        ];

        $client->jsonRequest('POST', '/api/v1/blog', $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayHasKey('id', $data);

        $blogId = $data['id'];

        $client->request('DELETE', '/api/v1/blog/' . $blogId);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $repository = $registry->getRepository(Blog::class);
        $registry->getManager()->clear();

        $deleted = $repository->find(Uuid::fromString($blogId));

        self::assertNull($deleted);
    }

    /**
     * @throws Throwable
     */
    public function testDeletingNonExistingBlogReturnsNotFound(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $nonExistingId = Uuid::uuid1()->toString();

        $client->request('DELETE', '/api/v1/blog/' . $nonExistingId);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
