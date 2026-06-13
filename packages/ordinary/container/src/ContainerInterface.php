<?php

declare(strict_types=1);

namespace Ordinary\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Extends PSR-11 with callable invocation, tag-based service collection, and scoped child containers.
 *
 * All services registered under a given tag can be resolved together via tagged().
 * A named scope creates an isolated container that tracks its own scoped instances.
 * Arbitrary callables can be invoked with their parameters autowired via call().
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Invokes the given callable, resolving its parameters by type from the container.
     *
     * Non-built-in type hints are resolved via get(). Use $parameters to supply named
     * overrides for parameters that cannot be resolved by type (e.g. primitives, scalars).
     * The #[Inject] attribute is honoured on individual parameters.
     *
     * @param callable(mixed...): mixed $callable
     * @param array<string, mixed> $parameters Named parameter values applied before type resolution.
     */
    public function call(callable $callable, array $parameters = []): mixed;

    /**
     * Resolves and yields all services registered under the given tag.
     *
     * Iteration order matches registration order. Each yielded value is the fully
     * resolved service instance, subject to its own scope rules.
     *
     * @return iterable<int, mixed>
     */
    public function tagged(string $tag): iterable;

    /**
     * Creates a child container that manages its own Scope::Scoped instance cache.
     *
     * Singleton and transient services are shared with the parent. Scoped services
     * within the returned container are independent of every other scope.
     */
    public function scope(string $name): static;
}
