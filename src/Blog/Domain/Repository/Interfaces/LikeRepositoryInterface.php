<?php

declare(strict_types=1);

namespace App\Blog\Domain\Repository\Interfaces;

/**
 * @package App\Blog
 */
interface LikeRepositoryInterface
{
    /**
     * @return array
     */
    public function countLikesByMonth(): array;
}
