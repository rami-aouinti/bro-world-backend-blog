<?php

declare(strict_types=1);

namespace App\Blog\Domain\Repository\Interfaces;

/**
 * @package App\Blog
 */
interface PostRepositoryInterface
{
    /**
     * @return array
     */
    public function countPostsByMonth(): array;
}
