<?php

declare(strict_types=1);

namespace App\General\Domain\Enum;

use App\General\Domain\Enum\Interfaces\DatabaseEnumInterface;
use App\General\Domain\Enum\Traits\GetValues;

/**
 * Locale
 *
 * @package App\General\Domain\Enum
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
enum Locale: string implements DatabaseEnumInterface
{
    use GetValues;

    case EN = 'en';
    case RU = 'ru';
    case UA = 'ua';
    case FI = 'fi';

    public static function getDefault(): self
    {
        return self::EN;
    }
}
