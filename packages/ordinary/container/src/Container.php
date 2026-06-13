<?php

declare(strict_types=1);

namespace Ordinary\Container;

use Ordinary\Container\Attribute\Inject;
use Ordinary\Container\Exception\CircularDependencyException;
use Ordinary\Container\Exception\ContainerException;
use Ordinary\Container\Exception\NotFoundException;

/**
 * Locked PSR-11 container produced by ContainerBuilder::build().
 *
 * Resolves services according to their Scope: singletons are cached for the lifetime
 * of this instance, transients are freshly created on every call, and scoped services
 * are cached per child scope created via scope().
 *
 * Lazy singletons are backed by PHP 8.4 native lazy proxies when the service id is a
 * concrete, non-final class; otherwise they fall back to eager resolution.
 *
 * Contextual bindings registered on the builder are applied transparently: any service
 * that calls get() while being resolved will receive the override configured for its
 * consumer/abstract pair, with no changes needed in the factory closure.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, mixed> Resolved singleton instances (shared with child scopes). */
    private array $singletons = [];

    /** @var array<string, mixed> Scoped instances (owned by this scope only). */
    private array $scoped = [];

    /** @var array<string, mixed> Auto-resolved singleton instances (shared with child scopes). */
    private array $autoResolved = [];

    /** @var array<string, true> Services currently being resolved; used for cycle detection. */
    private array $resolving = [];

    /** @var list<string> Stack of service ids being resolved; drives contextual lookup. */
    private array $resolvingStack = [];

    /**
     * @param array<string, ServiceDefinition> $definitions
     * @param array<string, string> $aliases
     * @param array<string, array<string, string|\Closure(ContainerInterface): mixed>> $contextual
     */
    public function __construct(
        private readonly array $definitions,
        private readonly array $aliases,
        private readonly array $contextual,
        private readonly ?string $scopeName = null,
        private readonly bool $autoWire = true,
    ) {}

    /**
     * {@inheritDoc}
     *
     * Applies contextual binding overrides when called from within another service's factory.
     */
    public function get(string $id): mixed
    {
        $consumer = $this->currentConsumer();

        if ($consumer !== null && isset($this->contextual[$consumer][$id])) {
            $concrete = $this->contextual[$consumer][$id];

            if ($concrete instanceof \Closure) {
                return ($concrete)($this);
            }

            $id = $concrete;
        }

        return $this->resolve($id);
    }

    public function has(string $id): bool
    {
        if (isset($this->definitions[$id]) || isset($this->aliases[$id])) {
            return true;
        }

        if ($this->autoWire && \class_exists($id)) {
            return new \ReflectionClass($id)->isInstantiable();
        }

        return false;
    }

    /**
     * {@inheritDoc}
     *
     * Parameters with non-built-in type hints are resolved from the container. Use
     * $parameters for scalars, primitives, or any override that should bypass resolution.
     *
     * @param callable(mixed...): mixed $callable
     */
    public function call(callable $callable, array $parameters = []): mixed
    {
        $reflector = $this->reflectCallable($callable);

        return $callable(...$this->resolveCallableParameters($reflector->getParameters(), $parameters));
    }

    /**
     * {@inheritDoc}
     *
     * Each yielded value is the fully resolved service instance for that definition.
     */
    public function tagged(string $tag): iterable
    {
        foreach ($this->definitions as $definition) {
            if (\in_array($tag, $definition->tags, true)) {
                yield $this->resolve($definition->id);
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * The returned container shares singleton state with this container but owns an
     * independent scoped instance cache. It also inherits all definitions, aliases,
     * and contextual bindings.
     */
    public function scope(string $name): static
    {
        /** @var static $child */
        $child = new self($this->definitions, $this->aliases, $this->contextual, $name, $this->autoWire);
        // Share singleton caches by reference so resolved instances are truly global across scopes.
        $child->singletons = &$this->singletons;
        $child->autoResolved = &$this->autoResolved;

        return $child;
    }

    /**
     * Resolves the contextual concrete for a given consumer/abstract pair.
     *
     * Called by autowiring factories to apply per-consumer overrides without requiring
     * factories to be aware of the contextual binding mechanism.
     *
     * @internal Used by ContainerBuilder-generated autowiring closures.
     */
    public function resolveContextual(string $consumer, string $abstract): mixed
    {
        if (isset($this->contextual[$consumer][$abstract])) {
            $concrete = $this->contextual[$consumer][$abstract];

            return $concrete instanceof \Closure ? ($concrete)($this) : $this->resolve($concrete);
        }

        return $this->get($abstract);
    }

    private function resolve(string $id): mixed
    {
        $id = $this->followAliases($id);

        if (!isset($this->definitions[$id])) {
            if ($this->autoWire) {
                return $this->resolveAutowired($id);
            }

            throw new NotFoundException(\sprintf('No binding found for "%s".', $id));
        }

        $definition = $this->definitions[$id];

        return match ($definition->scope) {
            Scope::Singleton => $this->resolveSingleton($definition),
            Scope::Transient => $this->invokeFactory($definition),
            Scope::Scoped => $this->resolveScoped($definition),
        };
    }

    private function resolveSingleton(ServiceDefinition $definition): mixed
    {
        if (\array_key_exists($definition->id, $this->singletons)) {
            return $this->singletons[$definition->id];
        }

        $instance = $definition->lazy ? $this->makeLazyProxy($definition) : $this->invokeFactory($definition);

        $this->singletons[$definition->id] = $instance;

        return $instance;
    }

    private function resolveScoped(ServiceDefinition $definition): mixed
    {
        if ($this->scopeName === null) {
            throw new ContainerException(\sprintf(
                'Service "%s" is scoped but no active scope exists. Call scope() first.',
                $definition->id,
            ));
        }

        if (\array_key_exists($definition->id, $this->scoped)) {
            return $this->scoped[$definition->id];
        }

        $instance = $this->invokeFactory($definition);
        $this->scoped[$definition->id] = $instance;

        return $instance;
    }

    private function invokeFactory(ServiceDefinition $definition): mixed
    {
        if (isset($this->resolving[$definition->id])) {
            $chain = [...\array_keys($this->resolving), $definition->id];
            /** @var list<string> $chain */
            throw new CircularDependencyException($chain);
        }

        $this->resolving[$definition->id] = true;
        $this->resolvingStack[] = $definition->id;

        try {
            return ($definition->factory)($this);
        } finally {
            \array_pop($this->resolvingStack);
            unset($this->resolving[$definition->id]);
        }
    }

    /**
     * Creates a PHP 8.4 lazy proxy for the given singleton definition.
     *
     * Falls back to eager resolution for final classes, abstract classes, interfaces,
     * and ids that are not class names — all scenarios where a proxy cannot be created.
     */
    private function makeLazyProxy(ServiceDefinition $definition): mixed
    {
        if (!\class_exists($definition->id)) {
            return $this->invokeFactory($definition);
        }

        $reflector = new \ReflectionClass($definition->id);

        if ($reflector->isFinal() || $reflector->isAbstract()) {
            return $this->invokeFactory($definition);
        }

        return $reflector->newLazyProxy(
            function (object $proxy) use ($definition): object {
                $result = $this->invokeFactory($definition);

                if (!($result instanceof $definition->id)) {
                    throw new ContainerException(\sprintf(
                        'Lazy proxy factory for "%s" returned %s.',
                        $definition->id,
                        \get_debug_type($result),
                    ));
                }

                return $result;
            },
        );
    }

    /**
     * Resolves an unregistered class by reflecting its constructor and resolving parameters.
     *
     * Only reached when auto-wiring is enabled and no explicit definition exists for $id.
     * The result is cached as a singleton and shared across child scopes.
     */
    private function resolveAutowired(string $id): mixed
    {
        if (\array_key_exists($id, $this->autoResolved)) {
            return $this->autoResolved[$id];
        }

        if (!\class_exists($id)) {
            throw new NotFoundException(\sprintf('No binding found for "%s".', $id));
        }

        $reflector = new \ReflectionClass($id);

        if (!$reflector->isInstantiable()) {
            throw new NotFoundException(\sprintf('No binding found for "%s".', $id));
        }

        if (isset($this->resolving[$id])) {
            $chain = [...\array_keys($this->resolving), $id];
            /** @var list<string> $chain */
            throw new CircularDependencyException($chain);
        }

        $this->resolving[$id] = true;
        $this->resolvingStack[] = $id;

        try {
            $constructor = $reflector->getConstructor();
            $args = $constructor !== null
                ? $this->resolveCallableParameters($constructor->getParameters(), [])
                : [];
            $instance = $reflector->newInstanceArgs($args);
            $this->autoResolved[$id] = $instance;

            return $instance;
        } finally {
            \array_pop($this->resolvingStack);
            unset($this->resolving[$id]);
        }
    }

    /** @param callable(mixed...): mixed $callable */
    private function reflectCallable(callable $callable): \ReflectionFunctionAbstract
    {
        if ($callable instanceof \Closure) {
            return new \ReflectionFunction($callable);
        }

        if (\is_array($callable)) {
            return new \ReflectionMethod($callable[0], $callable[1]);
        }

        if (\is_string($callable) && \str_contains($callable, '::')) {
            [$class, $method] = \explode('::', $callable, 2);

            return new \ReflectionMethod($class, $method);
        }

        if (\is_string($callable)) {
            return new \ReflectionFunction($callable);
        }

        // After Closure, array, and string checks, $callable must be an invokable object.
        // Wrapping via fromCallable() reflects __invoke parameters with full type safety.
        return new \ReflectionFunction(\Closure::fromCallable($callable));
    }

    /**
     * @param list<\ReflectionParameter> $params
     * @param array<string, mixed> $overrides
     *
     * @return list<mixed>
     */
    private function resolveCallableParameters(array $params, array $overrides): array
    {
        $args = [];

        foreach ($params as $param) {
            $name = $param->getName();

            if (\array_key_exists($name, $overrides)) {
                $args[] = $overrides[$name];

                continue;
            }

            $injectAttrs = $param->getAttributes(Inject::class);

            if ($injectAttrs !== []) {
                /** @var Inject $inject */
                $inject = $injectAttrs[0]->newInstance();
                $args[] = $this->get($inject->id);

                continue;
            }

            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();

                continue;
            }

            if ($param->allowsNull()) {
                $args[] = null;

                continue;
            }

            throw new ContainerException(\sprintf(
                'Cannot resolve parameter "$%s": no type hint, no default, and not nullable.',
                $name,
            ));
        }

        return $args;
    }

    private function followAliases(string $id): string
    {
        $depth = 0;

        while (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];

            if (++$depth > 32) {
                throw new ContainerException(\sprintf(
                    'Alias chain depth limit exceeded for "%s". Check for alias cycles.',
                    $id,
                ));
            }
        }

        return $id;
    }

    private function currentConsumer(): ?string
    {
        $count = \count($this->resolvingStack);

        return $count > 0 ? $this->resolvingStack[$count - 1] : null;
    }
}
