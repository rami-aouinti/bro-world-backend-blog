<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Moderation;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use function sprintf;

final readonly class ModerationWarningService
{
    private const string CACHE_TAG = 'moderation_warnings';
    private const int WARNING_TTL = 604800; // 7 days

    public function __construct(
        private TagAwareCacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<int, string> $violations
     */
    public function recordWarning(?string $userId, string $subjectType, string $subjectId, array $violations): void
    {
        $owner = $userId ?? 'anonymous';
        $warningCount = $this->incrementWarningCount($owner);

        $this->logger->warning('Content removed by moderation.', [
            'userId' => $owner,
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
            'violations' => $violations,
            'warnings' => $warningCount,
        ]);
    }

    private function incrementWarningCount(string $userId): int
    {
        $cacheKey = sprintf('moderation_warning_%s', $userId);

        $count = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->tag([self::CACHE_TAG]);
            $item->expiresAfter(self::WARNING_TTL);

            return 0;
        });

        $count++;

        $this->cache->delete($cacheKey);

        $this->cache->get($cacheKey, function (ItemInterface $item) use ($count) {
            $item->tag([self::CACHE_TAG]);
            $item->expiresAfter(self::WARNING_TTL);

            return $count;
        });

        return $count;
    }
}
