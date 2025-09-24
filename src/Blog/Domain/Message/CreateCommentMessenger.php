<?php

declare(strict_types=1);

namespace App\Blog\Domain\Message;

use App\Blog\Domain\Entity\Comment;
use App\General\Domain\Message\Interfaces\MessageHighInterface;

/**
 * @package App\Blog\Domain\Message
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class CreateCommentMessenger implements MessageHighInterface
{
    public function __construct(
        private ?string $token,
        private ?Comment $comment,
        private ?string $postId,
        private ?string $senderId,
        private ?string $userId,
        private ?array $data
    ) {
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getComment(): ?Comment
    {
        return $this->comment;
    }

    public function getPostId(): ?string
    {
        return $this->postId;
    }

    public function getSenderId(): ?string
    {
        return $this->senderId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getData(): ?array
    {
        return $this->data;
    }
}
