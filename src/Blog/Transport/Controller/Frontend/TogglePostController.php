<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Entity\Post;
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
readonly class TogglePostController
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
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     * @param Post        $post
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/post/{post}/like', name: 'like_post', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post): JsonResponse
    {
        $like = new Like();
        $like->setPost($post);
        $like->setUser(Uuid::fromString($symfonyUser->getUserIdentifier()));
        $this->bus->dispatch(
            new CreateNotificationMessenger(
                $request->headers->get('Authorization'),
                'PUSH',
                $symfonyUser->getUserIdentifier(),
                $post->getAuthor()->toString(),
                $post->getId(),
                'liked your post.'
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
