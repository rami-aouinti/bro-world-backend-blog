<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Blog;

use App\Blog\Application\Service\BlogService;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class CreateBlogController
{
    public function __construct(
        private BlogService $blogService,
        private BlogRepositoryInterface $blogRepository,
        private CacheItemPoolInterface $cache
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws InvalidArgumentException
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/blog', name: 'blog_create', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request): JsonResponse
    {
        $this->cache->deleteItem("profile_blog_{$symfonyUser->getUserIdentifier()}");
        $blog = new Blog();
        if($request->files->get('files')) {
            $logo = $this->blogService->uploadLogo($request);
            $blog->setLogo($logo);
        }
        $data = $request->request->all();

        $blog->setTitle($data['title']);
        $blog->setBlogSubtitle($data['description'] ?? '');
        $blog->setAuthor(Uuid::fromString($symfonyUser->getUserIdentifier()));

        $this->blogRepository->save($blog);

        $output['title'] = $blog->getTitle();
        $output['description'] = $blog->getBlogSubtitle();
        $output['slug'] = $blog->getSlug();
        $output['logo'] = $blog->getLogo();
        return new JsonResponse(
            $output
        );
    }
}
