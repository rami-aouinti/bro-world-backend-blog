<?php

declare(strict_types=1);

namespace App\Blog\Domain\Repository\Interfaces;

use Bro\WorldCoreBundle\Domain\Repository\Interfaces\BaseRepositoryInterface;

/**
 * @package App\Blog\Domain\Repository\Interfaces
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
interface CommentRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return array<string, int>
     */
    public function countCommentsByMonth(): array;
}
