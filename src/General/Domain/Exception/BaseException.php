<?php

declare(strict_types=1);

namespace App\General\Domain\Exception;

use App\General\Domain\Exception\Interfaces\ExceptionInterface;
use Exception;

/**
 * @package App\General\Domain\Exception
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
abstract class BaseException extends Exception implements ExceptionInterface
{
}
