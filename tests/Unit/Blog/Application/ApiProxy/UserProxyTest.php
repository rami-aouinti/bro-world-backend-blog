<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\ApiProxy;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Application\Service\UserCacheService;
use App\Tests\Utils\Cache\InMemoryCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class UserProxyTest extends TestCase
{
    public function testSearchUserHandlesNumericallyIndexedPayload(): void
    {
        $users = [
            [
                'id' => '10',
                'name' => 'Alice',
            ],
            [
                'id' => '20',
                'name' => 'Bob',
            ],
        ];

        $httpClient = new MockHttpClient(new MockResponse(json_encode($users)));

        $userCacheService = $this->createMock(UserCacheService::class);
        $userCacheService->expects($this->once())
            ->method('searchUser')
            ->with('20')
            ->willReturn(null);

        $userCacheService->expects($this->exactly(2))
            ->method('save')
            ->withConsecutive(
                ['10', $users[0]],
                ['20', $users[1]]
            );

        $proxy = new UserProxy($httpClient, $userCacheService, new InMemoryCache());

        $result = $proxy->searchUser('20');

        $this->assertSame($users[1], $result);
    }

    public function testMediaLookupIsCached(): void
    {
        $mediaResponse = ['id' => 'media-1'];
        $callCount = 0;

        $httpClient = new MockHttpClient(function () use (&$callCount, $mediaResponse) {
            ++$callCount;

            return new MockResponse(json_encode($mediaResponse));
        });

        $proxy = new UserProxy(
            $httpClient,
            $this->createMock(UserCacheService::class),
            new InMemoryCache()
        );

        $firstCall = $proxy->getMedia('media-1');
        $secondCall = $proxy->getMedia('media-1');

        $this->assertSame(1, $callCount, 'Media should be retrieved from cache after the first HTTP request.');
        $this->assertSame($firstCall, $secondCall);
    }
}
