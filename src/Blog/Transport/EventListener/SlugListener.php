<?php

declare(strict_types=1);

namespace App\Blog\Transport\EventListener;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Post;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

readonly class SlugListener
{
    public function __construct(
        private SlugifyInterface $slugify
    ) {
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->handleSlug($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->handleSlug($args->getObject());
    }

    private function handleSlug(object $entity): void
    {
        if (!$entity instanceof Blog && !$entity instanceof Post) {
            return;
        }

        if (empty($entity->getSlug()) && !empty($entity->getTitle())) {
            $slug = $this->slugify->slugify($entity->getTitle());
            $entity->setSlug($slug);
        }
    }
}
