<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\MediaService;
use App\Blog\Domain\Entity\Blog;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use JsonException;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class CreateBlogController
{
    public function __construct(
        private MediaService $mediaService,
        private SerializerInterface $serializer,
        private UserProxy $userProxy
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     *
     * @throws JsonException
     * @throws Throwable
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/blog', name: 'blog_create', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request): JsonResponse
    {
        $medias = $request->files->all() ? $this->mediaService->createMedia($request, 'media') : [];

        $data = $request->request->all();
        $blog = new Blog();
        $blog->setTitle($data['title']);
        $blog->setBlogSubtitle($data['description'] ?? '');
        $blog->setSlug($data['title']);
        $blog->setAuthor(Uuid::fromString($symfonyUser->getUserIdentifier()));
        if (!empty($medias)) {
            $blog->setLogo($medias[0]);
        }
        $output = JSON::decode(
            $this->serializer->serialize(
                $blog,
                'json',
                [
                    'groups' => 'Blog',
                ]
            ),
            true,
        );
        $output['logo'] = $this->userProxy->getMedia($blog->getLogo());
        return new JsonResponse(
            $output
        );
    }
}
