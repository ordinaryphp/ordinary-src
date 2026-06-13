<?php

declare(strict_types=1);

namespace Ordinary\Config;

/**
 * Mutable in-memory config store.
 *
 * Suitable for runtime overrides, test fixtures, and as a source in
 * LayeredConfig or PipelineConfig stacks.
 */
final class RuntimeConfig implements Config
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data = []) {}

    /**
     * Sets or overwrites the value for the given key.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Removes the key entirely so that has() returns false for it.
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return \array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }
}
