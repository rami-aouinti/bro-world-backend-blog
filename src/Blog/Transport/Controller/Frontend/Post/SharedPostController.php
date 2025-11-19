<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Post;

use App\Blog\Application\Service\Post\PostShareService;
use App\Blog\Domain\Entity\Post;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @package App\Blog\Transport\Controller\Frontend\Post
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class SharedPostController
{
    public function __construct(
        private PostShareService $postShareService
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @throws ExceptionInterface
     */
    #[Route(path: '/v1/platform/post/{post}/shared', name: 'shared_post', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post): JsonResponse
    {
        $content = $request->request->get('content');
        $sharedPost = $this->postShareService->share(
            $post,
            $symfonyUser,
            is_string($content) ? $content : null
        );

        return new JsonResponse($sharedPost->toArray());
    }
}
