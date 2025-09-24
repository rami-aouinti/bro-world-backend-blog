<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Blog;

use App\Blog\Application\Service\Blog\BlogService;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use OpenApi\Attributes as OA;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Throwable;

use function array_key_exists;
use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * @package App\Blog\Transport\Controller\Frontend\Blog
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class EditBlogController
{
    public function __construct(
        private BlogService $blogService,
        private BlogRepositoryInterface $blogRepository,
        private CacheItemPoolInterface $cacheItemPool,
        private CacheInterface $cacheInterface,
    ) {
    }

    /**
     * Update an existing blog for the authenticated owner.
     *
     * @throws InvalidArgumentException
     */
    #[Route(
        path: '/v1/platform/blog/{blog}',
        name: 'blog_edit',
        methods: [Request::METHOD_PUT, Request::METHOD_PATCH]
    )]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Blog $blog): JsonResponse
    {
        if ($blog->getAuthor()->toString() !== $symfonyUser->getUserIdentifier()) {
            return new JsonResponse([
                'error' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->extractPayload($request);

        if (array_key_exists('title', $payload) && is_string($payload['title'])) {
            $blog->setTitle($payload['title']);
        }

        if (array_key_exists('description', $payload)) {
            $blog->setBlogSubtitle($this->normalizeNullableString($payload['description']));
        } elseif (array_key_exists('subtitle', $payload)) {
            $blog->setBlogSubtitle($this->normalizeNullableString($payload['subtitle']));
        }

        $files = $request->files->get('files');
        if ($files) {
            $logo = $this->blogService->executeUploadLogoCommand($request);
            if ($logo instanceof JsonResponse) {
                return $logo;
            }

            $blog->setLogo($logo);
        }

        $previousSlug = $blog->getSlug();

        $this->blogRepository->save($blog);

        $this->cacheItemPool->deleteItem('profile_blog_' . $symfonyUser->getUserIdentifier());
        $this->cacheInterface->delete('public_blog');

        if ($previousSlug !== null) {
            $this->cacheInterface->delete('private_blog_' . $previousSlug);
        }

        $newSlug = $blog->getSlug();
        if ($newSlug !== null) {
            $this->cacheInterface->delete('private_blog_' . $newSlug);
        }

        return new JsonResponse([
            'id' => $blog->getId(),
            'title' => $blog->getTitle(),
            'description' => $blog->getBlogSubtitle(),
            'slug' => $blog->getSlug(),
            'logo' => $blog->getLogo(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(Request $request): array
    {
        $data = $request->request->all();

        if ($data !== []) {
            return $data;
        }

        $content = $request->getContent();
        if ($content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) ? $value : null;
    }
}
