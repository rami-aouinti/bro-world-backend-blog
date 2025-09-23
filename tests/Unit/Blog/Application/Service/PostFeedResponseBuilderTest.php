<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\PostFeedResponseBuilder;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

class PostFeedResponseBuilderTest extends TestCase
{
    public function testBuildComputesIsReactedWhenCurrentUserProvided(): void
    {
        $userProxy = $this->createMock(UserProxy::class);
        $builder = new PostFeedResponseBuilder($userProxy);

        $currentUserId = 'current-user';

        $postReaction = $this->createMock(Reaction::class);
        $postReaction->method('getId')->willReturn('post-reaction-1');
        $postReaction->method('getType')->willReturn('like');
        $postReaction->method('getUser')->willReturn($this->createUuidMock($currentUserId));

        $otherReaction = $this->createMock(Reaction::class);
        $otherReaction->method('getId')->willReturn('post-reaction-2');
        $otherReaction->method('getType')->willReturn('love');
        $otherReaction->method('getUser')->willReturn($this->createUuidMock('other-user'));

        $commentReaction = $this->createMock(Reaction::class);
        $commentReaction->method('getId')->willReturn('comment-reaction');
        $commentReaction->method('getType')->willReturn('wow');
        $commentReaction->method('getUser')->willReturn($this->createUuidMock($currentUserId));

        $comment = $this->createMock(Comment::class);
        $comment->method('getId')->willReturn('comment-1');
        $comment->method('getContent')->willReturn('Comment');
        $comment->method('getAuthor')->willReturn($this->createUuidMock('comment-author'));
        $comment->method('getReactions')->willReturn(new ArrayCollection([$commentReaction]));
        $comment->method('getChildren')->willReturn(new ArrayCollection());
        $comment->method('getLikes')->willReturn(new ArrayCollection());
        $comment->method('getPublishedAt')->willReturn(new DateTimeImmutable('2024-01-01T00:00:00+00:00'));

        $post = $this->createMock(Post::class);
        $post->method('getId')->willReturn('post-1');
        $post->method('getTitle')->willReturn('Title');
        $post->method('getSummary')->willReturn('Summary');
        $post->method('getContent')->willReturn('Content');
        $post->method('getUrl')->willReturn('https://example.com');
        $post->method('getSlug')->willReturn('title');
        $post->method('getMediaEntities')->willReturn(new ArrayCollection());
        $post->method('getReactions')->willReturn(new ArrayCollection([$postReaction, $otherReaction]));
        $post->method('getComments')->willReturn(new ArrayCollection([$comment]));
        $post->method('getAuthor')->willReturn($this->createUuidMock('post-author'));
        $post->method('getPublishedAt')->willReturn(new DateTimeImmutable('2024-01-01T00:00:00+00:00'));
        $post->method('getSharedFrom')->willReturn(null);
        $post->method('getLikes')->willReturn(new ArrayCollection());

        $userProxy->expects($this->once())
            ->method('batchSearchUsers')
            ->with($this->callback(function (array $ids) use ($currentUserId) {
                sort($ids);
                $this->assertSame([
                    'comment-author',
                    'current-user',
                    'other-user',
                    'post-author',
                ], $ids);

                return true;
            }))
            ->willReturn([
                'post-author' => ['id' => 'post-author'],
                'comment-author' => ['id' => 'comment-author'],
                'current-user' => ['id' => 'current-user'],
                'other-user' => ['id' => 'other-user'],
            ]);

        $result = $builder->build([$post], 1, 10, 1, $currentUserId);

        $this->assertSame('like', $result['data'][0]['isReacted']);
        $this->assertSame('wow', $result['data'][0]['comments_preview'][0]['isReacted']);
    }

    public function testBuildKeepsIsReactedNullWithoutCurrentUser(): void
    {
        $userProxy = $this->createMock(UserProxy::class);
        $builder = new PostFeedResponseBuilder($userProxy);

        $reaction = $this->createMock(Reaction::class);
        $reaction->method('getId')->willReturn('reaction');
        $reaction->method('getType')->willReturn('like');
        $reaction->method('getUser')->willReturn($this->createUuidMock('other-user'));

        $post = $this->createMock(Post::class);
        $post->method('getId')->willReturn('post-1');
        $post->method('getTitle')->willReturn('Title');
        $post->method('getSummary')->willReturn('Summary');
        $post->method('getContent')->willReturn('Content');
        $post->method('getUrl')->willReturn('https://example.com');
        $post->method('getSlug')->willReturn('title');
        $post->method('getMediaEntities')->willReturn(new ArrayCollection());
        $post->method('getReactions')->willReturn(new ArrayCollection([$reaction]));
        $post->method('getComments')->willReturn(new ArrayCollection());
        $post->method('getAuthor')->willReturn($this->createUuidMock('post-author'));
        $post->method('getPublishedAt')->willReturn(new DateTimeImmutable('2024-01-01T00:00:00+00:00'));
        $post->method('getSharedFrom')->willReturn(null);
        $post->method('getLikes')->willReturn(new ArrayCollection());

        $userProxy->expects($this->once())
            ->method('batchSearchUsers')
            ->with(['post-author', 'other-user'])
            ->willReturn([
                'post-author' => ['id' => 'post-author'],
                'other-user' => ['id' => 'other-user'],
            ]);

        $result = $builder->build([$post], 1, 10, 1, null);

        $this->assertNull($result['data'][0]['isReacted']);
    }

    private function createUuidMock(string $id): UuidInterface
    {
        $uuid = $this->createMock(UuidInterface::class);
        $uuid->method('toString')->willReturn($id);

        return $uuid;
    }
}
