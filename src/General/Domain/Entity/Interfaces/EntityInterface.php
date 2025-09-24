<?php

declare(strict_types=1);

namespace App\General\Domain\Entity\Interfaces;

use DateTimeImmutable;

/**
 * @package App\General\Domain\Entity\Interfaces
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
interface EntityInterface
{
    /**
     * @return non-empty-string
     */
    public function getId(): string;
    public function getCreatedAt(): ?DateTimeImmutable;
}
