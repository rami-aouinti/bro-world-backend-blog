<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Post;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\Post\PostService;
use App\Blog\Domain\Entity\Media;
use App\Blog\Domain\Entity\Post;
use App\Blog\Infrastructure\Repository\PostRepository;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Random\RandomException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

use function array_key_exists;
use function strlen;
use function trim;

/**
 * @package App\Blog\Transport\Controller\Frontend\Post
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class EditPostController
{
    public function __construct(
        private PostService $postService,
        private UserProxy $userProxy,
        private PostRepository $postRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @throws InvalidArgumentException
     * @throws RandomException
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws Throwable
     */
    #[Route(path: '/v1/platform/post/{post}', name: 'edit_post', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post): JsonResponse
    {
        $data = $request->request->all();

        if (array_key_exists('title', $data)) {
            $title = $data['title'];
            $post->setTitle($title);

            if (array_key_exists('slug', $data)) {
                $post->setSlug($data['slug']);
            } elseif (trim((string)$title) !== '') {
                $post->setSlug(null);
            }
        }
        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }
        if (isset($data['url'])) {
            $post->setUrl($data['url']);
        }

        $data = $request->files->all() ? $this->postService->uploadFiles($request, $post) : $post;
        $this->postRepository->save($post);

        $newPost = array_merge(
            $data->toArray(),
            [
                'medias' => $post->getMediaEntities()->map(
                    fn (Media $media) => $media->toArray()
                )->toArray(),
                'user' => $this->userProxy->searchUser($symfonyUser->getId()),
            ]
        );

        return new JsonResponse($newPost);
    }

    /**
     * @throws RandomException
     */
    private function generateRandomString(int $length): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}
