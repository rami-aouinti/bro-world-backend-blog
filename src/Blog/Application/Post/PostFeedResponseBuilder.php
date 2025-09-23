<?php

declare(strict_types=1);

namespace App\Blog\Application\Post;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Media;
use App\Blog\Domain\Entity\Post;

use function array_unique;
use function array_values;
use function count;
use function is_array;
use function iterator_to_array;

final class PostFeedResponseBuilder
{
    public function __construct(private readonly UserProxy $userProxy)
    {
    }

    /**
     * @param iterable<Post> $posts
     */
    public function buildFeedResponse(iterable $posts, int $page, int $limit, int $total): array
    {
        $posts = $this->normalizePosts($posts);
        $users = $this->userProxy->batchSearchUsers($this->collectUserIds($posts));

        $data = [];
        foreach ($posts as $post) {
            $data[] = $this->buildPostItem($post, $users);
        }

        return [
            'data' => $data,
            'page' => $page,
            'limit' => $limit,
            'count' => $total,
        ];
    }

    /**
     * @param iterable<Post> $posts
     * @return array<int, string>
     */
    public function collectUserIds(iterable $posts): array
    {
        $userIds = [];

        foreach ($posts as $post) {
            $userIds[] = $post->getAuthor()->toString();

            foreach ($post->getLikes() as $like) {
                $userIds[] = $like->getUser()->toString();
            }

            foreach ($post->getReactions() as $reaction) {
                $userIds[] = $reaction->getUser()->toString();
            }

            foreach ($post->getComments() as $comment) {
                $this->collectCommentUserIds($comment, $userIds);
            }

            $sharedFrom = $post->getSharedFrom();
            if ($sharedFrom === null) {
                continue;
            }

            $userIds[] = $sharedFrom->getAuthor()->toString();

            foreach ($sharedFrom->getReactions() as $reaction) {
                $userIds[] = $reaction->getUser()->toString();
            }

            foreach ($sharedFrom->getComments() as $comment) {
                $this->collectCommentUserIds($comment, $userIds);
            }
        }

        return array_values(array_unique($userIds));
    }

    /**
     * @param array<string, mixed> $users
     */
    public function buildPostItem(Post $post, array $users): array
    {
        $sharedFrom = $post->getSharedFrom();

        return [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'summary' => $post->getSummary(),
            'content' => $post->getContent(),
            'url' => $post->getUrl(),
            'slug' => $post->getSlug(),
            'medias' => $post->getMediaEntities()->map(fn (Media $m) => $m->toArray())->toArray(),
            'isReacted' => null,
            'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
            'sharedFrom' => $sharedFrom ? $this->buildSharedPostItem($post, $sharedFrom, $users) : null,
            'reactions_count' => count($post->getReactions()),
            'totalComments' => count($post->getComments()),
            'user' => $users[$post->getAuthor()->toString()] ?? null,
            'reactions_preview' => $this->buildReactionsPreview($post->getReactions(), $users),
            'comments_preview' => $this->buildCommentsPreview($post->getComments(), $users),
        ];
    }

    /**
     * @param array<string, mixed> $users
     */
    public function buildCommentPreview(Comment $comment, array $users, bool $includeLikesCount = true): array
    {
        $data = [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'user' => $users[$comment->getAuthor()->toString()] ?? null,
        ];

        if ($includeLikesCount) {
            $data['likes_count'] = count($comment->getLikes());
        }

        $data['isReacted'] = null;
        $data['totalComments'] = count($comment->getChildren());
        $data['reactions_count'] = count($comment->getReactions());
        $data['publishedAt'] = $comment->getPublishedAt()?->format(DATE_ATOM);
        $data['reactions_preview'] = $this->buildReactionsPreview($comment->getReactions(), $users);

        return $data;
    }

    /**
     * @param iterable<Post> $posts
     * @return array<int, Post>
     */
    private function normalizePosts(iterable $posts): array
    {
        return is_array($posts) ? $posts : iterator_to_array($posts, false);
    }

    /**
     * @param array<int, string> $userIds
     */
    private function collectCommentUserIds(Comment $comment, array &$userIds): void
    {
        $userIds[] = $comment->getAuthor()->toString();

        foreach ($comment->getLikes() as $like) {
            $userIds[] = $like->getUser()->toString();
        }

        foreach ($comment->getReactions() as $reaction) {
            $userIds[] = $reaction->getUser()->toString();
        }
    }

    /**
     * @param array<string, mixed> $users
     */
    private function buildSharedPostItem(Post $post, Post $sharedFrom, array $users): array
    {
        return [
            'id' => $sharedFrom->getId(),
            'title' => $sharedFrom->getTitle(),
            'summary' => $sharedFrom->getSummary(),
            'url' => $sharedFrom->getUrl(),
            'slug' => $sharedFrom->getSlug(),
            'medias' => $sharedFrom->getMediaEntities()->map(fn (Media $m) => $m->toArray())->toArray(),
            'isReacted' => null,
            'reactions_count' => count($sharedFrom->getReactions()),
            'totalComments' => count($sharedFrom->getComments()),
            'user' => $users[$sharedFrom->getAuthor()->toString()] ?? null,
            'publishedAt' => $post->getPublishedAt()?->format(DATE_ATOM),
            'reactions_preview' => $this->buildReactionsPreview($sharedFrom->getReactions(), $users),
            'comments_preview' => $this->buildCommentsPreview($sharedFrom->getComments(), $users, false),
        ];
    }

    /**
     * @param iterable<Comment> $comments
     * @param array<string, mixed> $users
     */
    private function buildCommentsPreview(iterable $comments, array $users, bool $includeLikesCount = true, int $limit = 2): array
    {
        $preview = [];
        $index = 0;

        foreach ($comments as $comment) {
            $preview[] = $this->buildCommentPreview($comment, $users, $includeLikesCount);
            $index++;

            if ($index >= $limit) {
                break;
            }
        }

        return $preview;
    }

    /**
     * @param iterable $reactions
     * @param array<string, mixed> $users
     */
    private function buildReactionsPreview(iterable $reactions, array $users, int $limit = 2): array
    {
        $preview = [];
        $index = 0;

        foreach ($reactions as $reaction) {
            $preview[] = [
                'id' => $reaction->getId(),
                'type' => $reaction->getType(),
                'user' => $users[$reaction->getUser()->toString()] ?? null,
            ];
            $index++;

            if ($index >= $limit) {
                break;
            }
        }

        return $preview;
    }
}
