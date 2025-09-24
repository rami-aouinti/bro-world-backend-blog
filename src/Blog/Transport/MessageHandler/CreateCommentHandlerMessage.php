<?php

declare(strict_types=1);

namespace App\Blog\Transport\MessageHandler;

use App\Blog\Application\Service\CommentService;
use App\Blog\Application\Service\Interfaces\CommentNotificationMailerInterface;
use App\Blog\Domain\Message\CreateCommentMessenger;
use App\Blog\Domain\Message\CreateNotificationMessenger;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @package App\Post\Transport\MessageHandler
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsMessageHandler]
readonly class CreateCommentHandlerMessage
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
        private CommentService $commentService,
        private CommentNotificationMailerInterface $commentNotificationMailer,
        private MessageBusInterface $bus
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function __invoke(CreateCommentMessenger $message): void
    {
        $this->handleMessage($message);
        $this->handleMail($message);
        $this->handleNotification($message);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    private function handleMessage(CreateCommentMessenger $message): void
    {
        $this->commentService->saveComment(
            $message->getComment(),
            $message->getPostId(),
            $message->getSenderId(),
            $message->getData()
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws ORMException
     */
    private function handleMail(CreateCommentMessenger $message): void
    {
        $post = $this->postRepository->find($message->getPostId());
        $this->commentNotificationMailer->sendCommentNotificationEmail(
            $post?->getAuthor()->toString(),
            $message->getUserId(),
            $post?->getSlug()
        );
    }

    /**
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    private function handleNotification(CreateCommentMessenger $message): void
    {
        $this->postRepository->find($message->getPostId());
        $this->bus->dispatch(
            new CreateNotificationMessenger(
                $message->getToken(),
                'PUSH',
                $message->getSenderId(),
                $message->getUserId(),
                $message->getPostId(),
                'commented on your post.'
            )
        );
    }
}
