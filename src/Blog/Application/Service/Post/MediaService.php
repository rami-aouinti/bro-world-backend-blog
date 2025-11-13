<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Post;

use Bro\WorldCoreBundle\Infrastructure\Service\ApiProxyService;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * @package App\Blog\Application\Service\Post
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
readonly class MediaService
{
    private const string PATH = 'media';
    private const string CREATE_MEDIA_PATH = 'v1/platform/media';

    public function __construct(
        private ApiProxyService $proxyService
    ) {
    }
    /**
     * @throws Throwable
     */
    public function createMedia(Request $request, string $context): array
    {
        $mediasArray = [];
        $medias = $this->proxyService->requestFile(
            Request::METHOD_POST,
            self::PATH,
            $request,
            [
                'context' => $context,
            ],
            self::CREATE_MEDIA_PATH
        );

        foreach ($medias as $media) {
            if ($media) {
                $mediasArray[] = $media['id'];
            }
        }

        return $mediasArray;
    }
}
