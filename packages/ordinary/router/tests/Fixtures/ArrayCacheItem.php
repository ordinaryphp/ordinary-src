<?php

declare(strict_types=1);

namespace Ordinary\Router\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;

/**
 * Minimal PSR-6 cache item for testing Psr6Cache.
 */
final class ArrayCacheItem implements CacheItemInterface
{
    private mixed $value;

    public function __construct(
        private readonly string $key,
        mixed $value,
        private readonly bool $hit,
    ) {
        $this->value = $value;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
