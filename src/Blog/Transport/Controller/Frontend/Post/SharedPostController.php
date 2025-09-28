<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Post;

use App\Blog\Domain\Entity\Post;
use App\Blog\Infrastructure\Repository\PostRepository;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @package App\Blog\Transport\Controller\Frontend\Post
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class SharedPostController
{
    public function __construct(
        private PostRepository $postRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    #[Route(path: '/v1/platform/post/{post}/shared', name: 'shared_post', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post): JsonResponse
    {
        $data = $request->request->all();
        $newPost = new Post();
        $newPost->setAuthor(Uuid::fromString($symfonyUser->getUserIdentifier()));
        $newPost->setSharedFrom($post);
        if ($data['content'] ?? null) {
            $newPost->setTitle($data['content']);
        }

        $this->postRepository->save($newPost);

        return new JsonResponse($newPost);
    }
}
