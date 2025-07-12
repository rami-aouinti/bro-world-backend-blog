<?php

declare(strict_types=1);

namespace App\Blog\Transport\AutoMapper\Post;

use App\General\Transport\AutoMapper\RestRequestMapper;

/**
 * @package App\Post
 */
class RequestMapper extends RestRequestMapper
{
    /**
     * @var array<int, non-empty-string>
     */
    protected static array $properties = [
        'title',
        'description',
        'userId',
        'photo',
        'birthday',
        'gender',
        'googleId',
        'githubId',
        'githubUrl',
        'instagramUrl',
        'linkedInId',
        'linkedInUrl',
        'twitterUrl',
        'facebookUrl',
        'phone'
    ];
}
