<?php

declare(strict_types=1);

namespace App\Blog\Transport\EventListener;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Entity\Reaction;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Cache\CacheItemPoolInterface;
use App\Blog\Domain\Entity\Post;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class CacheInvalidationListener
 *
 * @package App\Blog\Transport\EventListener
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class CacheInvalidationListener
{
    private CacheItemPoolInterface $cache;

    public function __construct(
        CacheItemPoolInterface $cache,
        private readonly UserProxy $userProxy
    )
    {
        $this->cache = $cache;
    }

    /**
     * @param LifecycleEventArgs $args
     *
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        //$this->handleInvalidation($args->getObject());
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handleInvalidation($args->getObject());
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
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
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function handleInvalidation(object $entity): void
    {
        $post = match (true) {
            $entity instanceof Post => $entity,
            $entity instanceof Comment => $entity->getPost() ?? $entity->getParent()?->getPost(),
            $entity instanceof Like => $entity->getPost() ?? $entity->getComment()?->getPost(),
            default => null,
        };



        //$this->cache->deleteItem("public_post_{$post?->getSlug()}");
    }
}
