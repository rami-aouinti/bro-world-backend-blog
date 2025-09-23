<?php

declare(strict_types=1);

namespace App\Blog\Transport\MessageHandler;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\PostService;
use App\Blog\Domain\Entity\Media;
use App\Blog\Domain\Message\CreatePostMessenger;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use Doctrine\Common\Collections\Collection;
use Closure;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class CreatePostHandlerMessage
 *
 * @package App\Post\Transport\MessageHandler
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsMessageHandler]
readonly class CreatePostHandlerMessage
{
    public function __construct(
        private PostService $postService,
        private TagAwareCacheInterface $cache,
        private PostRepositoryInterface $postRepository,
        private UserProxy $userProxy
    )
    {
    }

    /**
     * @param CreatePostMessenger $message
     *
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     * @return void
     */
    public function __invoke(CreatePostMessenger $message): void
    {
        $this->handleMessage($message);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws InvalidArgumentException
     */
    private function handleMessage(CreatePostMessenger $message): void
    {
        $this->postService->savePost($message->getPost(), $message->getMediasIds());

        $this->cache->invalidateTags(['posts']);

        $cacheKey = 'all_posts_' . 1 . '_' . 10;
        $this->cache->delete($cacheKey);
        $this->cache->get($cacheKey, fn (ItemInterface $item) => $this->getClosure(10, 1)($item));
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
            $item->tag(['posts']);
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
                'medias' =>  $this->getMedia($post->getMediaEntities()),
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
     * @param Collection $mediaEntities
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    private function getMedia(Collection $mediaEntities): array
    {
        $medias  = [];
        foreach ($mediaEntities as $media) {
            if (!$media instanceof Media) {
                continue;
            }

            $medias[] = $this->userProxy->getMedia($media->getId());
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
