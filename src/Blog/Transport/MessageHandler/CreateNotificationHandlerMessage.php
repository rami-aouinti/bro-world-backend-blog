<?php

declare(strict_types=1);

namespace App\Blog\Transport\MessageHandler;

use App\Blog\Application\Service\NotificationService;
use App\Blog\Domain\Message\CreateNotificationMessenger;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use JsonException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class CreatePostHandlerMessage
 *
 * @package App\Post\Transport\MessageHandler
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsMessageHandler]
readonly class CreateNotificationHandlerMessage
{
    public function __construct(
        private NotificationService $notificationService
    )
    {
    }

    /**
     * @param CreateNotificationMessenger $message
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransactionRequiredException
     * @throws TransportExceptionInterface
     * @return void
     */
    public function __invoke(CreateNotificationMessenger $message): void
    {
        $this->handleMessage($message);
    }

    /**
     * @param CreateNotificationMessenger $message
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws JsonException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function handleMessage(CreateNotificationMessenger $message): void
    {
        $this->notificationService->createNotification(
            $message->getToken(),
            $message->getChannel(),
            $message->getSenderId(),
            $message->getUserId(),
            $message->getPostId(),
            $message->getMessage()
        );
    }
}
