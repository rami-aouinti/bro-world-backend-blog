<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Like;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Transport\AutoMapper\RestRequestMapper;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package App\Like
 */
class RequestMapper extends RestRequestMapper
{
    /**
     * @var array<int, non-empty-string>
     */
    protected static array $properties = [
        'user',
        'post',
        'comment',
    ];

    /**
     * @var array<string, string>
     */
    private static array $requestPropertyMap = [
        'user' => 'userId',
        'post' => 'postId',
        'comment' => 'commentId',
    ];

    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
    }

    #[Override]
    public function mapToObject($source, $destination, array $context = []): RestDtoInterface
    {
        if ($source instanceof Request) {
            foreach (self::$requestPropertyMap as $property => $requestKey) {
                if (!$source->request->has($requestKey) || $source->request->has($property)) {
                    continue;
                }

                $value = $source->request->get($requestKey);

                if ($value === null || $value === '') {
                    continue;
                }

                $source->request->set($property, $value);
            }
        }

        return parent::mapToObject($source, $destination, $context);
    }

    private function transformUser(mixed $value): ?UuidInterface
    {
        if ($value instanceof UuidInterface) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Uuid::fromString((string) $value);
    }

    private function transformPost(mixed $value): ?Post
    {
        return $this->resolveAssociation($value, Post::class);
    }

    private function transformComment(mixed $value): ?Comment
    {
        return $this->resolveAssociation($value, Comment::class);
    }

    private function resolveAssociation(mixed $value, string $class): ?object
    {
        if ($value instanceof $class) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $uuid = $value instanceof UuidInterface ? $value : Uuid::fromString((string) $value);
        $manager = $this->managerRegistry->getManagerForClass($class);

        return $manager?->getReference($class, $uuid);
    }
}
