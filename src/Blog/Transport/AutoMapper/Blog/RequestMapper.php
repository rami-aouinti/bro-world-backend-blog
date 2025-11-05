<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Blog;

use Bro\WorldCoreBundle\Transport\AutoMapper\RestRequestMapper;

/**
 * @package App\Blog\Transport\AutoMapper\Blog
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
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
