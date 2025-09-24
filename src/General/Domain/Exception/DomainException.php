<?php

declare(strict_types=1);

namespace App\General\Domain\Exception;

use DomainException as CoreDomainException;

/**
 * @package App\General\Domain\Exception
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class DomainException extends CoreDomainException
{
    public const int CODE_UNAUTHORIZED = 401;
    public const int CODE_FORBIDDEN = 403;
    public const int CODE_NOT_FOUND = 404;
    public const int CODE_CONFLICT = 409;
    public const int CODE_UNPROCESSABLE_ENTITY = 422;
}
