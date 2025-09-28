<?php

declare(strict_types=1);

namespace App\Blog\Transport\MessageHandler;

use App\Blog\Application\Service\Comment\CommentService;
use App\Blog\Application\Service\Interfaces\CommentNotificationMailerInterface;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Message\CreateCommentMessenger;
use App\Blog\Domain\Message\CreateNotificationMessenger;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\Blog\Transport\Event\CommentCreatedEvent;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package App\Blog\Transport\MessageHandler
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsMessageHandler]
readonly class CreateCommentHandlerMessage
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
        private CommentService $commentService,
        private CommentNotificationMailerInterface $commentNotificationMailer,
        private MessageBusInterface $bus,
        private EventDispatcherInterface $eventDispatcher
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
        $comment = $this->handleMessage($message);

        $event = new CommentCreatedEvent($comment);
        $this->eventDispatcher->dispatch($event);

        if ($event->isBlocked()) {
            return;
        }

        $this->handleMail($message);
        $this->handleNotification($message);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    private function handleMessage(CreateCommentMessenger $message): Comment
    {
        return $this->commentService->executeSaveCommentCommand(
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
            $message->getSenderId(),
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
