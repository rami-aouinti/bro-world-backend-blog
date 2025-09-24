<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Application\Service;

use App\Blog\Application\Service\PostService;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Post;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Ramsey\Uuid\Uuid;

/**
 * @package App\Tests\Application\Blog\Application\Service
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class PostServiceTest extends KernelTestCase
{
    #[TestDox('uploadFiles returns an error response when no files are provided.')]
    public function testUploadFilesReturnsErrorWhenNoFilesAreProvided(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $service = $container->get(PostService::class);

        $request = new Request();
        $post = new Post();

        $response = $service->uploadFiles($request, $post);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());

        $data = json_decode((string)$response->getContent(), true);
        self::assertSame([
            'error' => 'No files uploaded.',
        ], $data);
    }

    #[TestDox('Persisting a post with a title generates a slug from that title.')]
    public function testSlugIsGeneratedWhenPersistingPost(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $service = $container->get(PostService::class);

        /** @var ManagerRegistry $registry */
        $registry = $container->get(ManagerRegistry::class);
        $entityManager = $registry->getManager();

        $authorId = Uuid::uuid4();

        $blog = (new Blog())
            ->setTitle('Test Blog')
            ->setAuthor($authorId)
            ->setSlug('test-blog');

        $user = new SymfonyUser($authorId->toString(), 'Test User', null, ['ROLE_USER']);
        $request = new Request([], [
            'title' => 'My Post Title',
        ]);

        $post = $service->generatePostAttributes($blog, $user, $request);
        $post->setBlog($blog);

        self::assertSame('', $post->getSlug());

        $entityManager->persist($blog);
        $entityManager->persist($post);
        $entityManager->flush();

        self::assertSame('my-post-title', $post->getSlug());

        $entityManager->remove($post);
        $entityManager->remove($blog);
        $entityManager->flush();
    }
}
