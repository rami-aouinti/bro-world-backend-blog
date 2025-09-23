<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Entity;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class ReactionTest extends TestCase
{
    #[TestDox('It stores reaction information for posts and comments')]
    public function testReactionAccessors(): void
    {
        $reaction = new Reaction();
        $user = Uuid::uuid4();
        $post = new Post();
        $post->setAuthor(Uuid::uuid4());
        $post->setTitle('Post title');
        $comment = new Comment();
        $comment->setAuthor(Uuid::uuid4());
        $comment->setContent('Comment body');

        $reaction->setUser($user);
        $reaction->setType('like');
        $reaction->setPost($post);
        $reaction->setComment($comment);

        self::assertSame($user, $reaction->getUser());
        self::assertSame('like', $reaction->getType());
        self::assertSame($post, $reaction->getPost());
        self::assertSame($comment, $reaction->getComment());
        self::assertNotEmpty((string)$reaction);
    }
}
