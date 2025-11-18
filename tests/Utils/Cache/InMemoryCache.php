<?php

declare(strict_types=1);

namespace App\Tests\Utils\Cache;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Simple in-memory cache used to satisfy CacheInterface during unit tests.
 */
final class InMemoryCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $storage = [];

    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
    {
        if (array_key_exists($key, $this->storage)) {
            return $this->storage[$key];
        }

        $item = new class($key) implements ItemInterface {
            public function __construct(private readonly string $key)
            {
            }

            public function getKey(): string
            {
                return $this->key;
            }

            public function get(): mixed
            {
                return null;
            }

            public function isHit(): bool
            {
                return false;
            }

            public function set(mixed $value): static
            {
                return $this;
            }

            public function expiresAt($expiration): static
            {
                return $this;
            }

            public function expiresAfter($time): static
            {
                return $this;
            }

            public function tag($tags): static
            {
                return $this;
            }
        };

        $this->storage[$key] = $callback($item);

        return $this->storage[$key];
    }

    public function delete(string $key): bool
    {
        unset($this->storage[$key]);

        return true;
    }
}
