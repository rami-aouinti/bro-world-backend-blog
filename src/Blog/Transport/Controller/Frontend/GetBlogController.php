<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use App\General\Domain\Utils\JSON;
use Closure;
use Doctrine\ORM\Exception\NotSupported;
use Exception;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
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
readonly class GetBlogController
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
     * @param string $slug
     *
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/blog/{slug}', name: 'public_blog_slug', methods: [Request::METHOD_GET])]
    public function __invoke(string $slug): JsonResponse
    {
        $cacheKey = 'public_blog_' . $slug;
        $blogs = $this->cache->get($cacheKey, fn (ItemInterface $item) => $this->getClosure($slug)($item));
        $output = JSON::decode(
            $this->serializer->serialize(
                $blogs,
                'json',
                [
                    'groups' => 'Blog',
                ]
            ),
            true,
        );
        return new JsonResponse($output);
    }

    /**
     *
     * @param string $slug
     *
     * @return Closure
     */
    private function getClosure(string $slug): Closure
    {
        return function (ItemInterface $item) use ($slug): Blog {
            $item->expiresAfter(3600);

            return $this->getFormattedPosts($slug);
        };
    }

    /**
     * @throws Exception
     */
    private function getFormattedPosts(string $slug): Blog
    {
        return $this->getBlog($slug);
    }

    /**
     * @param $slug
     *
     * @throws NotSupported
     * @return Blog
     */
    private function getBlog($slug): Blog
    {
        return $this->blogRepository->findOneBy([
            'slug' => $slug
        ]);
    }
}
