<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Entity;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Entity\Media;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Tag;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class PostTest extends TestCase
{
    #[TestDox('It manages scalar properties, relations and computed payloads')]
    public function testPostBehaviour(): void
    {
        $post = new Post();
        $author = Uuid::uuid4();
        $blog = (new Blog())
            ->setTitle('Blog title')
            ->setSlug('blog-title')
            ->setAuthor($author);

        $publishedAt = new DateTimeImmutable('-1 day');
        $post
            ->setTitle('Hello world')
            ->setUrl('https://example.test/post')
            ->setSummary('Summary text')
            ->setContent('Post content')
            ->setSlug('hello-world')
            ->setAuthor($author)
            ->setBlog($blog)
            ->setPublishedAt($publishedAt);

        self::assertSame('Hello world', $post->getTitle());
        self::assertSame('https://example.test/post', $post->getUrl());
        self::assertSame('Summary text', $post->getSummary());
        self::assertSame('Post content', $post->getContent());
        self::assertSame('hello-world', $post->getSlug());
        self::assertSame($blog, $post->getBlog());
        self::assertSame($author, $post->getAuthor());
        self::assertSame($publishedAt, $post->getPublishedAt());
        self::assertSame('Hello world', (string)$post);

        self::assertInstanceOf(Collection::class, $post->getComments());
        self::assertInstanceOf(Collection::class, $post->getTags());
        self::assertInstanceOf(Collection::class, $post->getMediaEntities());
        self::assertInstanceOf(Collection::class, $post->getLikes());
        self::assertInstanceOf(Collection::class, $post->getReactions());
        self::assertInstanceOf(Collection::class, $post->getSharedBy());
        self::assertCount(0, $post->getComments());

        $comment = new Comment();
        $comment->setAuthor(Uuid::uuid4());
        $comment->setContent('Nice article');
        $post->addComment($comment);
        self::assertCount(1, $post->getComments());
        self::assertSame($post, $comment->getPost());

        $post->removeComment($comment);
        self::assertCount(0, $post->getComments());

        $tag = new Tag('Testing');
        $post->addTag($tag, $tag);
        self::assertCount(1, $post->getTags());
        $post->removeTag($tag);
        self::assertCount(0, $post->getTags());

        $media = (new Media())
            ->setUrl('/images/picture.png')
            ->setType('image/png');
        $post->addMedia($media);
        self::assertCount(1, $post->getMediaEntities());
        self::assertSame($post, $media->getPost());
        $post->removeMedia($media);
        self::assertCount(0, $post->getMediaEntities());
        self::assertNull($media->getPost());

        $like = new Like();
        $like->setUser(Uuid::uuid4());
        $post->addLike($like);
        self::assertCount(1, $post->getLikes());
        $post->removeLike($like);
        self::assertCount(0, $post->getLikes());

        $sharedFrom = new Post();
        $sharedFrom->setAuthor(Uuid::uuid4());
        $sharedFrom->setTitle('Source post');
        $post->setSharedFrom($sharedFrom);
        self::assertSame($sharedFrom, $post->getSharedFrom());

        $payload = $post->toArray();
        self::assertSame($post->getId(), $payload['id']);
        self::assertSame('Hello world', $payload['title']);
        self::assertSame($author->toString(), $payload['author']);
        self::assertIsArray($payload['comments']);
        self::assertIsArray($payload['tags']);
        self::assertIsArray($payload['medias']);
        self::assertIsArray($payload['likes']);
        self::assertIsArray($payload['reactions']);
    }
}
