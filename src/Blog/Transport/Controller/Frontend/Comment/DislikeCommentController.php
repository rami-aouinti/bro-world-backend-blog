<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Comment;

use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Repository\Interfaces\LikeRepositoryInterface;
use App\General\Domain\Utils\JSON;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class DislikeCommentController
{
    public function __construct(
        private SerializerInterface $serializer,
        private LikeRepositoryInterface $likeRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    #[Route(path: '/v1/platform/comment/{like}/dislike', name: 'dislike_comment', methods: [Request::METHOD_POST])]
    public function __invoke(Like $like): JsonResponse
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
