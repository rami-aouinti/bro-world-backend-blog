<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Transport\Controller\Frontend\Comment;

use App\Blog\Application\Service\Interfaces\CommentNotificationMailerInterface;
use App\Blog\Application\Service\NotificationService;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\Blog\Transport\Controller\Frontend\Comment\CommentCommentController;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

class CommentCommentControllerTest extends TestCase
{
    private SerializerInterface&MockObject $serializer;
    private CommentRepositoryInterface&MockObject $commentRepository;
    private NotificationService&MockObject $notificationService;
    private CommentNotificationMailerInterface&MockObject $commentNotificationMailer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->commentRepository = $this->createMock(CommentRepositoryInterface::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->commentNotificationMailer = $this->createMock(CommentNotificationMailerInterface::class);
    }

    public function testReplyingToCommentTriggersMailer(): void
    {
        $controller = new CommentCommentController(
            $this->serializer,
            $this->commentRepository,
            $this->notificationService,
            $this->commentNotificationMailer
        );

        $parentAuthorId = '00000000-0000-0000-0000-000000000001';
        $replyAuthorId = '00000000-0000-0000-0000-000000000002';

        $parentComment = new Comment();
        $parentComment->setAuthor(Uuid::fromString($parentAuthorId));

        $post = $this->createMock(Post::class);
        $post->method('getId')->willReturn('post-id');
        $post->method('getSlug')->willReturn('post-slug');
        $parentComment->setPost($post);

        $request = new Request(
            [],
            [
                'content' => 'Thanks for your insight!',
            ],
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer token',
            ]
        );

        $symfonyUser = new SymfonyUser($replyAuthorId, null, null, []);

        $this->commentRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Comment $comment) use ($parentComment, $replyAuthorId) {
                self::assertSame($parentComment, $comment->getParent());
                self::assertSame($replyAuthorId, $comment->getAuthor()->toString());

                return true;
            }));

        $this->notificationService
            ->expects(self::once())
            ->method('createNotification')
            ->with(
                'Bearer token',
                'PUSH',
                $replyAuthorId,
                $parentAuthorId,
                'post-id',
                'commented on your comment.'
            );

        $this->serializer
            ->expects(self::once())
            ->method('serialize')
            ->with(self::isInstanceOf(Comment::class), 'json', [
                'groups' => 'Comment',
            ])
            ->willReturn('{}');

        $this->commentNotificationMailer
            ->expects(self::once())
            ->method('sendCommentReplyNotificationEmail')
            ->with(
                $parentAuthorId,
                $replyAuthorId,
                self::callback(static function (Comment $comment) use ($parentComment) {
                    return $comment->getParent() === $parentComment;
                })
            );

        $response = $controller->__invoke($symfonyUser, $request, $parentComment);

        self::assertSame(200, $response->getStatusCode());
    }
}
