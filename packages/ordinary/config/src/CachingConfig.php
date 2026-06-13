<?php

declare(strict_types=1);

namespace Ordinary\Config;

/**
 * Decorates a Config source with an in-memory read-through cache.
 *
 * Each key is resolved from the inner source at most once. Subsequent accesses
 * return the cached value without consulting the inner source, even if that
 * source changes afterwards. Both hits (resolved values, including null) and
 * misses (absent keys) are cached.
 *
 * Place this around expensive sources — file reads, remote calls — when used
 * inside a LayeredConfig stack to avoid redundant I/O.
 */
final class CachingConfig implements Config
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    /** @var array<string, true> */
    private array $absent = [];

    public function __construct(private readonly Config $inner) {}

    public function has(string $key): bool
    {
        if (\array_key_exists($key, $this->resolved)) {
            return true;
        }

        if (isset($this->absent[$key])) {
            return false;
        }

        if ($this->inner->has($key)) {
            return true;
        }

        $this->absent[$key] = true;

        return false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (\array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        if (isset($this->absent[$key])) {
            return $default;
        }

        if (!$this->inner->has($key)) {
            $this->absent[$key] = true;

            return $default;
        }

        return $this->resolved[$key] = $this->inner->get($key);
    }
}
