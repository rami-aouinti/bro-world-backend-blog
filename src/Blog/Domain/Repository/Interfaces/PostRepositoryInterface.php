<?php

declare(strict_types=1);

namespace App\Blog\Domain\Repository\Interfaces;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use Bro\WorldCoreBundle\Domain\Repository\Interfaces\BaseRepositoryInterface;

/**
 * @package App\Blog\Domain\Repository\Interfaces
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
interface PostRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return array<int, Post>
     */
    public function findWithRelations(int $limit, int $offset, ?string $authorId = null): array;

    public function countPosts(?string $authorId = null): int;

    /**
     * @return array<int, Comment>
     */
    public function getRootComments(string $postId, int $limit, int $offset): array;

    public function countComments(string $postId): int;

    /**
     * @return array<string, int>
     */
    public function countPostsByMonth(): array;
}
