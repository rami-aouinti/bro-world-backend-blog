<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\NotificationService;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\General\Infrastructure\Service\ApiProxyService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class NotificationServiceTest extends TestCase
{
    public function testCreateNotificationWithoutPostIdUsesSafeDefaults(): void
    {
        $proxyService = $this->createMock(ApiProxyService::class);
        $postRepository = $this->createMock(PostRepositoryInterface::class);
        $commentRepository = $this->createMock(CommentRepositoryInterface::class);
        $userProxy = $this->createMock(UserProxy::class);

        $postRepository->expects($this->never())->method('find');
        $commentRepository->expects($this->never())->method('find');

        $userProxy->expects($this->once())
            ->method('searchUser')
            ->with('symfony-user')
            ->willReturn(null);

        $proxyService->expects($this->once())
            ->method('request')
            ->with(
                Request::METHOD_POST,
                'notification',
                'token',
                $this->callback(static function (array $payload) {
                    self::assertSame([
                        'channel' => 'channel',
                        'scope' => 'INDIVIDUAL',
                        'topic' => '/notifications/target-user',
                        'pushTitle' => 'New comment',
                        'pushSubtitle' => '',
                        'pushContent' => 'https://bro-world-space.com/post/',
                        'scopeTarget' => '["target-user"]',
                    ], $payload);

                    return true;
                }),
                'api/v1/platform/notifications'
            );

        $service = new NotificationService(
            $proxyService,
            $postRepository,
            $commentRepository,
            $userProxy
        );

        $service->createNotification(
            'token',
            'channel',
            'symfony-user',
            'target-user',
            null,
            'New comment'
        );
    }

    public function testCreateNotificationWithPostIdUsesSlug(): void
    {
        $proxyService = $this->createMock(ApiProxyService::class);
        $postRepository = $this->createMock(PostRepositoryInterface::class);
        $commentRepository = $this->createMock(CommentRepositoryInterface::class);
        $userProxy = $this->createMock(UserProxy::class);

        $post = $this->createMock(Post::class);
        $post->method('getSlug')->willReturn('post-slug');

        $postRepository->expects($this->once())
            ->method('find')
            ->with('post-id')
            ->willReturn($post);

        $userProxy->expects($this->once())
            ->method('searchUser')
            ->with('symfony-user')
            ->willReturn([
                'firstName' => 'John',
                'lastName' => 'Doe',
                'photo' => 'photo-url',
            ]);

        $proxyService->expects($this->once())
            ->method('request')
            ->with(
                Request::METHOD_POST,
                'notification',
                'token',
                $this->callback(static function (array $payload) {
                    self::assertSame([
                        'channel' => 'channel',
                        'scope' => 'INDIVIDUAL',
                        'topic' => '/notifications/target-user',
                        'pushTitle' => 'John Doe Title',
                        'pushSubtitle' => 'photo-url',
                        'pushContent' => 'https://bro-world-space.com/post/post-slug',
                        'scopeTarget' => '["target-user"]',
                    ], $payload);

                    return true;
                }),
                'api/v1/platform/notifications'
            );

        $service = new NotificationService(
            $proxyService,
            $postRepository,
            $commentRepository,
            $userProxy
        );

        $service->createNotification(
            'token',
            'channel',
            'symfony-user',
            'target-user',
            'post-id',
            'Title'
        );
    }
}
