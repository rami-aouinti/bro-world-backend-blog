<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Entity;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Entity\Post;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class LikeTest extends TestCase
{
    #[TestDox('It stores relations to posts or comments with audit information')]
    public function testLikeAccessors(): void
    {
        $like = new Like();
        $user = Uuid::uuid4();
        $post = new Post();
        $post->setAuthor(Uuid::uuid4());
        $post->setTitle('Post title');
        $comment = new Comment();
        $comment->setAuthor(Uuid::uuid4());
        $comment->setContent('Comment text');

        $createdAt = new DateTimeImmutable('-5 minutes');

        $like->setUser($user);
        $like->setPost($post);
        $like->setComment($comment);
        $like->setCreatedAt($createdAt);

        self::assertSame($user, $like->getUser());
        self::assertSame($post, $like->getPost());
        self::assertSame($comment, $like->getComment());
        self::assertSame($createdAt, $like->getCreatedAt());
        self::assertNotEmpty((string)$like);
    }
}
