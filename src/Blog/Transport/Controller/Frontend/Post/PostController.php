<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Post;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Media;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\General\Domain\Utils\JSON;
use Closure;
use Doctrine\ORM\Exception\NotSupported;
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
        $cacheKey = 'public_post_' . $slug;
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
            $item->expiresAfter(31536000);

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
     * @throws InvalidArgumentException
     * @return array
     */
    private function getFormattedPost(string $slug): array
    {
        $post = $this->getPost($slug);
        $postData = [];
        if($post) {
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
        }

        return $postData;
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
