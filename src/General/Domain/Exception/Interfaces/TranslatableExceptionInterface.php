<?php

declare(strict_types=1);

namespace App\General\Domain\Exception\Interfaces;

use Throwable;

/**
 * @package App\General\Domain\Exception\Interfaces
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
interface TranslatableExceptionInterface extends Throwable
{
    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    public function getDomain(): ?string;
}
