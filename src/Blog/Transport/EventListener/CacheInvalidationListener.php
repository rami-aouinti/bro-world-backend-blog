<?php

declare(strict_types=1);

namespace App\Blog\Transport\EventListener;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @package App\Blog\Transport\EventListener
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
final readonly class CacheInvalidationListener
{
    public function __construct(
        private TagAwareCacheInterface $cache
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateIfRelevant($args->getObject());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateIfRelevant($args->getObject());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateIfRelevant($args->getObject());
    }

    /**
     * @throws InvalidArgumentException
     */
    private function invalidateIfRelevant(object $entity): void
    {
        if (
            $entity instanceof Post ||
            $entity instanceof Comment ||
            $entity instanceof Reaction
        ) {
            $this->cache->invalidateTags(['posts', 'comments', 'likes', 'reactions']);
        }
    }
}
