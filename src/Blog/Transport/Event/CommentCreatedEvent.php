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
 * @package App\Blog\Transport\Event
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
final class CommentCreatedEvent extends Event
{
    private bool $blocked = false;

    /**
     * @var array<int, string>
     */
    private array $violations = [];
    private ?string $reason = null;

    public function __construct(
        private readonly Comment $comment
    ) {
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }

    /**
     * @param array<int, string> $violations
     */
    public function block(array $violations, ?string $reason = null): void
    {
        $this->blocked = true;
        $this->violations = $violations;
        $this->reason = $reason;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    /**
     * @return array<int, string>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
