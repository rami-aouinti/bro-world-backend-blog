<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\General\Domain\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @package App\General\Domain\Entity\Traits
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
trait SlugTrait
{
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([
        'Post',
        'Post.slug',
        'Blog',
        'Blog.slug',
        'Post_Show',
        'BlogProfile',
    ])]
    private ?string $slug = null;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(
        ?string $slug
    ): self {
        $this->slug = (string)$slug;

        return $this;
    }
}
