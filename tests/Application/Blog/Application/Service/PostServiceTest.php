<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Application\Service;

use App\Blog\Application\Service\PostService;
use App\Blog\Domain\Entity\Post;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame([
            'error' => 'No files uploaded.',
        ], $data);
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Tag;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use PHPUnit\Framework\Attributes\TestDox;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

use function sprintf;

class PostServiceTest extends KernelTestCase
{
    private ?PostService $postService = null;

    #[TestDox('Tags created from request data receive a non-empty description')]
    public function testGeneratePostAttributesAssignsTagDescription(): void
    {
        $this->bootKernelAndFetchDependencies();

        $blog = (new Blog())
            ->setTitle('Service test blog')
            ->setAuthor(Uuid::uuid4());

        $user = new SymfonyUser(
            Uuid::uuid4()->toString(),
            'Service Tester',
            null,
            ['ROLE_USER']
        );

        $tagName = sprintf('service-tag-%s', Uuid::uuid4()->toString());
        $request = new Request([], [
            'title' => 'Service test post',
            'summary' => 'Service test summary',
            'content' => 'Service test content',
            'tags' => [$tagName],
        ]);

        $post = $this->postService->generatePostAttributes($blog, $user, $request);

        $tags = $post->getTags();
        self::assertCount(1, $tags);

        $tag = $tags->first();
        self::assertInstanceOf(Tag::class, $tag);
        self::assertSame(sprintf('Posts tagged with %s', $tagName), $tag->getDescription());
        self::assertNotSame('', $tag->getDescription());
    }

    private function bootKernelAndFetchDependencies(): void
    {
        if ($this->postService === null) {
            self::bootKernel();
            $this->postService = self::getContainer()->get(PostService::class);
        }
    }
}
