<?php

declare(strict_types=1);

namespace Ordinary\Container;

use Ordinary\Container\Attribute\Inject;
use Ordinary\Container\Attribute\Singleton as SingletonAttr;
use Ordinary\Container\Attribute\Transient as TransientAttr;
use Ordinary\Container\Exception\ContainerException;

/**
 * Mutable registry for service definitions.
 *
 * Collect all bindings, aliases, tags, contextual overrides, and providers here,
 * then call build() to obtain a locked Container ready for resolution.
 */
final class ContainerBuilder
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, string> abstract id → concrete id */
    private array $aliases = [];

    /** @var array<string, array<string, string|\Closure(ContainerInterface): mixed>> consumer → abstract → concrete */
    private array $contextual = [];

    private bool $autoWire = true;

    /**
     * Controls whether the container automatically resolves unregistered instantiable classes.
     *
     * Enabled by default. When disabled, get() throws NotFoundException for any id with no
     * explicit registration. Disable when you want strict, auditable wiring with no implicit resolution.
     */
    public function useAutowiring(bool $enabled = true): static
    {
        $this->autoWire = $enabled;

        return $this;
    }

    /**
     * Registers a singleton service: one shared instance for the lifetime of the container.
     *
     * Tags may be passed as trailing string arguments and are equivalent to calling tag() after.
     *
     * @param \Closure(ContainerInterface): mixed $factory
     */
    public function singleton(string $id, \Closure $factory, string ...$tags): static
    {
        return $this->bind($id, $factory, Scope::Singleton, false, ...$tags);
    }

    /**
     * Registers a singleton that is resolved lazily via a PHP 8.4 native lazy proxy.
     *
     * The proxy is returned immediately; the real instance is only created on first
     * property or method access. Requires $id to be a concrete, non-final class name.
     * Falls back to eager singleton resolution for final classes and when the id is not
     * a class name.
     *
     * @param \Closure(ContainerInterface): mixed $factory
     */
    public function lazySingleton(string $id, \Closure $factory, string ...$tags): static
    {
        return $this->bind($id, $factory, Scope::Singleton, true, ...$tags);
    }

    /**
     * Registers a transient service: a new instance is created on every call to get().
     *
     * @param \Closure(ContainerInterface): mixed $factory
     */
    public function transient(string $id, \Closure $factory, string ...$tags): static
    {
        return $this->bind($id, $factory, Scope::Transient, false, ...$tags);
    }

    /**
     * Registers a scoped service: one instance per named scope created via Container::scope().
     *
     * @param \Closure(ContainerInterface): mixed $factory
     */
    public function scoped(string $id, \Closure $factory, string ...$tags): static
    {
        return $this->bind($id, $factory, Scope::Scoped, false, ...$tags);
    }

    /**
     * Registers a service using reflection to resolve constructor dependencies automatically.
     *
     * Parameters are resolved by type from the container. Use #[Inject] on constructor
     * parameters to override individual bindings. Primitive parameters must have defaults
     * or be supplied via $overrides.
     *
     * @param class-string $class
     * @param array<string, mixed> $overrides Named constructor parameter overrides (e.g. ['host' => 'localhost']).
     */
    public function autowire(string $class, Scope $scope = Scope::Singleton, array $overrides = [], string ...$tags): static
    {
        $factory = $this->buildAutowireFactory($class, $overrides);

        return $this->bind($class, $factory, $scope, false, ...$tags);
    }

    /**
     * Registers a service by reading the #[Singleton] or #[Transient] attribute on the class.
     *
     * Defaults to Scope::Singleton when neither attribute is present.
     *
     * @param class-string $class
     * @param array<string, mixed> $overrides Named constructor parameter overrides.
     */
    public function autowireFromAttribute(string $class, array $overrides = [], string ...$tags): static
    {
        $scope = $this->readScopeAttribute($class);

        return $this->autowire($class, $scope, $overrides, ...$tags);
    }

    /**
     * Declares $abstract as an alias that resolves to the $concrete service id.
     *
     * Alias chains are followed at resolution time. Cycles in the alias chain are
     * broken after 32 hops and produce a ContainerException.
     *
     * @param class-string|string $abstract
     * @param class-string|string $concrete
     */
    public function alias(string $abstract, string $concrete): static
    {
        $this->aliases[$abstract] = $concrete;

        return $this;
    }

    /**
     * Adds one or more tags to an already-registered service.
     *
     * Tags may also be passed directly to singleton(), transient(), scoped(), or autowire().
     */
    public function tag(string $id, string ...$tags): static
    {
        if (!isset($this->definitions[$id])) {
            throw new ContainerException(\sprintf('Cannot tag unknown service "%s".', $id));
        }

        $existing = $this->definitions[$id];
        $this->definitions[$id] = new ServiceDefinition(
            $existing->id,
            $existing->scope,
            $existing->factory,
            [...$existing->tags, ...\array_values($tags)],
            $existing->lazy,
        );

        return $this;
    }

    /**
     * Begins a contextual binding declaration.
     *
     * Usage: $builder->when(Consumer::class)->needs(Abstract::class)->give(Concrete::class)
     *
     * @param non-empty-list<class-string|string>|class-string|string $consumers
     */
    public function when(array|string $consumers): ContextualBindingBuilder
    {
        $list = \is_array($consumers) ? $consumers : [$consumers];

        return new ContextualBindingBuilder($this, $list);
    }

    /**
     * Registers a service provider, immediately calling its register() method.
     */
    public function provider(ServiceProviderInterface $provider): static
    {
        $provider->register($this);

        return $this;
    }

    /**
     * Stores a contextual override for a consumer/abstract pair.
     *
     * Called by ContextualBindingBuilder::give(); not intended for direct use.
     *
     * @param string|\Closure(ContainerInterface): mixed $concrete
     */
    public function addContextualBinding(string $consumer, string $abstract, string|\Closure $concrete): void
    {
        $this->contextual[$consumer] ??= [];
        $this->contextual[$consumer][$abstract] = $concrete;
    }

    /**
     * Returns a locked Container built from the current definitions, aliases, and contextual bindings.
     *
     * After calling build() the builder may continue to accept new registrations for
     * constructing additional containers, but the returned Container is immutable.
     */
    public function build(): Container
    {
        return new Container($this->definitions, $this->aliases, $this->contextual, autoWire: $this->autoWire);
    }

    /** @param \Closure(ContainerInterface): mixed $factory */
    private function bind(string $id, \Closure $factory, Scope $scope, bool $lazy, string ...$tags): static
    {
        $this->definitions[$id] = new ServiceDefinition($id, $scope, $factory, \array_values($tags), $lazy);

        return $this;
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $overrides
     *
     * @return \Closure(ContainerInterface): object
     */
    private function buildAutowireFactory(string $class, array $overrides): \Closure
    {
        if (!\class_exists($class)) {
            throw new ContainerException(\sprintf('Cannot autowire "%s": class does not exist.', $class));
        }

        $reflector = new \ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new ContainerException(\sprintf('Cannot autowire "%s": class is not instantiable.', $class));
        }

        return static function (ContainerInterface $container) use ($class, $overrides): object {
            $reflector = new \ReflectionClass($class);
            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                return $reflector->newInstance();
            }

            $args = [];

            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();

                if (\array_key_exists($name, $overrides)) {
                    $args[] = $overrides[$name];

                    continue;
                }

                $injectAttrs = $param->getAttributes(Inject::class);

                if ($injectAttrs !== []) {
                    /** @var Inject $inject */
                    $inject = $injectAttrs[0]->newInstance();
                    $args[] = $container->get($inject->id);

                    continue;
                }

                $type = $param->getType();

                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $typeName = $type->getName();
                    $args[] = $container instanceof Container
                        ? $container->resolveContextual($class, $typeName)
                        : $container->get($typeName);

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
                    'Cannot autowire parameter "$%s" of "%s": no type hint, no default, and not nullable.',
                    $name,
                    $class,
                ));
            }

            return $reflector->newInstanceArgs($args);
        };
    }

    /** @param class-string $class */
    private function readScopeAttribute(string $class): Scope
    {
        if (!\class_exists($class)) {
            throw new ContainerException(\sprintf('Cannot read scope attribute from "%s": class does not exist.', $class));
        }

        $reflector = new \ReflectionClass($class);

        if ($reflector->getAttributes(SingletonAttr::class) !== []) {
            return Scope::Singleton;
        }

        if ($reflector->getAttributes(TransientAttr::class) !== []) {
            return Scope::Transient;
        }

        return Scope::Singleton;
    }
}
