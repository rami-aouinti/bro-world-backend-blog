<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Transport\Controller\Frontend\React;

use App\Blog\Application\Service\Interfaces\ReactionNotificationMailerInterface;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\LikeRepositoryInterface;
use App\Blog\Transport\Controller\Frontend\React\ToggleCommentController;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function json_encode;

final class ToggleCommentControllerTest extends TestCase
{
    /** @var SerializerInterface */
    private $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new class() implements SerializerInterface {
            public function serialize($data, string $format, array $context = []): string
            {
                return (string)json_encode($data);
            }

            public function deserialize($data, string $type, string $format, array $context = []): mixed
            {
                throw new \BadMethodCallException('Not needed in tests.');
            }
        };
    }

    public function testMailerIsTriggeredWhenCommentIsLiked(): void
    {
        $commentAuthorId = Uuid::uuid4()->toString();
        $reactorId = Uuid::uuid4()->toString();

        $post = new Post();
        $post->setAuthor(Uuid::fromString($commentAuthorId));
        $post->setSlug('post-slug');

        $comment = new Comment();
        $comment->setAuthor(Uuid::fromString($commentAuthorId));
        $comment->setPost($post);

        $likeRepository = new class() implements LikeRepositoryInterface {
            public ?Like $saved = null;

            public function countLikesByMonth(): array
            {
                return [];
            }

            public function save(Like $like): void
            {
                $this->saved = $like;
            }
        };

        /** @var MessageBusInterface&MockObject $bus */
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch');

        /** @var ReactionNotificationMailerInterface&MockObject $mailer */
        $mailer = $this->createMock(ReactionNotificationMailerInterface::class);
        $mailer->expects(self::once())
            ->method('sendCommentReactionNotificationEmail')
            ->with($commentAuthorId, $reactorId, 'post-slug');

        $controller = new ToggleCommentController(
            $this->serializer,
            $likeRepository,
            $bus,
            $mailer
        );

        $symfonyUser = new SymfonyUser($reactorId, 'Reactor', null, []);
        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer token']);

        $controller($symfonyUser, $request, $comment);

        self::assertInstanceOf(Like::class, $likeRepository->saved);
        self::assertSame($reactorId, $likeRepository->saved?->getUser()->toString());
    }
}
