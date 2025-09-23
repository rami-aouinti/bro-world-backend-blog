<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Media;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use Doctrine\Common\Collections\Collection;

use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function array_map;
use function array_slice;
use function array_unique;
use function count;

/**
 * Class PostFeedResponseBuilder
 *
 * @package App\Blog\Application\Service
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class PostFeedResponseBuilder
{
    public function __construct(private UserProxy $userProxy)
    {
    }

    /**
     * @param array<int, Post> $posts
     * @param int              $page
     * @param int              $limit
     * @param int              $total
     * @param string|null      $currentUserId
     *
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    public function build(array $posts, int $page, int $limit, int $total, ?string $currentUserId = null): array
    {
        $users = $this->userProxy->batchSearchUsers($this->collectUserIds($posts));

        return [
            'data' => array_map(fn(Post $post) => $this->formatPost($post, $users, $currentUserId), $posts),
            'page' => $page,
            'limit' => $limit,
            'count' => $total,
        ];
    }

    /**
     * @param array<int, Post> $posts
     *
     * @return array<int, string>
     */
    private function collectUserIds(array $posts): array
    {
        $userIds = [];

        foreach ($posts as $post) {
            $userIds[] = $post->getAuthor()->toString();

            foreach ($this->collectionToArray($post->getLikes()) as $like) {
                $userIds[] = $like->getUser()->toString();
            }

            foreach ($this->collectionToArray($post->getReactions()) as $reaction) {
                $userIds[] = $reaction->getUser()->toString();
            }

            $sharedFrom = $post->getSharedFrom();
            if ($sharedFrom) {
                $userIds[] = $sharedFrom->getAuthor()->toString();

                foreach ($this->collectionToArray($sharedFrom->getReactions()) as $reaction) {
                    $userIds[] = $reaction->getUser()->toString();
                }

                foreach ($this->collectionToArray($sharedFrom->getComments()) as $comment) {
                    $userIds[] = $comment->getAuthor()->toString();

                    foreach ($this->collectionToArray($comment->getReactions()) as $reaction) {
                        $userIds[] = $reaction->getUser()->toString();
                    }
                }
            }

            foreach ($this->collectionToArray($post->getComments()) as $comment) {
                $userIds[] = $comment->getAuthor()->toString();

                foreach ($this->collectionToArray($comment->getLikes()) as $like) {
                    $userIds[] = $like->getUser()->toString();
                }

                foreach ($this->collectionToArray($comment->getReactions()) as $reaction) {
                    $userIds[] = $reaction->getUser()->toString();
                }
            }
        }

        return array_unique($userIds);
    }

    private function formatPost(Post $post, array $users, ?string $currentUserId): array
    {
        return [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'summary' => $post->getSummary(),
            'content' => $post->getContent(),
            'url' => $post->getUrl(),
            'slug' => $post->getSlug(),
            'medias' => $post->getMediaEntities()->map(fn(Media $media) => $media->toArray())->toArray(),
            'isReacted' => $this->userHasReacted($this->collectionToArray($post->getReactions()), $currentUserId),
            'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
            'sharedFrom' => $this->formatSharedPost($post, $users, $currentUserId),
            'reactions_count' => count($post->getReactions()),
            'totalComments' => count($post->getComments()),
            'user' => $users[$post->getAuthor()->toString()] ?? null,
            'reactions_preview' => $this->formatReactionsPreview($this->collectionToArray($post->getReactions()), $users),
            'comments_preview' => $this->formatCommentsPreview($this->collectionToArray($post->getComments()), $users, $currentUserId, true),
        ];
    }

    private function formatSharedPost(Post $post, array $users, ?string $currentUserId): ?array
    {
        $sharedFrom = $post->getSharedFrom();

        if ($sharedFrom === null) {
            return null;
        }

        return [
            'id' => $sharedFrom->getId(),
            'title' => $sharedFrom->getTitle(),
            'summary' => $sharedFrom->getSummary(),
            'content' => $sharedFrom->getContent(),
            'url' => $sharedFrom->getUrl(),
            'slug' => $sharedFrom->getSlug(),
            'medias' => $sharedFrom->getMediaEntities()->map(fn(Media $media) => $media->toArray())->toArray(),
            'isReacted' => $this->userHasReacted($this->collectionToArray($sharedFrom->getReactions()), $currentUserId),
            'reactions_count' => count($sharedFrom->getReactions()),
            'totalComments' => count($sharedFrom->getComments()),
            'user' => $users[$sharedFrom->getAuthor()->toString()] ?? null,
            'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
            'reactions_preview' => $this->formatReactionsPreview($this->collectionToArray($sharedFrom->getReactions()), $users),
            'comments_preview' => $this->formatCommentsPreview($this->collectionToArray($sharedFrom->getComments()), $users, $currentUserId, false),
        ];
    }

    /**
     * @param array<int, Comment> $comments
     */
    private function formatCommentsPreview(array $comments, array $users, ?string $currentUserId, bool $includeLikesCount): array
    {
        $formatted = array_map(function (Comment $comment) use ($users, $currentUserId, $includeLikesCount) {
            $data = [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'user' => $users[$comment->getAuthor()->toString()] ?? null,
                'isReacted' => $this->userHasReacted($this->collectionToArray($comment->getReactions()), $currentUserId),
                'totalComments' => count($comment->getChildren()),
                'reactions_count' => count($comment->getReactions()),
                'publishedAt' => $comment->getPublishedAt()?->format(DATE_ATOM),
                'reactions_preview' => $this->formatReactionsPreview($this->collectionToArray($comment->getReactions()), $users),
            ];

            if ($includeLikesCount) {
                $data['likes_count'] = count($comment->getLikes());
            }

            return $data;
        }, $comments);

        return array_slice($formatted, 0, 2);
    }

    /**
     * @param array<int, Reaction> $reactions
     */
    private function formatReactionsPreview(array $reactions, array $users): array
    {
        $preview = array_map(static function (Reaction $reaction) use ($users) {
            return [
                'id' => $reaction->getId(),
                'type' => $reaction->getType(),
                'user' => $users[$reaction->getUser()->toString()] ?? null,
            ];
        }, $reactions);

        return array_slice($preview, 0, 2);
    }

    /**
     * @param array<int, Reaction> $reactions
     */
    private function userHasReacted(array $reactions, ?string $currentUserId): ?string
    {
        if ($currentUserId === null || $currentUserId === '') {
            return null;
        }

        foreach ($reactions as $reaction) {
            if ($reaction->getUser()->toString() === $currentUserId) {
                return $reaction->getType();
            }
        }

        return null;
    }

    /**
     * @template T
     *
     * @param Collection<int, T>|array<int, T> $collection
     *
     * @return array<int, T>
     */
    private function collectionToArray(Collection|array $collection): array
    {
        if ($collection instanceof Collection) {
            return $collection->toArray();
        }

        return $collection;
    }
}
