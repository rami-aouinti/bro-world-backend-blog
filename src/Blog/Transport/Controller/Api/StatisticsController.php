<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api;

use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\LikeRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class StatisticsController
{
    public function __construct(
        private CacheInterface $cache,
        private BlogRepositoryInterface $blogRepository,
        private PostRepositoryInterface $postRepository,
        private CommentRepositoryInterface $commentRepository,
        private LikeRepositoryInterface $likeRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users
     *
     * @throws InvalidArgumentException
     * @return JsonResponse
     */
    #[Route(path: '/v1/statistics', name: 'blog_statistics', methods: [Request::METHOD_GET])]
    #[Cache(smaxage: 60)]
    public function __invoke(): JsonResponse
    {
        $cacheKey = 'blog_statistics';

        $statistics = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(3600); // 1h

            return [
                'postsPerMonth' => $this->postRepository->countPostsByMonth(),
                'blogsPerMonth' => $this->blogRepository->countBlogsByMonth(),
                'likesPerMonth' => $this->likeRepository->countLikesByMonth(),
                'commentsPerMonth' => $this->commentRepository->countCommentsByMonth(),
            ];
        });

        return new JsonResponse($statistics);
    }
}
