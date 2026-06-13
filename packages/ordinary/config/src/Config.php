<?php

declare(strict_types=1);

namespace Ordinary\Config;

/**
 * Contract for resolving string keys to arbitrary configuration values.
 *
 * Implementations must treat key presence and a null value as distinct states:
 * a key that exists with a null value satisfies has(), while a missing key does not.
 */
interface Config
{
    /**
     * Returns true when the key exists in this source, regardless of its value.
     */
    public function has(string $key): bool;

    /**
     * Returns the value for the given key, or $default when the key is absent.
     */
    public function get(string $key, mixed $default = null): mixed;
}
