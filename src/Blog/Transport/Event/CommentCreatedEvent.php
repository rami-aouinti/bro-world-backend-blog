<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Blog\Transport\Event;

use App\Blog\Domain\Entity\Comment;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class CommentCreatedEvent
 *
 * @package App\Blog\Transport\Event
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
final class CommentCreatedEvent extends Event
{
    public function __construct(
        protected Comment $comment
    ) {
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }
}
