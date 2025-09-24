<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Transport\Controller\Frontend\React;

use App\Blog\Application\Service\Interfaces\ReactionNotificationMailerInterface;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use App\Blog\Domain\Repository\Interfaces\ReactionRepositoryInterface;
use App\Blog\Transport\Controller\Frontend\React\ReactPostController;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function json_encode;

final class ReactPostControllerTest extends TestCase
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new class () implements SerializerInterface {
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

    public function testMailerIsTriggeredWhenReactionIsCreated(): void
    {
        $postAuthor = Uuid::uuid4();
        $reactor = Uuid::uuid4();
        $post = $this->createPost($postAuthor, 'my-post-slug');

        $reactionRepository = new class () implements ReactionRepositoryInterface {
            public ?Reaction $saved = null;

            public function findOneBy(array $criteria, ?array $orderBy = null)
            {
                return null;
            }

            public function save(Reaction $reaction): void
            {
                $this->saved = $reaction;
            }

            public function remove(Reaction $reaction): void
            {
            }
        };

        /** @var MessageBusInterface&MockObject $bus */
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch');

        /** @var ReactionNotificationMailerInterface&MockObject $mailer */
        $mailer = $this->createMock(ReactionNotificationMailerInterface::class);
        $mailer->expects(self::once())
            ->method('sendPostReactionNotificationEmail')
            ->with($postAuthor->toString(), $reactor->toString(), 'my-post-slug');

        $controller = new ReactPostController(
            $this->serializer,
            $reactionRepository,
            $bus,
            $mailer
        );

        $symfonyUser = new SymfonyUser($reactor->toString(), 'Reactor', null, []);
        $request = new Request([], [], [
            'type' => 'like',
        ], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer token',
        ]);

        $controller($symfonyUser, $request, $post, 'like');

        self::assertInstanceOf(Reaction::class, $reactionRepository->saved);
        self::assertSame('like', $reactionRepository->saved?->getType());
    }

    private function createPost(UuidInterface $author, string $slug): Post
    {
        $post = new Post();
        $post->setAuthor($author);
        $post->setSlug($slug);

        return $post;
    }
}
