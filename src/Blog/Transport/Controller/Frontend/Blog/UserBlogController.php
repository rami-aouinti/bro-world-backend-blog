<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Blog;

use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use Closure;
use Doctrine\ORM\Exception\NotSupported;
use Exception;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
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
 * @package App\Blog\Transport\Controller\Frontend\Blog
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class UserBlogController
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
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    #[Route(path: '/v1/profile/blog', name: 'public_blog_profile', methods: [Request::METHOD_GET])]
    #[Cache(smaxage: 10)]
    public function __invoke(SymfonyUser $symfonyUser): JsonResponse
    {
        $cacheKey = 'profile_blog_' . $symfonyUser->getId();
        $blogs = $this->cache->get($cacheKey, fn (ItemInterface $item) => $this->getClosure($symfonyUser->getId())($item));
        $json = $this->serializer->serialize(
            $blogs,
            'json',
            [
                'groups' => 'BlogProfile',
            ]
        );

        return JsonResponse::fromJsonString($json);
    }

    private function getClosure(string $userId): Closure
    {
        return function (ItemInterface $item) use ($userId): array {
            $item->expiresAfter(31536000);

            return $this->getFormattedBlog($userId);
        };
    }

    /**
     * @throws Exception
     */
    private function getFormattedBlog(string $userId): array
    {
        return $this->getBlog($userId);
    }

    /**
     * @throws NotSupported
     */
    private function getBlog(string $userId): array
    {
        return $this->blogRepository->findBy([
            'author' => Uuid::fromString($userId),
        ]);
    }
}
