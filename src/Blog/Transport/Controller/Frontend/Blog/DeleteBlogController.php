<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Blog;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use OpenApi\Attributes as OA;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @package App\Blog\Transport\Controller\Frontend\Blog
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class DeleteBlogController
{
    public function __construct(
        private BlogRepositoryInterface $blogRepository,
        private CacheItemPoolInterface $cacheItemPool,
        private CacheInterface $cacheInterface,
    ) {
    }

    /**
     * Remove an existing blog owned by the authenticated user.
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/v1/platform/blog/{blog}', name: 'blog_delete', methods: [Request::METHOD_DELETE])]
    public function __invoke(SymfonyUser $symfonyUser, Blog $blog): JsonResponse
    {
        if ($blog->getAuthor()->toString() !== $symfonyUser->getId()) {
            return new JsonResponse([
                'error' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $authorId = $symfonyUser->getId();
        $slug = $blog->getSlug();

        $this->blogRepository->remove($blog);

        $this->cacheItemPool->deleteItem('profile_blog_' . $authorId);
        $this->cacheInterface->delete('public_blog');

        if ($slug !== null) {
            $this->cacheInterface->delete('private_blog_' . $slug);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
