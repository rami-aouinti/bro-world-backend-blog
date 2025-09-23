<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Post;

use App\Blog\Application\Resource\BlogResource;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Tag;
use App\Blog\Domain\Repository\Interfaces\TagRepositoryInterface;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Transport\AutoMapper\RestRequestMapper;
use DateTimeImmutable;
use Override;
use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;

/**
 * @package App\Post
 */
class RequestMapper extends RestRequestMapper
{
    /**
     * @var array<int, non-empty-string>
     */
    protected static array $properties = [
        'title',
        'url',
        'summary',
        'content',
        'author',
        'blog',
        'tags',
        'mediaIds',
        'publishedAt',
    ];

    public function __construct(
        private readonly BlogResource $blogResource,
        private readonly TagRepositoryInterface $tagRepository,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function mapToObject($source, $destination, array $context = []): RestDtoInterface
    {
        if ($source instanceof Request) {
            $this->normalizeAliases($source);
        }

        return parent::mapToObject($source, $destination, $context);
    }

    private function normalizeAliases(Request $request): void
    {
        if ($request->request->has('authorId')) {
            $request->request->set('author', $request->request->get('authorId'));
        }

        if ($request->request->has('blogId')) {
            $request->request->set('blog', $request->request->get('blogId'));
        }

        if ($request->request->has('tagIds')) {
            $request->request->set('tags', $request->request->all('tagIds'));
        }
    }

    /**
     * @param mixed $value
     */
    protected function transformBlog(mixed $value): ?Blog
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Blog) {
            return $value;
        }

        return $this->blogResource->getReference((string)$value);
    }

    /**
     * @param mixed $value
     *
     * @return array<int, Tag>
     */
    protected function transformTags(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        $tags = [];

        foreach ($value as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }

            $tag = $this->tagRepository->find($id);

            if ($tag instanceof Tag) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @param mixed $value
     */
    protected function transformPublishedAt(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string)$value);
    }
}
