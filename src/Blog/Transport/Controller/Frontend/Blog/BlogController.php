<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Blog;

use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use Closure;
use Doctrine\ORM\Exception\NotSupported;
use Exception;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class BlogController
{
    public function __construct(
        private SerializerInterface $serializer,
        private CacheInterface $cache,
        private BlogRepositoryInterface $blogRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ExceptionInterface
     */
    #[Route(path: '/public/blog', name: 'public_blog_index', methods: [Request::METHOD_GET])]
    #[Cache(smaxage: 10)]
    public function __invoke(): JsonResponse
    {
        $cacheKey = 'public_blog';
        $blogs = $this->cache->get($cacheKey, fn (ItemInterface $item) => $this->getClosure()($item));
        $json = $this->serializer->serialize(
            $blogs,
            'json',
            [
                'groups' => 'Blog',
            ]
        );

        return JsonResponse::fromJsonString($json);
    }

    private function getClosure(): Closure
    {
        return function (ItemInterface $item): array {
            $item->expiresAfter(31536000);

            return $this->getFormattedPosts();
        };
    }

    /**
     * @throws Exception
     */
    private function getFormattedPosts(): array
    {
        return $this->getBlogs();
    }

    /**
     * @throws NotSupported
     */
    private function getBlogs(): array
    {
        return $this->blogRepository->findAll();
    }
}
