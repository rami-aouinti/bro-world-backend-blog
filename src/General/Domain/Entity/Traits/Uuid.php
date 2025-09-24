<?php

declare(strict_types=1);

namespace App\General\Domain\Entity\Traits;

use App\General\Domain\Rest\UuidHelper;
use Ramsey\Uuid\UuidInterface;
use Throwable;

/**
 * @package App\General\Domain\Entity\Traits
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
trait Uuid
{
    public function getUuid(): UuidInterface
    {
        return $this->id;
    }

    /**
     * @throws Throwable
     */
    protected function createUuid(): UuidInterface
    {
        return UuidHelper::getFactory()->uuid1();
    }
}
