<?php

declare(strict_types=1);

namespace App\General\Application\Rest\Traits;

/**
 * @SuppressWarnings("unused")
 *
 * @package App\General\Application\Rest\Traits
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
trait RestResourceIds
{
    /**
     * Before lifecycle method for ids method.
     *
     * @param mixed[] $criteria
     * @param mixed[] $search
     */
    public function beforeIds(array &$criteria, array &$search): void
    {
    }

    /**
     * Before lifecycle method for ids method.
     *
     * @param mixed[] $criteria
     * @param mixed[] $search
     * @param string[] $ids
     */
    public function afterIds(array &$criteria, array &$search, array &$ids): void
    {
    }
}
