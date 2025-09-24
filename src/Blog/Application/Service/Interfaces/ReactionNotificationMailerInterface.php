<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Interfaces;

interface ReactionNotificationMailerInterface
{
    public function sendPostReactionNotificationEmail(string $postAuthorId, string $reactorId, ?string $postSlug): void;

    public function sendCommentReactionNotificationEmail(string $commentAuthorId, string $reactorId, ?string $postSlug): void;
}
