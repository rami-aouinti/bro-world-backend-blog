<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Entity;

use App\Blog\Domain\Entity\Media;
use App\Blog\Domain\Entity\Post;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class MediaTest extends TestCase
{
    #[TestDox('It serializes to a lightweight payload and keeps owning post')]
    public function testMediaAccessors(): void
    {
        $media = new Media();
        $post = new Post();
        $post->setAuthor(Uuid::uuid4());
        $post->setTitle('Post title');

        $media
            ->setUrl('/uploads/image.jpg')
            ->setType('image/jpeg')
            ->setPost($post);

        self::assertSame('/uploads/image.jpg', $media->getUrl());
        self::assertSame('image/jpeg', $media->getType());
        self::assertSame($post, $media->getPost());
        self::assertNotEmpty($media->getId());

        $payload = $media->toArray();
        self::assertSame($media->getId(), $payload['id']);
        self::assertSame('image/jpeg', $payload['type']);
        self::assertSame('/uploads/image.jpg', $payload['path']);
    }
}
