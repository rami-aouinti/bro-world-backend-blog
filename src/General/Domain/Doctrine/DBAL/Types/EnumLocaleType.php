<?php

declare(strict_types=1);

namespace App\General\Domain\Doctrine\DBAL\Types;

use App\General\Domain\Enum\Locale;

/**
 * @package App\General\Domain\Doctrine\DBAL\Types
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class EnumLocaleType extends EnumType
{
    protected static string $name = Types::ENUM_LOCALE;
    protected static string $enum = Locale::class;
}
