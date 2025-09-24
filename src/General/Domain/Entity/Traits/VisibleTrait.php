<?php

declare(strict_types=1);

namespace App\General\Domain\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @package App\General\Domain\Entity\Traits
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
trait VisibleTrait
{
    #[ORM\Column(name: 'visible', type: 'boolean', nullable: false)]
    #[Groups([
        'Post',
        'Post.slug',
        'Blog',
        'Blog.slug',
        'Post_Show',
        'BlogProfile',
    ])]
    private bool $visible = true;

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }
}
