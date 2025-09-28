<?php

declare(strict_types=1);

namespace App\General\Domain\Enum\Interfaces;

use BackedEnum;

/**
 * Enum StringEnumInterface
 *
 * @package App\General\Domain\Enum\Interfaces
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
interface StringEnumInterface extends BackedEnum
{
    /**
     * @return array<int, string>
     */
    public static function getValues(): array;
}
