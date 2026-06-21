<?php

declare(strict_types=1);

namespace Ordinary\Config;

use InvalidArgumentException;

/**
 * Config with per-key resolver chains and layered source fallback.
 *
 * Keys registered via define() are resolved by running their handler chain.
 * Each handler receives the key and a $next callable; calling $next($key)
 * continues to the next handler in the chain. When all handlers are exhausted,
 * $next consults the configured sources in registration order. Returning from a
 * handler without calling $next short-circuits further resolution.
 *
 * Keys with no registered handlers fall through to sources directly, identical
 * to LayeredConfig behaviour.
 *
 * Calling define() for the same key a second time replaces the previous chain.
 * define() returns the same instance for fluent chaining.
 */
final class PipelineConfig implements Config
{
    /** @var list<Config> */
    private readonly array $sources;

    /** @var array<string, non-empty-list<\Closure(string, \Closure(string): mixed): mixed>> */
    private array $definitions = [];

    public function __construct(Config ...$sources)
    {
        $this->sources = \array_values($sources);
    }

    /**
     * Registers a resolver chain for the given key.
     *
     * Each resolver: fn(string $key, \Closure $next): mixed
     *
     * @param \Closure(string, \Closure(string): mixed): mixed ...$resolvers
     *
     * @throws InvalidArgumentException when called with no resolvers.
     */
    public function define(string $key, \Closure ...$resolvers): static
    {
        if ($resolvers === []) {
            throw new InvalidArgumentException(
                'define() requires at least one resolver.',
            );
        }

        $this->definitions[$key] = \array_values($resolvers);

        return $this;
    }

    public function has(string $key): bool
    {
        if (isset($this->definitions[$key])) {
            return true;
        }

        return \array_any($this->sources, fn(Config $source): bool => $source->has($key));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->definitions[$key])) {
            return $this->dispatch($key, $this->definitions[$key], 0);
        }

        foreach ($this->sources as $source) {
            if ($source->has($key)) {
                return $source->get($key);
            }
        }

        return $default;
    }

    /**
     * @param non-empty-list<\Closure(string, \Closure(string): mixed): mixed> $resolvers
     */
    private function dispatch(string $key, array $resolvers, int $index): mixed
    {
        $resolver = $resolvers[$index] ?? null;

        if ($resolver === null) {
            return $this->resolveFromSources($key);
        }

        $next = fn(string $k): mixed => $this->dispatch($k, $resolvers, $index + 1);

        return ($resolver)($key, $next);
    }

    private function resolveFromSources(string $key): mixed
    {
        foreach ($this->sources as $source) {
            if ($source->has($key)) {
                return $source->get($key);
            }
        }

        return null;
    }
}
