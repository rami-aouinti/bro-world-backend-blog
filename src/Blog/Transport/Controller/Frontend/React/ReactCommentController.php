<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\React;

use App\Blog\Application\Service\Interfaces\ReactionNotificationMailerInterface;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use App\Blog\Domain\Message\CreateNotificationMessenger;
use App\Blog\Domain\Repository\Interfaces\ReactionRepositoryInterface;
use Bro\WorldCoreBundle\Domain\Utils\JSON;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
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
 * @package App\Blog\Transport\Controller\Frontend\React
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class ReactCommentController
{
    public function __construct(
        private SerializerInterface $serializer,
        private ReactionRepositoryInterface $reactionRepository,
        private MessageBusInterface $bus,
        private ReactionNotificationMailerInterface $reactionNotificationMailer
    ) {
    }

    /**
     * Handles user reactions on a post (add, update, delete).
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws NotSupported
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[Route(path: '/v1/private/comment/{comment}/react/{type}', name: 'react_comment', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Comment $comment, string $type): JsonResponse
    {
        $reaction = $this->reactionRepository->findOneBy([
            'user' => Uuid::fromString($symfonyUser->getId()),
            'comment' => $comment->getId(),
        ]);

        return $type === 'delete'
            ? $this->handleDeleteReaction($reaction)
            : $this->handleAddOrUpdateReactionComment($reaction, $comment, $symfonyUser, $request);
    }

    /**
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
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
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    private function handleAddOrUpdateReaction(?Reaction $existingReaction, Post $post, SymfonyUser $symfonyUser, Request $request): JsonResponse
    {
        if ($existingReaction) {
            $this->reactionRepository->remove($existingReaction);
        }

        $reaction = new Reaction();
        $reaction->setPost($post);
        $reaction->setUser(Uuid::fromString($symfonyUser->getId()));
        $reaction->setType($request->attributes->get('type'));

        $this->bus->dispatch(
            new CreateNotificationMessenger(
                $request->headers->get('Authorization'),
                'PUSH',
                $symfonyUser->getId(),
                $post->getAuthor()->toString(),
                $post->getId(),
                'reacted to your post.'
            )
        );

        $this->reactionNotificationMailer->sendPostReactionNotificationEmail(
            $post->getAuthor()->toString(),
            $symfonyUser->getId(),
            $post->getSlug()
        );

        $this->reactionRepository->save($reaction);

        return $this->jsonResponse([
            'id' => $reaction->getId(),
            'user' => $symfonyUser,
        ]);
    }


    /**
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    private function handleAddOrUpdateReactionComment(?Reaction $existingReaction, Comment $comment, SymfonyUser $symfonyUser, Request $request): JsonResponse
    {
        if ($existingReaction) {
            $this->reactionRepository->remove($existingReaction);
        }

        $reaction = new Reaction();
        $reaction->setComment($comment);
        $reaction->setUser(Uuid::fromString($symfonyUser->getId()));
        $reaction->setType($request->attributes->get('type'));

        $this->bus->dispatch(
            new CreateNotificationMessenger(
                $request->headers->get('Authorization'),
                'PUSH',
                $symfonyUser->getId(),
                $comment->getAuthor()->toString(),
                $comment->getId(),
                'reacted to your comment.'
            )
        );

        $this->reactionNotificationMailer->sendPostReactionNotificationEmail(
            $comment->getAuthor()->toString(),
            $symfonyUser->getId(),
            $comment->getPost()?->getSlug()
        );

        $this->reactionRepository->save($reaction);

        return $this->jsonResponse([
            'id' => $reaction->getId(),
            'user' => $symfonyUser,
        ]);
    }



    /**
     * @throws ExceptionInterface|JsonException
     */
    private function jsonResponse(mixed $data): JsonResponse
    {
        $json = JSON::decode($this->serializer->serialize($data, 'json', [
            'groups' => 'Reaction',
        ]), true);

        return new JsonResponse($json);
    }
}
