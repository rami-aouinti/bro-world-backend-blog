<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Blog;

use App\Blog\Application\DTO\Blog\BlogCreate;
use App\Blog\Application\DTO\Blog\BlogPatch;
use App\Blog\Application\DTO\Blog\BlogUpdate;
use Bro\WorldCoreBundle\Transport\AutoMapper\RestAutoMapperConfiguration;

/**
 * @package App\Blog\Transport\AutoMapper\Blog
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    /**
     * Classes to use specified request mapper.
     *
     * @var array<int, class-string>
     */
    protected static array $requestMapperClasses = [
        BlogCreate::class,
        BlogUpdate::class,
        BlogPatch::class,
    ];

    public function __construct(
        RequestMapper $requestMapper,
    ) {
        parent::__construct($requestMapper);
    }
}
