<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\Resource;

use App\Blog\Application\DTO\Like\Like as LikeDto;
use App\Blog\Application\Resource\LikeResource;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like as LikeEntity;
use App\Blog\Domain\Entity\Post;
use App\Blog\Infrastructure\Repository\LikeRepository;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Validation;

/**
 * @covers \App\Blog\Application\Resource\LikeResource
 */
final class LikeResourceTest extends TestCase
{
    public function testLikeCanBeCreatedAndUpdated(): void
    {
        $storage = [];
        $repository = $this->createMock(LikeRepository::class);
        $repository->method('getEntityName')->willReturn(LikeEntity::class);
        $repository->method('find')->willReturnCallback(
            static function (
                string $id,
                ?int $lockMode = null,
                ?int $lockVersion = null,
                ?string $entityManagerName = null
            ) use (&$storage): ?LikeEntity {
                return $storage[$id] ?? null;
            }
        );
        $repository->method('save')->willReturnCallback(
            static function (
                EntityInterface $entity,
                ?bool $flush = null,
                ?bool $skipValidation = null,
                ?string $entityManagerName = null
            ) use (&$storage, &$repository): LikeRepository {
                $storage[$entity->getId()] = $entity;

                return $repository;
            }
        );

        $resource = new LikeResource($repository);
        $resource->setValidator(Validation::createValidator());

        $dto = (new LikeDto())
            ->setUser(Uuid::uuid4())
            ->setPost(new Post());

        $entity = $resource->create($dto, flush: false, skipValidation: true);

        self::assertInstanceOf(LikeEntity::class, $entity);
        self::assertSame($dto->getUser(), $entity->getUser());
        self::assertSame($dto->getPost(), $entity->getPost());
        self::assertNull($entity->getComment());

        $comment = new Comment();
        $updateDto = (new LikeDto())
            ->setUser($dto->getUser())
            ->setPost(null)
            ->setComment($comment);

        $resource->update($entity->getId(), $updateDto, flush: false, skipValidation: true);

        $updated = $storage[$entity->getId()];
        self::assertSame($comment, $updated->getComment());
        self::assertNull($updated->getPost());
    }
}
