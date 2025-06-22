<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Repository\Interfaces\LikeRepositoryInterface;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class DislikePostController
{
    public function __construct(
        private SerializerInterface $serializer,
        private LikeRepositoryInterface $likeRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     * @param Like        $like
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/post/{like}/dislike', name: 'dislike_post', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Like $like): JsonResponse
    {
        $this->likeRepository->remove($like);

        $output = JSON::decode(
            $this->serializer->serialize(
                'Success',
                'json',
                [
                    'groups' => 'Like',
                ]
            ),
            true,
        );
        return new JsonResponse($output);
    }
}
