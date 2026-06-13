<?php

declare(strict_types=1);

namespace Ordinary\Container;

/**
 * Fluent builder for contextual bindings.
 *
 * Determines which concrete implementation to inject when a specific set of consumers
 * requests a given abstract type. Chain: when()->needs()->give().
 *
 * Works for both manually-factored services (by intercepting get() calls on the shared
 * container) and autowired services (by overriding parameter resolution per consumer).
 */
final class ContextualBindingBuilder
{
    private string $abstract = '';

    /**
     * @param non-empty-list<string> $consumers
     */
    public function __construct(
        private readonly ContainerBuilder $builder,
        private readonly array $consumers,
    ) {}

    /**
     * Specifies the abstract type (class or interface id) to override for the consumers.
     *
     * @param class-string|string $abstract
     */
    public function needs(string $abstract): static
    {
        $this->abstract = $abstract;

        return $this;
    }

    /**
     * Specifies the concrete implementation to inject.
     *
     * Accepts a concrete class id (string) or a factory closure for inline definitions.
     *
     * @param class-string|string|\Closure(ContainerInterface): mixed $concrete
     */
    public function give(string|\Closure $concrete): ContainerBuilder
    {
        foreach ($this->consumers as $consumer) {
            $this->builder->addContextualBinding($consumer, $this->abstract, $concrete);
        }

        return $this->builder;
    }
}
