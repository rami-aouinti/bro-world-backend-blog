<?php

declare(strict_types=1);

namespace App\Tests\Integration\Blog\Transport\Controller\Frontend;

use App\Blog\Application\Service\UserCacheService;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use App\Blog\Infrastructure\DataFixtures\ORM\LoadBlogData;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use function array_map;
use function array_unique;
use function json_decode;
use function sprintf;
use function substr;

use const JSON_THROW_ON_ERROR;

final class PostsCacheInvalidationTest extends WebTestCase
{
    /**
     * @throws JsonException
     */
    public function testPostsFeedIsInvalidatedWhenBlogContentChanges(): void
    {
        $client = static::createClient();

        /** @var TagAwareCacheInterface $cache */
        $cache = static::getContainer()->get(TagAwareCacheInterface::class);
        $cache->clear();

        /** @var UserCacheService $userCacheService */
        $userCacheService = static::getContainer()->get(UserCacheService::class);
        foreach ($this->getKnownUserIds() as $userId) {
            $userCacheService->save($userId, [
                'id' => $userId,
                'username' => sprintf('user-%s', substr($userId, -4)),
                'email' => sprintf('%s@example.test', $userId),
            ]);
        }

        $client->request('GET', '/public/post', [
            'limit' => 5,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $initialPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($initialPayload['data']);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $blog = $entityManager->getRepository(Blog::class)->findOneBy([
            'title' => 'public',
        ]);
        self::assertNotNull($blog);

        $post = new Post();
        $post->setTitle('Cache invalidation test post');
        $post->setSummary('Fresh summary for cache invalidation');
        $post->setContent('Fresh content for cache invalidation.');
        $post->setUrl('https://example.test/cache-invalidation');
        $post->setBlog($blog);
        $post->setAuthor(Uuid::fromString('20000000-0000-1000-8000-000000000001'));

        $entityManager->persist($post);
        $entityManager->flush();

        $client->request('GET', '/public/post', [
            'limit' => 5,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $afterPostPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($afterPostPayload['data']);
        $postIds = array_map(static fn (array $row) => $row['id'], $afterPostPayload['data']);
        self::assertContains($post->getId(), $postIds);

        $newPostData = $afterPostPayload['data'][0];
        self::assertSame($post->getId(), $newPostData['id']);

        $initialComments = $newPostData['totalComments'];
        $initialReactions = $newPostData['reactions_count'];

        $comment = new Comment();
        $comment->setContent('Cache invalidation comment');
        $comment->setAuthor(Uuid::fromString('20000000-0000-1000-8000-000000000002'));
        $comment->setPost($post);

        $entityManager->persist($comment);
        $entityManager->flush();

        $client->request('GET', '/public/post', [
            'limit' => 5,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $afterCommentPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($afterCommentPayload['data']);
        $postDataWithComment = $afterCommentPayload['data'][0];
        self::assertSame($post->getId(), $postDataWithComment['id']);
        self::assertSame($initialComments + 1, $postDataWithComment['totalComments']);

        $reaction = new Reaction();
        $reaction->setPost($post);
        $reaction->setUser(Uuid::fromString('20000000-0000-1000-8000-000000000003'));
        $reaction->setType('like');

        $entityManager->persist($reaction);
        $entityManager->flush();

        $client->request('GET', '/public/post', [
            'limit' => 5,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $afterReactionPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($afterReactionPayload['data']);
        $postDataWithReaction = $afterReactionPayload['data'][0];
        self::assertSame($post->getId(), $postDataWithReaction['id']);
        self::assertSame($postDataWithComment['totalComments'], $postDataWithReaction['totalComments']);
        self::assertSame($initialReactions + 1, $postDataWithReaction['reactions_count']);
    }

    /**
     * @return string[]
     */
    private function getKnownUserIds(): array
    {
        return array_unique([
            '20000000-0000-1000-8000-000000000001',
            '20000000-0000-1000-8000-000000000002',
            '20000000-0000-1000-8000-000000000003',
            '20000000-0000-1000-8000-000000000004',
            '20000000-0000-1000-8000-000000000005',
            '20000000-0000-1000-8000-000000000006',
            ...LoadBlogData::$uuids,
        ]);
    }
}
