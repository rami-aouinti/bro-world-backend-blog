<?php

declare(strict_types=1);

namespace App\Blog\Domain\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

/**
 * Class CreatePostMessenger
 *
 * @package App\Post\Domain\Message
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class CreateNotificationMessenger implements MessageHighInterface
{
    public function __construct(
        private ?string $token,
        private ?string $channel,
        private ?string $senderId,
        private ?string $userId,
        private ?string $postId
    )
    {
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function getSenderId(): ?string
    {
        return $this->senderId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getPostId(): ?string
    {
        return $this->postId;
    }
}
