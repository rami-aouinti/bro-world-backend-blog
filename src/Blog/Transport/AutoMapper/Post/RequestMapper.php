<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Post;

use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Transport\AutoMapper\RestRequestMapper;
use Override;
use Symfony\Component\HttpFoundation\Request;

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
}
