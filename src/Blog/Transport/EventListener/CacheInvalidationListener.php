<?php

declare(strict_types=1);

namespace App\Blog\Transport\EventListener;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Cache\CacheItemPoolInterface;
use App\Blog\Domain\Entity\Post;
use Psr\Cache\InvalidArgumentException;

/**
 * Class CacheInvalidationListener
 *
 * @package App\Blog\Transport\EventListener
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class CacheInvalidationListener
{
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handleInvalidation($args->getObject());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handleInvalidation($args->getObject());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->handleInvalidation($args->getObject());
    }

    /**
     *
     * @param object $entity
     *
     * @throws InvalidArgumentException
     */
    private function handleInvalidation(object $entity): void
    {
        $post = match (true) {
            $entity instanceof Post => $entity,
            $entity instanceof Comment => $entity->getPost() ?? $entity->getParent()?->getPost(),
            $entity instanceof Like => $entity->getPost() ?? $entity->getComment()?->getPost(),
            default => null,
        };

        $cacheKey = "post_public_1_10";
        $this->cache->deleteItem($cacheKey);
        $this->cache->deleteItem("all_posts_1_10");
        $this->cache->deleteItem("post_{$post?->getId()}");
    }
}
