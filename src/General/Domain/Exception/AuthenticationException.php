<?php

declare(strict_types=1);

namespace App\General\Domain\Exception;

/**
 * @package App\General\Domain\Exception
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
final class AuthenticationException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message, self::CODE_UNAUTHORIZED);
    }
}
