<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\React;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Message\CreateNotificationMessenger;
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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class ToggleCommentController
{
    public function __construct(
        private SerializerInterface $serializer,
        private LikeRepositoryInterface $likeRepository,
        private MessageBusInterface $bus
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[Route(path: '/v1/platform/comment/{comment}/like', name: 'like_comment', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Comment $comment): JsonResponse
    {
        $like = new Like();
        $like->setComment($comment);
        $like->setUser(Uuid::fromString($symfonyUser->getUserIdentifier()));
        $this->bus->dispatch(
            new CreateNotificationMessenger(
                $request->headers->get('Authorization'),
                'PUSH',
                $symfonyUser->getUserIdentifier(),
                $comment->getAuthor()->toString(),
                $comment->getPost()?->getId(),
                'liked your comment.'
            )
        );
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
