<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use function array_unique;
use function mb_strtolower;
use function trim;

final readonly class ContentModerationService
{
    /**
     * @param array<int, string> $bannedWords
     */
    public function __construct(private array $bannedWords)
    {
    }

    /**
     * @return array<int, string>
     */
    public function detectViolations(?string $content): array
    {
        if ($content === null || trim($content) === '') {
            return [];
        }

        $normalized = mb_strtolower($content);
        $violations = [];

        foreach ($this->bannedWords as $word) {
            $word = trim($word);

            if ($word === '') {
                continue;
            }

            if (str_contains($normalized, mb_strtolower($word))) {
                $violations[] = $word;
            }
        }

        return array_values(array_unique($violations));
    }

    public function isContentAllowed(?string $content): bool
    {
        return $this->detectViolations($content) === [];
    }
}
