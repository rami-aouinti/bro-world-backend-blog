<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Entity\Reaction;
use Doctrine\Common\Collections\Collection;

use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function array_slice;
use function is_array;
use function iterator_to_array;

/**
 * Helper responsible for building comment, like and reaction payloads for frontend controllers.
 */
readonly class CommentResponseHelper
{
    public function __construct(private UserProxy $userProxy)
    {
    }

    /**
     * Builds a fully formatted comment payload including recursive children mapping.
     *
     * @param Comment     $comment
     * @param array       $users             Indexed array of user payloads keyed by their identifier
     * @param string|null $currentUserId     Identifier of the authenticated user, if any
     * @param bool        $includeLikesCount Whether to expose the likes count alongside the likes list
     * @param int         $previewLimit      Maximum number of reactions to expose in the preview list
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    public function buildCommentThread(
        Comment $comment,
        array $users,
        ?string $currentUserId = null,
        bool $includeLikesCount = false,
        int $previewLimit = 2,
    ): array {
        $reactions = $this->normalizeIterable($comment->getReactions());

        $payload = [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'likes' => $this->buildLikeList($comment->getLikes(), $users),
            'publishedAt' => $comment->getPublishedAt()?->format(DATE_ATOM),
            'user' => $this->resolveUser($users, $comment->getAuthor()->toString()),
            'children' => [],
            'totalComments' => $comment->getChildren()->count(),
            'isReacted' => $this->getReactionTypeForUser($reactions, $currentUserId),
            'reactions_count' => count($reactions),
            'reactions_preview' => array_slice(
                $this->buildReactionList($reactions, $users),
                0,
                $previewLimit,
            ),
        ];

        if ($includeLikesCount) {
            $payload['likes_count'] = $comment->getLikes()->count();
        }

        foreach ($comment->getChildren() as $child) {
            $payload['children'][] = $this->buildCommentThread(
                $child,
                $users,
                $currentUserId,
                $includeLikesCount,
                $previewLimit,
            );
        }

        return $payload;
    }

    /**
     * @param iterable<int, Like> $likes
     * @param array               $users
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    public function buildLikeList(iterable $likes, array $users): array
    {
        $payload = [];

        foreach ($this->normalizeIterable($likes) as $like) {
            $userId = $like->getUser()?->toString();
            $payload[] = [
                'id' => $like->getId(),
                'user' => $this->resolveUser($users, $userId),
            ];
        }

        return $payload;
    }

    /**
     * @param iterable<int, Reaction> $reactions
     * @param array                   $users
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    public function buildReactionList(iterable $reactions, array $users): array
    {
        $payload = [];

        foreach ($this->normalizeIterable($reactions) as $reaction) {
            $userId = $reaction->getUser()?->toString();
            $payload[] = [
                'id' => $reaction->getId(),
                'type' => $reaction->getType(),
                'user' => $this->resolveUser($users, $userId),
            ];
        }

        return $payload;
    }

    /**
     * Determines the reaction type left by the provided user, if any.
     *
     * @param iterable<int, Reaction> $reactions
     */
    public function getReactionTypeForUser(iterable $reactions, ?string $currentUserId): ?string
    {
        if ($currentUserId === null || $currentUserId === '') {
            return null;
        }

        foreach ($this->normalizeIterable($reactions) as $reaction) {
            if ($reaction->getUser()?->toString() === $currentUserId) {
                return $reaction->getType();
            }
        }

        return null;
    }

    /**
     * @template T
     * @param iterable<int, T> $items
     * @return array<int, T>
     */
    private function normalizeIterable(iterable $items): array
    {
        if ($items instanceof Collection) {
            return $items->toArray();
        }

        if (is_array($items)) {
            return $items;
        }

        return iterator_to_array($items, false);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function resolveUser(array $users, ?string $userId): mixed
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        return $users[$userId] ?? $this->userProxy->searchUser($userId);
    }
}
