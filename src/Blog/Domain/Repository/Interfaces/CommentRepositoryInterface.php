<?php

declare(strict_types=1);

namespace App\Blog\Domain\Repository\Interfaces;

use App\General\Domain\Repository\Interfaces\BaseRepositoryInterface;

/**
 * @package App\Blog
 */
interface CommentRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return array<string, int>
     */
    public function countCommentsByMonth(): array;
}
