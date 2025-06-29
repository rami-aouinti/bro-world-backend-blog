<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\General\Infrastructure\Service\ApiProxyService;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Class MediaService
 *
 * @package App\Blog\Application\Service
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
        private BlogRepositoryInterface $blogRepository,
        private UserProxy $userProxy
    ) {}

    /**
     * @param string|null $token
     * @param string|null $channel
     * @param string|null $userId
     * @param string|null $postId
     * @param string|null $commentId
     * @param string|null $blogId
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransactionRequiredException
     * @throws TransportExceptionInterface
     * @throws JsonException
     * @return void
     */
    public function createNotification(
        ?string $token,
        ?string $channel,
        ?string $userId,
        ?string $postId,
        ?string $commentId,
        ?string $blogId
    ): void
    {
        $scopeTarget = [];
        $post = [];
        $user['firstName'] = '';
        $user['lastName'] = '';
        if($postId) {
            $post = $this->postRepository->find($postId);
            $scopeTarget = $post?->getAuthor()->toString();
        }
        if($commentId) {
            $comment = $this->commentRepository->find($commentId);
            $scopeTarget = $comment?->getAuthor()->toString();
            $post = $comment?->getPost();
        }
        if($blogId) {
            $blog = $this->blogRepository->find($blogId);
            $scopeTarget = $blog?->getAuthor()->toString();
        }
        if($userId) {
            $users = $this->userProxy->getUsers();
            $usersById = [];
            foreach ($users as $user) {
                $usersById[$user['id']] = $user;
            }
            $user = $usersById[$userId];
        }


        $data = [
            'channel' => $channel,
            'scope' => 'INDIVIDUAL',
            'topic' => '/notifications/' . $scopeTarget,
            'pushTitle' => $user['firstName'] . ' ' . $user['lastName'] . ' commented on your post.',
            'pushSubtitle' => 'Someone commented on your post.',
            'pushContent' => 'https://bro-world-space.com/post/' . $post?->getSlug(),
            'scopeTarget' => '["' . $scopeTarget . '"]',
        ];

        $this->createPush($token, $data);
    }


    /**
     * @param string|null $token
     * @param array       $data
     *
     * @throws TransportExceptionInterface
     * @throws JsonException
     */
    public function createPush(
        ?string $token,
        array $data
    ): void
    {
        $this->proxyService->request(
            Request::METHOD_POST,
            self::PATH,
            $token,
            $data,
            self::CREATE_NOTIFICATION_PATH
        );
    }

    /**
     * @param string|null $token
     * @param array       $data
     * @param SymfonyUser $user
     *
     * @throws JsonException
     * @throws TransportExceptionInterface
     * @return void
     */
    public function createEmail(
        ?string $token,
        array $data,
        SymfonyUser $user): void
    {
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
}
