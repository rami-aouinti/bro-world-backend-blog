<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
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

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class DeletePostController
{
    public function __construct(
        private SerializerInterface $serializer,
        private PostRepositoryInterface $postRepository,
        private CacheInterface $cache
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     * @param Post        $post
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws InvalidArgumentException
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/post/{post}', name: 'delete_post', methods: [Request::METHOD_DELETE])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post): JsonResponse
    {
        for($i = 0; $i < 10; $i++) {
            $cacheKey = 'post_public_' . $i . '_' . 10;
            $this->cache->delete($cacheKey);
        }
        $this->postRepository->remove($post);
        $output = JSON::decode(
            $this->serializer->serialize(
                'deleted',
                'json',
                [
                    'groups' => 'Post',
                ]
            ),
            true,
        );
        return new JsonResponse($output);
    }
}
