<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Like;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;

/**
 * @package App\Like
 */
class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    public function __construct(
        RequestMapper $requestMapper,
    ) {
        parent::__construct($requestMapper);
    }
}
