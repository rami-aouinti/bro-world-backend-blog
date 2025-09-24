<?php

declare(strict_types=1);

namespace App\Blog\Transport\Event;

use App\Blog\Domain\Entity\Post;
use Symfony\Contracts\EventDispatcher\Event;

final class PostCreatedEvent extends Event
{
    private bool $blocked = false;

    /**
     * @var array<int, string>
     */
    private array $violations = [];
    private ?string $reason = null;

    public function __construct(
        private readonly Post $post
    ) {
    }

    public function getPost(): Post
    {
        return $this->post;
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
