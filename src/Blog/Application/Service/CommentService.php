<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Ramsey\Uuid\Uuid;

/**
 * @package App\Blog\Application\Service
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class CommentService
{
    public function __construct(
        private CommentRepositoryInterface $commentRepository,
        private PostRepositoryInterface $postRepository
    ) {
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function saveComment(Comment $comment, ?string $postId, ?string $userId, ?array $data): void
    {
        $post = $this->postRepository->find($postId);
        $comment->setPost($post);
        $comment->setAuthor(Uuid::fromString($userId));
        $comment->setContent($data['content']);
        $this->commentRepository->save($comment);
    }

    public function commentToArray($comment, $usersById): array
    {
        return [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'parent' => $comment->getParent()?->getId(),
            'children' => [],
            'medias' => $comment->getMedias(),
            'likes' => $comment->getLikes()->toArray(),
            'publishedAt' => $comment->getPublishedAt()->format('Y-m-d H:i:s'),
            'author' => $usersById[$comment->getAuthor()?->toString()] ?? null,
        ];
    }
}
