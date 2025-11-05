<?php

declare(strict_types=1);

namespace App\Blog\Domain\Message;

use App\Blog\Domain\Entity\Post;
use Bro\WorldCoreBundle\Domain\Message\Interfaces\MessageHighInterface;

/**
 * @package App\Blog\Domain\Message
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class CreatePostMessenger implements MessageHighInterface
{
    public function __construct(
        private ?Post $post,
        private ?array $mediasIds
    ) {
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function getMediasIds(): ?array
    {
        return $this->mediasIds;
    }
}
