<?php

declare(strict_types=1);

namespace App\Blog\Transport\MessageHandler;

use App\Blog\Application\Service\Notification\NotificationService;
use App\Blog\Domain\Message\CreateNotificationMessenger;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @package App\Blog\Transport\MessageHandler
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
#[AsMessageHandler]
readonly class CreateNotificationHandlerMessage
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransactionRequiredException
     * @throws TransportExceptionInterface
     */
    public function __invoke(CreateNotificationMessenger $message): void
    {
        $this->handleMessage($message);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws JsonException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     */
    private function handleMessage(CreateNotificationMessenger $message): void
    {
        $this->notificationService->executeCreateNotificationCommand(
            $message->getToken(),
            $message->getChannel(),
            $message->getSenderId(),
            $message->getUserId(),
            $message->getPostId(),
            $message->getMessage()
        );
    }
}
