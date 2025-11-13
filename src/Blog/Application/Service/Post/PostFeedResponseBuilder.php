<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Post;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Media;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\PersistentCollection;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function array_map;
use function array_slice;
use function array_unique;

/**
 * @package App\Blog\Application\Service\Post
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
readonly class PostFeedResponseBuilder
{
    public function __construct(
        private UserProxy $userProxy
    ) {
    }

    /**
     * @param array<int, Post> $posts
     *
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function build(array $posts, int $page, int $limit, int $total, ?string $currentUserId = null): array
    {
        $users = $this->userProxy->batchSearchUsers($this->collectUserIds($posts));

        return [
            'data' => array_map(fn (Post $post) => $this->formatPost($post, $users, $currentUserId), $posts),
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

            foreach ($this->collectionToArray($post->getReactions(), 2) as $reaction) {
                $userIds[] = $reaction->getUser()->toString();
            }

            $sharedFrom = $post->getSharedFrom();
            if ($sharedFrom) {
                $userIds[] = $sharedFrom->getAuthor()->toString();

                foreach ($this->collectionToArray($sharedFrom->getReactions(), 2) as $reaction) {
                    $userIds[] = $reaction->getUser()->toString();
                }

                foreach ($this->collectionToArray($sharedFrom->getComments(), 2) as $comment) {
                    $userIds[] = $comment->getAuthor()->toString();

                    foreach ($this->collectionToArray($comment->getReactions(), 2) as $reaction) {
                        $userIds[] = $reaction->getUser()->toString();
                    }
                }
            }

            foreach ($this->collectionToArray($post->getComments(), 2) as $comment) {
                $userIds[] = $comment->getAuthor()->toString();

                foreach ($this->collectionToArray($comment->getReactions(), 2) as $reaction) {
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
            'medias' => $post->getMediaEntities()->map(fn (Media $media) => $media->toArray())->toArray(),
            'isReacted' => $this->userHasReacted($post->getReactions(), $currentUserId),
            'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
            'sharedFrom' => $this->formatSharedPost($post, $users, $currentUserId),
            'reactions_count' => $post->getReactions()->count(),
            'totalComments' => $post->getComments()->count(),
            'user' => $users[$post->getAuthor()->toString()] ?? null,
            'reactions_preview' => $this->formatReactionsPreview($post->getReactions(), $users),
            'comments_preview' => $this->formatCommentsPreview($post->getComments(), $users, $currentUserId, true),
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
            'medias' => $sharedFrom->getMediaEntities()->map(fn (Media $media) => $media->toArray())->toArray(),
            'isReacted' => $this->userHasReacted($sharedFrom->getReactions(), $currentUserId),
            'reactions_count' => $sharedFrom->getReactions()->count(),
            'totalComments' => $sharedFrom->getComments()->count(),
            'user' => $users[$sharedFrom->getAuthor()->toString()] ?? null,
            'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
            'reactions_preview' => $this->formatReactionsPreview($sharedFrom->getReactions(), $users),
            'comments_preview' => $this->formatCommentsPreview($sharedFrom->getComments(), $users, $currentUserId, false),
        ];
    }

    /**
     * @param Collection<int, Comment>|array<int, Comment> $comments
     */
    private function formatCommentsPreview(Collection|array $comments, array $users, ?string $currentUserId, bool $includeLikesCount): array
    {
        $previewComments = $this->collectionToArray($comments, 2);

        $formatted = array_map(function (Comment $comment) use ($users, $currentUserId, $includeLikesCount) {
            $data = [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'user' => $users[$comment->getAuthor()->toString()] ?? null,
                'isReacted' => $this->userHasReacted($comment->getReactions(), $currentUserId),
                'totalComments' => $comment->getChildren()->count(),
                'reactions_count' => $comment->getReactions()->count(),
                'publishedAt' => $comment->getPublishedAt()?->format(DATE_ATOM),
                'reactions_preview' => $this->formatReactionsPreview($comment->getReactions(), $users),
            ];

            if ($includeLikesCount) {
                $data['likes_count'] = $comment->getLikes()->count();
            }

            return $data;
        }, $previewComments);

        return $formatted;
    }

    /**
     * @param Collection<int, Reaction>|array<int, Reaction> $reactions
     */
    private function formatReactionsPreview(Collection|array $reactions, array $users): array
    {
        $previewReactions = $this->collectionToArray($reactions, 2);

        $preview = array_map(static function (Reaction $reaction) use ($users) {
            return [
                'id' => $reaction->getId(),
                'type' => $reaction->getType(),
                'user' => $users[$reaction->getUser()->toString()] ?? null,
            ];
        }, $previewReactions);

        return $preview;
    }

    /**
     * @param Collection<int, Reaction>|array<int, Reaction> $reactions
     */
    private function userHasReacted(Collection|array $reactions, ?string $currentUserId): ?string
    {
        if ($currentUserId === null || $currentUserId === '') {
            return null;
        }

        if ($reactions instanceof PersistentCollection && !$reactions->isInitialized()) {
            $criteria = Criteria::create()
                ->where(Criteria::expr()->eq('user', Uuid::fromString($currentUserId)))
                ->setMaxResults(1);

            $matched = $reactions->matching($criteria);

            if (!$matched->isEmpty()) {
                $reaction = $matched->first();

                return $reaction instanceof Reaction ? $reaction->getType() : null;
            }
        }

        foreach ($this->collectionToArray($reactions) as $reaction) {
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
    private function collectionToArray(Collection|array $collection, ?int $limit = null): array
    {
        if ($collection instanceof Collection) {
            if ($limit !== null && $limit >= 0) {
                return $collection->slice(0, $limit);
            }

            return $collection->toArray();
        }

        if ($limit !== null && $limit >= 0) {
            return array_slice($collection, 0, $limit);
        }

        return $collection;
    }
}
