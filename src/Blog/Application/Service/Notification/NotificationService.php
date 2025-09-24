<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Notification;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\General\Infrastructure\Service\ApiProxyService;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @package App\Blog\Application\Service\Notification
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class NotificationService
{
    private const string PATH = 'notification';
    private const string CREATE_NOTIFICATION_PATH = 'api/v1/platform/notifications';

    public function __construct(
        private ApiProxyService $proxyService,
        private PostRepositoryInterface $postRepository,
        private CommentRepositoryInterface $commentRepository,
        private UserProxy $userProxy
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransactionRequiredException
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     */
    public function executeCreateNotificationCommand(
        ?string $token,
        ?string $channel,
        ?string $symfonyUserId,
        ?string $userId,
        ?string $postId,
        ?string $title
    ): void {
        if ($symfonyUserId !== $userId) {
            $post = null;
            if ($postId) {
                $post = $this->getOriginPost($postId);
            }

            $senderData = $this->userProxy->searchUser($symfonyUserId) ?? [];

            $firstName = is_array($senderData) ? (string)($senderData['firstName'] ?? '') : '';
            $lastName = is_array($senderData) ? (string)($senderData['lastName'] ?? '') : '';
            $photo = is_array($senderData) ? (string)($senderData['photo'] ?? '') : '';

            $titleParts = array_filter([
                $firstName,
                $lastName,
                (string)($title ?? ''),
            ], static fn (string $part) => $part !== '');
            $pushTitle = trim(implode(' ', $titleParts));

            $slug = $post instanceof Post ? $post->getSlug() : '';

            $notification = [
                'channel' => $channel,
                'scope' => 'INDIVIDUAL',
                'topic' => '/notifications/' . $userId,
                'pushTitle' => $pushTitle,
                'pushSubtitle' => $photo,
                'pushContent' => 'https://bro-world-space.com/post/' . $slug,
                'scopeTarget' => '["' . $userId . '"]',
            ];

            $this->executeCreatePushCommand($token, $notification);
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws JsonException
     */
    public function executeCreatePushCommand(
        ?string $token,
        array $data
    ): void {
        $this->proxyService->request(
            Request::METHOD_POST,
            self::PATH,
            $token,
            $data,
            self::CREATE_NOTIFICATION_PATH
        );
    }

    /**
     * @throws JsonException
     * @throws TransportExceptionInterface
     */
    public function executeCreateEmailCommand(
        ?string $token,
        array $data,
        SymfonyUser $user
    ): void {
        $this->proxyService->request(
            Request::METHOD_POST,
            self::PATH,
            $token,
            [
                'channel' => 'EMAIL',
                'templateId' => $data['templateId'],
                'emailSenderName' => $data['emailSenderName'],
                'emailSenderEmail' => $data['emailSenderEmail'],
                'emailSubject' => $data['emailSubject'],
                'recipients' => $data['recipients'],
                'scope' => 'INDIVIDUAL',
                'scopeTarget' => [$user->getUserIdentifier()],
            ],
            self::CREATE_NOTIFICATION_PATH
        );
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @return Post|void|null
     */
    private function getOriginPost($postId)
    {
        $post = $this->postRepository->find($postId);
        if ($post) {
            return $post;
        }

        $comment = $this->commentRepository->find($postId);

        if ($comment) {
            if ($comment->getPost()) {
                return $comment->getPost();
            }

            return $this->getOriginPost($comment->getParent()?->getId());
        }
    }
}
