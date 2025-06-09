<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\General\Domain\Utils\JSON;
use Closure;
use Doctrine\ORM\Exception\NotSupported;
use Exception;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class PostController
{
    public function __construct(
        private SerializerInterface $serializer,
        private CacheInterface $cache,
        private PostRepositoryInterface $postRepository,
        private UserProxy $userProxy
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users
     *
     * @param string $slug
     *
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @return JsonResponse
     */
    #[Route(path: '/public/post/{slug}', name: 'public_post_slug', methods: [Request::METHOD_GET])]
    public function __invoke(string $slug): JsonResponse
    {
        $cacheKey = 'public_post_slug_' . $slug;
        $blogs = $this->cache->get($cacheKey, fn (ItemInterface $item) => $this->getClosure($slug)($item));
        $output = JSON::decode(
            $this->serializer->serialize(
                $blogs,
                'json',
                [
                    'groups' => 'Post',
                ]
            ),
            true,
        );
        return new JsonResponse($output);
    }

    /**
     *
     * @param string $slug
     *
     * @return Closure
     */
    private function getClosure(string $slug): Closure
    {
        return function (ItemInterface $item) use ($slug): array {
            $item->expiresAfter(3600);

            return $this->getFormattedPost($slug);
        };
    }

    /**
     * @param string $slug
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws NotSupported
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    private function getFormattedPost(string $slug): array
    {
        $users = $this->userProxy->getUsers();

        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user['id']] = $user;
        }

        $post = $this->getPost($slug);
        $postData = [];
        if($post) {
            $authorId = $post->getAuthor()->toString();
            $postData = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'summary' => $post->getSummary(),
                'content' => $post->getContent(),
                'slug' => $post->getSlug(),
                'tags' => $post->getTags(),
                'medias' => $post->getMedias(),
                'likes' => $post->getLikes(),
                'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
                'blog' => [
                    'title' => $post->getBlog()?->getTitle(),
                    'blogSubtitle' => $post->getBlog()?->getBlogSubtitle(),
                ],
                'user' => $usersById[$authorId] ?? null,
                'comments' => [],
            ];
            foreach ($post->getComments() as $comment) {
                $commentAuthorId = $comment->getAuthor()->toString();

                $postData['comments'][] = [
                    'id' => $comment->getId(),
                    'content' => $comment->getContent(),
                    'publishedAt' => $comment->getPublishedAt()?->format(DATE_ATOM),
                    'user' => $usersById[$commentAuthorId] ?? null,
                ];
            }
        }

        return $postData;
    }


    /**
     * @param $slug
     *
     * @throws NotSupported
     * @return Post|null
     */
    private function getPost($slug): Post|null
    {
        return $this->postRepository->findOneBy([
            'slug' => $slug
        ]);
    }
}
