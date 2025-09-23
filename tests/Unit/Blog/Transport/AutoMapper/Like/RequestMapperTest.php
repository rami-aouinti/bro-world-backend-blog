<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Transport\AutoMapper\Like;

use App\Blog\Application\DTO\Like\Like as LikeDto;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Transport\AutoMapper\Like\RequestMapper;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\Blog\Transport\AutoMapper\Like\RequestMapper
 */
final class RequestMapperTest extends TestCase
{
    public function testRequestMapperPopulatesEntityReferences(): void
    {
        $userId = Uuid::uuid4();
        $postId = Uuid::uuid4();
        $commentId = Uuid::uuid4();
        $postReference = new Post();
        $commentReference = new Comment();

        $postManager = $this->createMock(ObjectManager::class);
        $postManager->expects(self::once())
            ->method('getReference')
            ->with(Post::class, $postId)
            ->willReturn($postReference);

        $commentManager = $this->createMock(ObjectManager::class);
        $commentManager->expects(self::once())
            ->method('getReference')
            ->with(Comment::class, $commentId)
            ->willReturn($commentReference);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::exactly(2))
            ->method('getManagerForClass')
            ->willReturnMap([
                [Post::class, $postManager],
                [Comment::class, $commentManager],
            ]);

        $mapper = new RequestMapper($registry);
        $request = new Request([], [
            'userId' => $userId->toString(),
            'postId' => $postId->toString(),
            'commentId' => $commentId->toString(),
        ]);

        /** @var LikeDto $dto */
        $dto = $mapper->map($request, LikeDto::class);

        self::assertTrue($userId->equals($dto->getUser()));
        self::assertSame($postReference, $dto->getPost());
        self::assertSame($commentReference, $dto->getComment());
    }

    public function testRequestMapperSkipsMissingAssociations(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        $mapper = new RequestMapper($registry);
        $request = new Request([], [
            'userId' => Uuid::uuid4()->toString(),
        ]);

        /** @var LikeDto $dto */
        $dto = $mapper->map($request, LikeDto::class);

        self::assertNull($dto->getPost());
        self::assertNull($dto->getComment());
    }
}
