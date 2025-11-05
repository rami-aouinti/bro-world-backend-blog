<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Like;

use Bro\WorldCoreBundle\Transport\AutoMapper\RestAutoMapperConfiguration;

/**
 * @package App\Blog\Transport\AutoMapper\Like
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    public function __construct(
        RequestMapper $requestMapper,
    ) {
        parent::__construct($requestMapper);
    }
}
