<?php

declare(strict_types=1);

namespace App\Blog\Domain\Repository\Interfaces;

/**
 * @package App\Blog\Domain\Repository\Interfaces
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
interface BlogRepositoryInterface
{
    public function countBlogsByMonth(): array;
}
