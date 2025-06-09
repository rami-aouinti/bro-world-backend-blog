<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Application\ApiProxy\UserProxy;
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
use Symfony\Component\HttpKernel\Attribute\Cache;
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
readonly class PostsController
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
     * @param Request $request
     *
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @return JsonResponse
     */
    #[Route(path: '/public/post', name: 'public_post_index', methods: [Request::METHOD_GET])]
    #[Cache(smaxage: 10)]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 5);
        $offset = ($page - 1) * $limit;
        $cacheKey = 'post_public';

        $blogs = $this->cache->get($cacheKey, fn (ItemInterface $item) => $this->getClosure($limit, $offset)($item));
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
     * @param $limit
     * @param $offset
     *
     * @return Closure
     */
    private function getClosure($limit, $offset): Closure
    {
        return function (ItemInterface $item) use($limit, $offset): array {
            $item->expiresAfter(3600);

            return $this->getFormattedPosts($limit, $offset);
        };
    }

    /**
     * @param $limit
     * @param $offset
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws NotSupported
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    private function getFormattedPosts($limit, $offset): array
    {
        $users = $this->userProxy->getUsers();

        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user['id']] = $user;
        }

        $posts = $this->getPosts($limit, $offset);
        $output = [];

        foreach ($posts as $post) {
            $authorId = $post->getAuthor()->toString();

            $postData = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'summary' => $post->getSummary(),
                'content' => $post->getContent(),
                'slug' => $post->getSlug(),
                'tags' => $post->getTags(),
                'medias' =>  $this->getMedia($post->getMedias()),
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

            $output[] = $postData;
        }

        return $output;
    }

    /**
     * @param array|null $mediaIds
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    private function getMedia(?array $mediaIds): array
    {
        $medias  = [];
        foreach ($mediaIds as $id) {
            $medias[] = $this->userProxy->getMedia($id);
        }
        return $medias;
    }


    /**
     * @param $limit
     * @param $offset
     *
     * @throws NotSupported
     * @return array
     */
    private function getPosts($limit, $offset): array
    {
        return $this->postRepository->findBy([], ['publishedAt' => 'DESC'], $limit, $offset);
    }
}
