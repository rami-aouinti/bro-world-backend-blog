<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Entity;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Entity\Post;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class CommentTest extends TestCase
{
    #[TestDox('It validates spam detection and manages relations')]
    public function testCommentLifecycle(): void
    {
        $comment = new Comment();
        $author = Uuid::uuid4();
        $post = new Post();
        $post->setAuthor(Uuid::uuid4());
        $post->setTitle('Parent post');

        $publishedAt = new DateTimeImmutable('-2 hours');
        $comment->setAuthor($author);
        $comment->setContent('Great article!');
        $comment->setPost($post);
        $comment->setPublishedAt($publishedAt);
        $comment->setMedias(['image.png']);

        self::assertSame($author, $comment->getAuthor());
        self::assertSame('Great article!', $comment->getContent());
        self::assertSame($post, $comment->getPost());
        self::assertSame($publishedAt, $comment->getPublishedAt());
        self::assertSame(['image.png'], $comment->getMedias());
        self::assertTrue($comment->isLegitComment());
        self::assertSame('Great article!', (string)$comment);

        $comment->setContent('contact me spam@example.com');
        self::assertFalse($comment->isLegitComment());
        $comment->setContent('Back to normal');

        $child = new Comment();
        $child->setAuthor(Uuid::uuid4());
        $child->setContent('reply');
        $comment->addChildren($child);
        self::assertSame($comment, $child->getParent());
        self::assertCount(1, $comment->getChildren());

        $comment->removeChildren($child);
        self::assertCount(0, $comment->getChildren());
        self::assertNull($child->getParent());

        $like = new Like();
        $like->setUser(Uuid::uuid4());
        $comment->addLike($like);
        self::assertCount(1, $comment->getLikes());
        $comment->removeLike($like);
        self::assertCount(0, $comment->getLikes());

        $comment->setMedias(null);
        self::assertSame([], $comment->getMedias());

        self::assertInstanceOf(Collection::class, $comment->getLikes());
        self::assertInstanceOf(Collection::class, $comment->getReactions());
    }

    #[TestDox('It safely handles comments without content')]
    public function testCommentWithoutContentIsSafe(): void
    {
        $comment = new Comment();

        self::assertSame('', (string)$comment);
        self::assertTrue($comment->isLegitComment());

        $comment->setContent('');

        self::assertSame('', (string)$comment);
        self::assertTrue($comment->isLegitComment());
    }
}
