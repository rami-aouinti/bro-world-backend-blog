<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Application\Resource;

use App\Blog\Application\DTO\Post\PostCreate;
use App\Blog\Application\DTO\Post\PostPatch;
use App\Blog\Application\Resource\PostResource;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sprintf;
use function str_repeat;

class PostResourceTest extends KernelTestCase
{
    private PostResource $postResource;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $container = self::getContainer();
        $this->postResource = $container->get(PostResource::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    #[TestDox('DTO data is persisted when creating a post.')]
    public function testCreateMapsDtoToEntity(): void
    {
        $blog = $this->createBlog('Integration blog create');
        $tagA = $this->createTag('integration-tag-a');
        $tagB = $this->createTag('integration-tag-b');

        $dto = (new PostCreate())
            ->setTitle('Integration post title')
            ->setUrl('https://example.com/integration-post')
            ->setSummary('Integration summary for post resource create test')
            ->setContent(str_repeat('content ', 5))
            ->setAuthor(Uuid::uuid4())
            ->setBlog($blog)
            ->setTags([$tagA, $tagB])
            ->setPublishedAt('2024-01-01T10:00:00+00:00');

        $post = $this->postResource->create($dto);

        self::assertInstanceOf(Post::class, $post);
        self::assertSame('Integration post title', $post->getTitle());
        self::assertSame('https://example.com/integration-post', $post->getUrl());
        self::assertSame('Integration summary for post resource create test', $post->getSummary());
        self::assertSame(str_repeat('content ', 5), $post->getContent());
        self::assertTrue($post->getTags()->contains($tagA));
        self::assertTrue($post->getTags()->contains($tagB));
        self::assertSame($blog->getId(), $post->getBlog()?->getId());
    }

    #[TestDox('Visited fields on the DTO patch update the existing post.')]
    public function testPatchUpdatesOnlyVisitedFields(): void
    {
        $blog = $this->createBlog('Integration blog patch');
        $initialTag = $this->createTag('integration-tag-initial');

        $createDto = (new PostCreate())
            ->setTitle('Patch me please')
            ->setUrl('https://example.com/patch-me')
            ->setSummary('Initial summary before patching')
            ->setContent(str_repeat('initial ', 5))
            ->setAuthor(Uuid::uuid4())
            ->setBlog($blog)
            ->setTags([$initialTag])
            ->setPublishedAt('2024-02-02T12:00:00+00:00');

        $post = $this->postResource->create($createDto);

        $newTag = $this->createTag('integration-tag-updated');

        $patchDto = (new PostPatch())
            ->setSummary('Updated summary from patch test')
            ->setContent(str_repeat('patched ', 4))
            ->setTags([$newTag]);

        $updated = $this->postResource->patch($post->getId(), $patchDto);

        self::assertSame('Patch me please', $updated->getTitle());
        self::assertSame('Updated summary from patch test', $updated->getSummary());
        self::assertSame(str_repeat('patched ', 4), $updated->getContent());
        self::assertTrue($updated->getTags()->contains($newTag));
        self::assertFalse($updated->getTags()->contains($initialTag));
    }

    private function createBlog(string $title): Blog
    {
        $blog = new Blog();
        $blog->setTitle($title);
        $blog->setAuthor(Uuid::uuid4());
        $blog->setBlogSubtitle(sprintf('%s subtitle', $title));

        $this->entityManager->persist($blog);
        $this->entityManager->flush();

        return $blog;
    }

    private function createTag(string $name): Tag
    {
        $tag = new Tag($name);
        $tag->setDescription(sprintf('%s description', $name));

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $tag;
    }
}
