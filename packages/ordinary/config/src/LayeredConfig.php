<?php

declare(strict_types=1);

namespace Ordinary\Config;

/**
 * Resolves config keys against an ordered stack of sources.
 *
 * Sources are checked in registration order (first-in, first-checked). The
 * first source that owns the key provides the value; remaining sources are not
 * consulted. This means earlier sources have higher priority — register
 * overrides before defaults.
 */
final readonly class LayeredConfig implements Config
{
    /** @var list<Config> */
    private array $sources;

    public function __construct(Config ...$sources)
    {
        $this->sources = \array_values($sources);
    }

    public function has(string $key): bool
    {
        return \array_any($this->sources, fn(Config $source): bool => $source->has($key));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        foreach ($this->sources as $source) {
            if ($source->has($key)) {
                return $source->get($key);
            }
        }

        return $default;
    }
}
