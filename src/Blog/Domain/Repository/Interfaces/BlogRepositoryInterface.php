<?php

declare(strict_types=1);

namespace App\Blog\Domain\Repository\Interfaces;

/**
 * @package App\Blog
 */
interface BlogRepositoryInterface
{
    /**
     * @return array
     */
    public function countBlogsByMonth(): array;
}
