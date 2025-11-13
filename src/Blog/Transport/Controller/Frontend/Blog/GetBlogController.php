<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Blog;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use Closure;
use Doctrine\ORM\Exception\NotSupported;
use Exception;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @package App\Blog\Transport\Controller\Frontend\Blog
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class GetBlogController
{
    public function __construct(
        private CacheInterface $cache,
        private BlogRepositoryInterface $blogRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/v1/platform/blog/{slug}', name: 'public_blog_slug', methods: [Request::METHOD_GET])]
    public function __invoke(string $slug): JsonResponse
    {
        $cacheKey = 'private_blog_' . $slug;
        $blog = $this->cache->get($cacheKey, fn (ItemInterface $item) => $this->getClosure($slug)($item));

        return new JsonResponse($blog);
    }

    private function getClosure(string $slug): Closure
    {
        return function (ItemInterface $item) use ($slug): Blog {
            $item->expiresAfter(31536000);

            return $this->getFormattedBlog($slug);
        };
    }

    /**
     * @throws Exception
     */
    private function getFormattedBlog(string $slug): Blog
    {
        return $this->getBlog($slug);
    }

    /**
     * @throws NotSupported
     */
    private function getBlog($slug): Blog
    {
        return $this->blogRepository->findOneBy([
            'slug' => $slug,
        ]);
    }
}
