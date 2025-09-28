<?php

declare(strict_types=1);

namespace App\General\Application\Rest\Interfaces;

/**
 * @package App\General\Application\Rest\Interfaces
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
interface RestResourceInterface extends
    BaseRestResourceInterface,
    RestCountResourceInterface,
    RestCreateResourceInterface,
    RestDeleteResourceInterface,
    RestIdsResourceInterface,
    RestListResourceInterface,
    RestPatchResourceInterface,
    RestUpdateResourceInterface,
    RestFindOneResourceInterface
{
}
