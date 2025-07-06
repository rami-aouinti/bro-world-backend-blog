<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Media;
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
     * @throws NotSupported
     * @return JsonResponse
     */
    #[Route(path: '/public/post', name: 'public_post_index', methods: [Request::METHOD_GET])]
    #[Cache(smaxage: 10)]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = (int)$request->query->get('limit', 10);
        $offset = ($page - 1) * $limit;
        $cacheKey = 'all_posts_' . $page . '_' . $limit;

        $posts = $this->cache->get($cacheKey, fn (ItemInterface $item) => $this->getClosure($limit, $offset)($item));
        $output = JSON::decode(
            $this->serializer->serialize(
                $posts,
                'json',
                [
                    'groups' => 'Post',
                ]
            ),
            true,
        );
        $results = [
            'data' => $output,
            'page' => $page,
            'limit' => $limit,
            'count' => count($this->postRepository->findAll()),
        ];
        return new JsonResponse($results);
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
            $item->expiresAfter(31536000);

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
     * @throws InvalidArgumentException
     * @return array
     */
    private function getFormattedPosts($limit, $offset): array
    {
        $posts = $this->getPosts($limit, $offset);
        $output = [];

        foreach ($posts as $post) {
            $authorId = $post->getAuthor()->toString();

            $postData = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'url' => $post->getUrl(),
                'summary' => $post->getSummary(),
                'content' => $post->getContent(),
                'slug' => $post->getSlug(),
                'tags' => $post->getTags(),
                'medias' =>  $post->getMediaEntities()->map(
                    fn(Media $media) => $media->toArray()
                )->toArray(),
                'likes' => [],
                'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
                'blog' => [
                    'title' => $post->getBlog()?->getTitle(),
                    'blogSubtitle' => $post->getBlog()?->getBlogSubtitle(),
                ],
                'user' => $this->userProxy->searchUser($authorId),
                'comments' => [],
            ];

            foreach ($post->getLikes() as $key => $like) {
                $postData['likes'][$key]['id'] = $like->getId();
                $postData['likes'][$key]['user']  = $this->userProxy->searchUser($like->getUser()->toString());
            }

            $rootComments = array_filter(
                $post->getComments()->toArray(),
                static fn($comment) => $comment->getParent() === null
            );

            foreach ($rootComments as $comment) {
                $postData['comments'][] = $this->formatCommentRecursively($comment);
            }

            $output[] = $postData;
        }
        return $output;
    }

    /**
     * @param       $comment
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    private function formatCommentRecursively($comment): array
    {
        $authorId = $comment->getAuthor()->toString();

        $formatted = [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'likes' => [],
            'publishedAt' => $comment->getPublishedAt()?->format(DATE_ATOM),
            'user' => $this->userProxy->searchUser($authorId),
            'children' => [],
        ];
        foreach ($comment->getLikes() as $key => $like) {
            $formatted['likes'][$key]['id'] = $like->getId();

            $formatted['likes'][$key]['user']  = $this->userProxy->searchUser($like->getUser()->toString());
        }
        foreach ($comment->getChildren() as $child) {
            $formatted['children'][] = $this->formatCommentRecursively($child);
        }

        return $formatted;
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
