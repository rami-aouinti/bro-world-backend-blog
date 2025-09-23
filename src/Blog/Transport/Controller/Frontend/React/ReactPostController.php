<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\React;

use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use App\Blog\Domain\Message\CreateNotificationMessenger;
use App\Blog\Domain\Repository\Interfaces\ReactionRepositoryInterface;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\NotSupported;
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
 * Class ReactPostController
 *
 * @package App\Blog\Transport\Controller\Frontend
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class ReactPostController
{
    public function __construct(
        private SerializerInterface $serializer,
        private ReactionRepositoryInterface $reactionRepository,
        private MessageBusInterface $bus
    ) {}

    /**
     * Handles user reactions on a post (add, update, delete).
     *
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     * @param Post        $post
     * @param string      $type
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws NotSupported
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @return JsonResponse
     */
    #[Route(path: '/v1/private/post/{post}/react/{type}', name: 'react_post', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post, string $type): JsonResponse
    {
        $reaction = $this->reactionRepository->findOneBy(['user' => Uuid::fromString($symfonyUser->getUserIdentifier()), 'post' => $post->getId()]);

        return $type === 'delete'
            ? $this->handleDeleteReaction($reaction)
            : $this->handleAddOrUpdateReaction($reaction, $post, $symfonyUser, $request);
    }

    /**
     *
     * @param Reaction|null $reaction
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @return JsonResponse
     */
    private function handleDeleteReaction(?Reaction $reaction): JsonResponse
    {
        if ($reaction) {
            $this->reactionRepository->remove($reaction);
            return $this->jsonResponse('success');
        }

        return $this->jsonResponse('reaction not found');
    }

    /**
     *
     * @param Reaction|null $existingReaction
     * @param Post          $post
     * @param SymfonyUser   $symfonyUser
     * @param Request       $request
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @return JsonResponse
     */
    private function handleAddOrUpdateReaction(?Reaction $existingReaction, Post $post, SymfonyUser $symfonyUser, Request $request): JsonResponse
    {
        if ($existingReaction) {
            $this->reactionRepository->remove($existingReaction);
        }

        $reaction = new Reaction();
        $reaction->setPost($post);
        $reaction->setUser(Uuid::fromString($symfonyUser->getUserIdentifier()));
        $reaction->setType($request->attributes->get('type'));

        $this->bus->dispatch(
            new CreateNotificationMessenger(
                $request->headers->get('Authorization'),
                'PUSH',
                $symfonyUser->getUserIdentifier(),
                $post->getAuthor()->toString(),
                $post->getId(),
                'reacted to your post.'
            )
        );

        $this->reactionRepository->save($reaction);

        return $this->jsonResponse([
            'id' => $reaction->getId(),
            'user' => $symfonyUser
        ]);
    }

    /**
     *
     * @param mixed $data
     * @return JsonResponse
     * @throws ExceptionInterface|JsonException
     */
    private function jsonResponse(mixed $data): JsonResponse
    {
        $json = JSON::decode($this->serializer->serialize($data, 'json', ['groups' => 'Reaction']), true);
        return new JsonResponse($json);
    }
}
