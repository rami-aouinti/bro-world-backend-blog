<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Post;

use App\Blog\Application\DTO\Post\PostCreate;
use App\Blog\Application\DTO\Post\PostPatch;
use App\Blog\Application\DTO\Post\PostUpdate;
use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;

/**
 * @package App\Blog\Transport\AutoMapper\Post
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
        PostCreate::class,
        PostUpdate::class,
        PostPatch::class,
    ];

    public function __construct(
        RequestMapper $requestMapper,
    ) {
        parent::__construct($requestMapper);
    }
}
