<?php

declare(strict_types=1);

namespace App\General\Domain\Entity\Traits;

use App\General\Application\Export\Annotation\Expose;
use App\General\Application\Validator\Constraints\HexColor;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

trait ColorTrait
{
    public const string DEFAULT_COLOR = '#d2d6de';

    /**
     * The assigned color in HTML hex format, e.g. #dd1d00
     */
    #[ORM\Column(name: 'color', type: 'string', length: 36, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Expose(label: 'color')]
    #[HexColor]
    private ?string $color = null;

    public function getColor(): ?string
    {
        if ($this->color === self::DEFAULT_COLOR) {
            return null;
        }

        return $this->color;
    }

    public function hasColor(): bool
    {
        return $this->color !== null && $this->color !== self::DEFAULT_COLOR;
    }

    public function setColor(?string $color = null): void
    {
        $this->color = $color;
    }
}
