<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Repository\Interfaces\LikeRepositoryInterface;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class ToogleCommentController
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
     * @param Comment     $comment
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws JsonException
     * @throws ExceptionInterface
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/comment/{comment}/like', name: 'like_comment', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Comment $comment): JsonResponse
    {
        $like = new Like();
        $like->setComment($comment);
        $like->setUser(Uuid::fromString($symfonyUser->getUserIdentifier()));

        $this->likeRepository->save($like);
        $result = [];
        $result['id'] = $like->getId();
        $result['user'] = $symfonyUser;
        $output = JSON::decode(
            $this->serializer->serialize(
                $result,
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
