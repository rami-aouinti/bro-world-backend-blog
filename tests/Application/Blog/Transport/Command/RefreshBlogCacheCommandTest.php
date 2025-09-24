<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Command;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\Tests\TestCase\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use function array_reduce;
use function sprintf;

final class RefreshBlogCacheCommandTest extends WebTestCase
{
    public function testCommandWarmsCachesForAllScopes(): void
    {
        static::ensureKernelShutdown();
        static::createClient();
        $container = static::getContainer();
        $this->overrideUserProxy($container);

        /** @var TagAwareCacheInterface $cache */
        $cache = $container->get(TagAwareCacheInterface::class);
        $cache->clear();

        $application = new Application(static::$kernel);
        $command = $application->find('blog:cache:refresh');
        $tester = new CommandTester($command);
        $tester->execute([
            '--scope' => 'all',
        ]);
        $tester->assertCommandIsSuccessful();

        /** @var PostRepositoryInterface $postRepository */
        $postRepository = $container->get(PostRepositoryInterface::class);
        $post = $postRepository->findOneBy([]);
        self::assertNotNull($post, 'Expected at least one post to exist.');

        $postId = $post->getId();

        $postFeedItem = $cache->getItem(sprintf('posts_page_%d_limit_%d', 1, 10));
        self::assertTrue($postFeedItem->isHit());
        self::assertSameCanonicalizing(['posts', 'comments', 'likes', 'reactions'], $postFeedItem->getMetadata()['tags'] ?? []);

        $postCommentsItem = $cache->getItem(sprintf('post_comments_%s_page_%d_limit_%d', $postId, 1, 10));
        self::assertTrue($postCommentsItem->isHit());
        self::assertSameCanonicalizing(['posts', 'comments'], $postCommentsItem->getMetadata()['tags'] ?? []);

        $postLikesItem = $cache->getItem(sprintf('post_likes_%s', $postId));
        self::assertTrue($postLikesItem->isHit());
        self::assertSameCanonicalizing(['posts', 'likes'], $postLikesItem->getMetadata()['tags'] ?? []);

        $postReactionsItem = $cache->getItem(sprintf('post_reactions_%s', $postId));
        self::assertTrue($postReactionsItem->isHit());
        self::assertSameCanonicalizing(['posts', 'reactions'], $postReactionsItem->getMetadata()['tags'] ?? []);

        $commentsPayload = $postCommentsItem->get();
        self::assertIsArray($commentsPayload);
        self::assertArrayHasKey('comments', $commentsPayload);
        self::assertNotEmpty($commentsPayload['comments']);

        $commentId = $this->extractFirstCommentId($commentsPayload['comments']);
        self::assertNotNull($commentId, 'Expected to find at least one comment identifier.');

        $commentLikesItem = $cache->getItem(sprintf('comment_likes_%s', $commentId));
        self::assertTrue($commentLikesItem->isHit());
        self::assertSameCanonicalizing(['comments', 'likes'], $commentLikesItem->getMetadata()['tags'] ?? []);

        $commentReactionsItem = $cache->getItem(sprintf('comment_reactions_%s', $commentId));
        self::assertTrue($commentReactionsItem->isHit());
        self::assertSameCanonicalizing(['comments', 'reactions'], $commentReactionsItem->getMetadata()['tags'] ?? []);
    }

    /**
     * @param array<int, array<string, mixed>> $comments
     */
    private function extractFirstCommentId(array $comments): ?string
    {
        foreach ($comments as $comment) {
            if (isset($comment['id'])) {
                return (string)$comment['id'];
            }

            if (!empty($comment['children'])) {
                $childId = $this->extractFirstCommentId($comment['children']);
                if ($childId !== null) {
                    return $childId;
                }
            }
        }

        return null;
    }

    private function overrideUserProxy(ContainerInterface $container): void
    {
        $userProxy = $this->createMock(UserProxy::class);

        $userProxy->method('batchSearchUsers')->willReturnCallback(
            static fn (array $ids): array => array_reduce(
                $ids,
                static function (array $carry, string $id): array {
                    $carry[$id] = [
                        'id' => $id,
                        'username' => sprintf('user_%s', $id),
                    ];

                    return $carry;
                },
                [],
            ),
        );

        $userProxy->method('searchUser')->willReturnCallback(
            static fn (string $id): array => [
                'id' => $id,
                'username' => sprintf('user_%s', $id),
            ],
        );

        $userProxy->method('getUsers')->willReturn([]);
        $userProxy->method('getMedia')->willReturnCallback(
            static fn (string $id): array => [
                'id' => $id,
                'url' => sprintf('https://media.example/%s', $id),
            ],
        );

        $container->set(UserProxy::class, $userProxy);
        $container->set('App\\Blog\\Application\\ApiProxy\\UserProxy', $userProxy);
    }
}
