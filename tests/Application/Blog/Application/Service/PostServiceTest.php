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
    }
}
