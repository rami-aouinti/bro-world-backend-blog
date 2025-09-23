<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Blog;

use App\General\Transport\AutoMapper\RestRequestMapper;

/**
 * @package App\Blog
 */
class RequestMapper extends RestRequestMapper
{
    /**
     * @var array<int, non-empty-string>
     */
    protected static array $properties = [
        'title',
        'blogSubtitle',
        'author',
        'logo',
        'teams',
        'visible',
        'slug',
        'color',
    ];
}
