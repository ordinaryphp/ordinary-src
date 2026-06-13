<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests;

use Ordinary\Container\Attribute\Inject;
use Ordinary\Container\Container;
use Ordinary\Container\ContainerBuilder;
use Ordinary\Container\Exception\CircularDependencyException;
use Ordinary\Container\Exception\ContainerException;
use Ordinary\Container\Exception\NotFoundException;
use Ordinary\Container\Tests\Support\AbstractService;
use Ordinary\Container\Tests\Support\CircularA;
use Ordinary\Container\Tests\Support\CircularB;
use Ordinary\Container\Tests\Support\ConcreteA;
use Ordinary\Container\Tests\Support\ConcreteB;
use Ordinary\Container\Tests\Support\ContextualConsumer;
use Ordinary\Container\Tests\Support\DependentService;
use Ordinary\Container\Tests\Support\LazyService;
use Ordinary\Container\Tests\Support\ScopedService;
use Ordinary\Container\Tests\Support\ServiceInterface;
use Ordinary\Container\Tests\Support\SimpleService;
use Ordinary\Container\Tests\Support\TaggedServiceA;
use Ordinary\Container\Tests\Support\TaggedServiceB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    // ── has() ────────────────────────────────────────────────────────────────

    #[Test]
    public function hasReturnsTrueForRegisteredService(): void
    {
        $container = new ContainerBuilder()
            ->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService())
            ->build();

        $this->assertTrue($container->has(SimpleService::class));
    }

    #[Test]
    public function hasReturnsTrueForAlias(): void
    {
        $container = new ContainerBuilder()
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->alias(ServiceInterface::class, ConcreteA::class)
            ->build();

        $this->assertTrue($container->has(ServiceInterface::class));
    }

    #[Test]
    public function hasReturnsFalseForUnknownService(): void
    {
        $container = new ContainerBuilder()->build();

        $this->assertFalse($container->has('unknown'));
    }

    // ── get() / singleton ────────────────────────────────────────────────────

    #[Test]
    public function singletonReturnsSameInstanceOnSubsequentCalls(): void
    {
        $container = new ContainerBuilder()
            ->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService())
            ->build();

        $a = $container->get(SimpleService::class);
        $b = $container->get(SimpleService::class);

        $this->assertSame($a, $b);
    }

    #[Test]
    public function singletonFactoryIsCalledOnlyOnce(): void
    {
        $callCount = 0;

        $container = new ContainerBuilder()
            ->singleton(SimpleService::class, static function () use (&$callCount): SimpleService {
                $callCount++;

                return new SimpleService();
            })
            ->build();

        $container->get(SimpleService::class);
        $container->get(SimpleService::class);

        $this->assertSame(1, $callCount);
    }

    // ── get() / transient ────────────────────────────────────────────────────

    #[Test]
    public function transientReturnsDifferentInstanceEachCall(): void
    {
        $container = new ContainerBuilder()
            ->transient(SimpleService::class, static fn(): SimpleService => new SimpleService())
            ->build();

        $a = $container->get(SimpleService::class);
        $b = $container->get(SimpleService::class);

        $this->assertNotSame($a, $b);
    }

    // ── get() / alias ─────────────────────────────────────────────────────────

    #[Test]
    public function aliasResolvesToConcrete(): void
    {
        $container = new ContainerBuilder()
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->alias(ServiceInterface::class, ConcreteA::class)
            ->build();

        $svc = $container->get(ServiceInterface::class);

        $this->assertInstanceOf(ConcreteA::class, $svc);
    }

    #[Test]
    public function aliasChainsAreFollowed(): void
    {
        $container = new ContainerBuilder()
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->alias('alias1', ConcreteA::class)
            ->alias('alias2', 'alias1')
            ->build();

        $this->assertInstanceOf(ConcreteA::class, $container->get('alias2'));
    }

    // ── get() / not found ─────────────────────────────────────────────────────

    #[Test]
    public function getThrowsNotFoundForUnregisteredService(): void
    {
        $container = new ContainerBuilder()->build();

        $this->expectException(NotFoundException::class);
        $container->get('not.registered');
    }

    // ── get() / factory dependency ───────────────────────────────────────────

    #[Test]
    public function factoryCanRequestOtherServicesFromContainer(): void
    {
        $container = new ContainerBuilder()
            ->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService())
            ->singleton(DependentService::class, static fn($c): DependentService => new DependentService($c->get(SimpleService::class)))
            ->build();

        $svc = $container->get(DependentService::class);

        $this->assertInstanceOf(DependentService::class, $svc);
        $this->assertInstanceOf(SimpleService::class, $svc->simple);
    }

    // ── circular dependency ───────────────────────────────────────────────────

    #[Test]
    public function circularDependencyThrowsException(): void
    {
        $container = new ContainerBuilder()
            ->singleton(CircularA::class, static fn($c): CircularA => new CircularA($c->get(CircularB::class)))
            ->singleton(CircularB::class, static fn($c): CircularB => new CircularB($c->get(CircularA::class)))
            ->build();

        $this->expectException(CircularDependencyException::class);
        $container->get(CircularA::class);
    }

    #[Test]
    public function circularDependencyExceptionContainsChain(): void
    {
        $container = new ContainerBuilder()
            ->singleton(CircularA::class, static fn($c): CircularA => new CircularA($c->get(CircularB::class)))
            ->singleton(CircularB::class, static fn($c): CircularB => new CircularB($c->get(CircularA::class)))
            ->build();

        try {
            $container->get(CircularA::class);
            self::fail('Expected CircularDependencyException was not thrown.');
        } catch (CircularDependencyException $circularDependencyException) {
            $this->assertContains(CircularA::class, $circularDependencyException->chain());
            $this->assertContains(CircularB::class, $circularDependencyException->chain());
        }
    }

    // ── tagged() ─────────────────────────────────────────────────────────────

    #[Test]
    public function taggedYieldsAllServicesForTag(): void
    {
        $container = new ContainerBuilder()
            ->singleton(TaggedServiceA::class, static fn(): TaggedServiceA => new TaggedServiceA(), 'processor')
            ->singleton(TaggedServiceB::class, static fn(): TaggedServiceB => new TaggedServiceB(), 'processor')
            ->build();

        $results = \iterator_to_array($container->tagged('processor'));

        $this->assertCount(2, $results);
        $this->assertInstanceOf(TaggedServiceA::class, $results[0]);
        $this->assertInstanceOf(TaggedServiceB::class, $results[1]);
    }

    #[Test]
    public function taggedYieldsNothingForUnknownTag(): void
    {
        $container = new ContainerBuilder()
            ->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService())
            ->build();

        $results = \iterator_to_array($container->tagged('nonexistent'));

        $this->assertEmpty($results);
    }

    #[Test]
    public function taggedRespectsSingletonScope(): void
    {
        $container = new ContainerBuilder()
            ->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService(), 'item')
            ->build();

        $first = \iterator_to_array($container->tagged('item'));
        $second = \iterator_to_array($container->tagged('item'));

        $this->assertSame($first[0], $second[0]);
    }

    // ── scope() ──────────────────────────────────────────────────────────────

    #[Test]
    public function scopedServiceIsIsolatedPerScope(): void
    {
        $i = 0;
        $container = new ContainerBuilder()
            ->scoped(ScopedService::class, static function () use (&$i): ScopedService {
                return new ScopedService((string) ++$i);
            })
            ->build();

        $scope1 = $container->scope('req-1');
        $scope2 = $container->scope('req-2');

        $a = $scope1->get(ScopedService::class);
        $b = $scope1->get(ScopedService::class);
        $c = $scope2->get(ScopedService::class);

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }

    #[Test]
    public function singletonIsSharedAcrossScopes(): void
    {
        $container = new ContainerBuilder()
            ->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService())
            ->build();

        $scope1 = $container->scope('req-1');
        $scope2 = $container->scope('req-2');

        $this->assertSame($scope1->get(SimpleService::class), $scope2->get(SimpleService::class));
    }

    #[Test]
    public function scopedServiceThrowsWhenAccessedOutsideScope(): void
    {
        $container = new ContainerBuilder()
            ->scoped(ScopedService::class, static fn(): ScopedService => new ScopedService('x'))
            ->build();

        $this->expectException(ContainerException::class);
        $container->get(ScopedService::class);
    }

    // ── lazy singleton ────────────────────────────────────────────────────────

    #[Test]
    public function lazySingletonProxiesAreSameInstance(): void
    {
        $container = new ContainerBuilder()
            ->lazySingleton(LazyService::class, static fn(): LazyService => new LazyService())
            ->build();

        $a = $container->get(LazyService::class);
        $b = $container->get(LazyService::class);

        $this->assertSame($a, $b);
    }

    #[Test]
    public function lazySingletonProxyDeferredUntilAccess(): void
    {
        $tracker = new class {
            public bool $initialized = false;
        };

        $container = new ContainerBuilder()
            ->lazySingleton(LazyService::class, static function () use ($tracker): LazyService {
                $tracker->initialized = true;

                return new LazyService();
            })
            ->build();

        $proxy = $container->get(LazyService::class);

        $this->assertFalse($tracker->initialized, 'Factory must not run before the proxy is accessed.');
        $this->assertInstanceOf(LazyService::class, $proxy);

        // Trigger initialization via method call
        $proxy->value();

        $this->assertTrue($tracker->initialized, 'Factory must run on first method access.');
    }

    // ── contextual bindings ────────────────────────────────────────────────

    #[Test]
    public function contextualBindingOverridesDefaultForConsumer(): void
    {
        $container = new ContainerBuilder()
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->singleton(ConcreteB::class, static fn(): ConcreteB => new ConcreteB())
            ->alias(ServiceInterface::class, ConcreteA::class)
            ->autowire(ContextualConsumer::class)
            ->when(ContextualConsumer::class)
            ->needs(ServiceInterface::class)
            ->give(ConcreteB::class)
            ->build();

        $consumer = $container->get(ContextualConsumer::class);

        $this->assertInstanceOf(ContextualConsumer::class, $consumer);
        $this->assertInstanceOf(ConcreteB::class, $consumer->service);
    }

    #[Test]
    public function contextualBindingWithClosureIsInvoked(): void
    {
        $container = new ContainerBuilder()
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->autowire(ContextualConsumer::class)
            ->when(ContextualConsumer::class)
            ->needs(ServiceInterface::class)
            ->give(static fn(): ConcreteB => new ConcreteB())
            ->build();

        $consumer = $container->get(ContextualConsumer::class);

        $this->assertInstanceOf(ConcreteB::class, $consumer->service);
    }

    #[Test]
    public function defaultBindingUsedWhenNoContextualOverride(): void
    {
        $container = new ContainerBuilder()
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->singleton(ConcreteB::class, static fn(): ConcreteB => new ConcreteB())
            ->alias(ServiceInterface::class, ConcreteA::class)
            ->autowire(ContextualConsumer::class)
            ->build();

        $consumer = $container->get(ContextualConsumer::class);

        $this->assertInstanceOf(ConcreteA::class, $consumer->service);
    }

    // ── PSR-11 interface ──────────────────────────────────────────────────────

    #[Test]
    public function implementsPsrContainerInterface(): void
    {
        $container = new ContainerBuilder()->build();

        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    // ── contextual bindings via manual factory get() ───────────────────────

    #[Test]
    public function contextualStringBindingAppliedViaGetInsideManualFactory(): void
    {
        $container = new ContainerBuilder()
            ->singleton(ContextualConsumer::class, static fn($c): ContextualConsumer => new ContextualConsumer($c->get(ServiceInterface::class)))
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->singleton(ConcreteB::class, static fn(): ConcreteB => new ConcreteB())
            ->when(ContextualConsumer::class)->needs(ServiceInterface::class)->give(ConcreteB::class)
            ->build();

        $consumer = $container->get(ContextualConsumer::class);

        $this->assertInstanceOf(ConcreteB::class, $consumer->service);
    }

    #[Test]
    public function contextualClosureBindingAppliedViaGetInsideManualFactory(): void
    {
        $container = new ContainerBuilder()
            ->singleton(ContextualConsumer::class, static fn($c): ContextualConsumer => new ContextualConsumer($c->get(ServiceInterface::class)))
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->when(ContextualConsumer::class)->needs(ServiceInterface::class)->give(static fn(): ConcreteB => new ConcreteB())
            ->build();

        $consumer = $container->get(ContextualConsumer::class);

        $this->assertInstanceOf(ConcreteB::class, $consumer->service);
    }

    #[Test]
    public function lazySingletonProxyThrowsWhenFactoryReturnsWrongType(): void
    {
        $container = new ContainerBuilder()
            ->lazySingleton(LazyService::class, static fn(): SimpleService => new SimpleService())
            ->build();

        $proxy = $container->get(LazyService::class);
        $this->assertInstanceOf(LazyService::class, $proxy);
        $this->expectException(ContainerException::class);
        $proxy->value();
    }

    // ── lazy singleton fallbacks ───────────────────────────────────────────

    #[Test]
    public function lazySingletonFallsBackToEagerForFinalClass(): void
    {
        $container = new ContainerBuilder()
            ->lazySingleton(SimpleService::class, static fn(): SimpleService => new SimpleService())
            ->build();

        $a = $container->get(SimpleService::class);
        $b = $container->get(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $a);
        $this->assertSame($a, $b);
    }

    #[Test]
    public function lazySingletonFallsBackToEagerForNonClassStringId(): void
    {
        $container = new ContainerBuilder()
            ->lazySingleton('my.service', static fn(): SimpleService => new SimpleService())
            ->build();

        $service = $container->get('my.service');

        $this->assertInstanceOf(SimpleService::class, $service);
    }

    // ── alias depth limit ─────────────────────────────────────────────────

    #[Test]
    public function aliasDepthLimitThrowsContainerException(): void
    {
        $builder = new ContainerBuilder();

        for ($i = 0; $i < 34; $i++) {
            $builder->alias('alias' . $i, 'alias' . ($i + 1));
        }

        $builder->singleton('alias34', static fn(): SimpleService => new SimpleService());
        $container = $builder->build();

        $this->expectException(ContainerException::class);
        $container->get('alias0');
    }

    // ── call() ────────────────────────────────────────────────────────────

    #[Test]
    public function callResolvesClosureParametersByType(): void
    {
        $container = new ContainerBuilder()->build();

        $result = $container->call(static fn(SimpleService $s): SimpleService => $s);

        $this->assertInstanceOf(SimpleService::class, $result);
    }

    #[Test]
    public function callResolvesArrayCallable(): void
    {
        $container = new ContainerBuilder()->build();

        $target = new class {
            public function handle(SimpleService $s): SimpleService
            {
                return $s;
            }
        };

        $result = $container->call($target->handle(...));

        $this->assertInstanceOf(SimpleService::class, $result);
    }

    #[Test]
    public function callResolvesInvokableObject(): void
    {
        $container = new ContainerBuilder()->build();

        $handler = new class {
            public function __invoke(SimpleService $s): SimpleService
            {
                return $s;
            }
        };

        $result = $container->call($handler);

        $this->assertInstanceOf(SimpleService::class, $result);
    }

    #[Test]
    public function callUsesNamedParameterOverrides(): void
    {
        $expected = new SimpleService();
        $container = new ContainerBuilder()->build();

        $result = $container->call(
            static fn(SimpleService $s): SimpleService => $s,
            parameters: ['s' => $expected],
        );

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function callUsesDefaultValueForUnresolvablePrimitive(): void
    {
        $container = new ContainerBuilder()->build();

        $result = $container->call(static fn(string $name = 'default'): string => $name);

        $this->assertSame('default', $result);
    }

    #[Test]
    public function callHonoursInjectAttributeOnClosureParameter(): void
    {
        $container = new ContainerBuilder()->build();

        $result = $container->call(
            static fn(
                #[Inject(ConcreteB::class)]
                ServiceInterface $svc,
            ): ServiceInterface => $svc,
        );

        $this->assertInstanceOf(ConcreteB::class, $result);
    }

    #[Test]
    public function callThrowsForUnresolvablePrimitiveWithNoDefault(): void
    {
        $container = new ContainerBuilder()->build();

        $this->expectException(ContainerException::class);
        $container->call(static fn(string $host): string => $host);
    }

    // ── auto-wiring (zero-config) ─────────────────────────────────────────

    #[Test]
    public function autoWiringResolvesUnregisteredInstantiableClass(): void
    {
        $container = new ContainerBuilder()->build();

        $this->assertInstanceOf(SimpleService::class, $container->get(SimpleService::class));
    }

    #[Test]
    public function autoWiringCachesResolutionAsSingleton(): void
    {
        $container = new ContainerBuilder()->build();

        $this->assertSame($container->get(SimpleService::class), $container->get(SimpleService::class));
    }

    #[Test]
    public function autoWiringResolvesTransitiveDependencies(): void
    {
        $container = new ContainerBuilder()->build();

        $svc = $container->get(DependentService::class);

        $this->assertInstanceOf(DependentService::class, $svc);
        $this->assertInstanceOf(SimpleService::class, $svc->simple);
    }

    #[Test]
    public function autoWiringSharesSingletonAcrossChildScopes(): void
    {
        $container = new ContainerBuilder()->build();

        $this->assertSame(
            $container->scope('req-1')->get(SimpleService::class),
            $container->scope('req-2')->get(SimpleService::class),
        );
    }

    #[Test]
    public function autoWiringThrowsNotFoundForNonExistentClass(): void
    {
        $container = new ContainerBuilder()->build();

        $this->expectException(NotFoundException::class);
        $container->get('App\NonExistent\Class');
    }

    #[Test]
    public function autoWiringThrowsNotFoundForInterface(): void
    {
        $container = new ContainerBuilder()->build();

        $this->expectException(NotFoundException::class);
        $container->get(ServiceInterface::class);
    }

    #[Test]
    public function autoWiringThrowsNotFoundForAbstractClass(): void
    {
        $container = new ContainerBuilder()->build();

        $this->expectException(NotFoundException::class);
        $container->get(AbstractService::class);
    }

    #[Test]
    public function autoWiringDetectsCircularDependencies(): void
    {
        $container = new ContainerBuilder()->build();

        $this->expectException(CircularDependencyException::class);
        $container->get(CircularA::class);
    }

    #[Test]
    public function autoWiringCanBeDisabledViaBuilder(): void
    {
        $container = new ContainerBuilder()->useAutowiring(false)->build();

        $this->expectException(NotFoundException::class);
        $container->get(SimpleService::class);
    }

    // ── has() with auto-wiring ─────────────────────────────────────────────

    #[Test]
    public function hasReturnsTrueForInstantiableClassWhenAutoWiringEnabled(): void
    {
        $container = new ContainerBuilder()->build();

        $this->assertTrue($container->has(SimpleService::class));
    }

    #[Test]
    public function hasReturnsFalseForInterfaceWhenAutoWiringEnabled(): void
    {
        $container = new ContainerBuilder()->build();

        $this->assertFalse($container->has(ServiceInterface::class));
    }

    #[Test]
    public function hasReturnsFalseForInstantiableClassWhenAutoWiringDisabled(): void
    {
        $container = new ContainerBuilder()->useAutowiring(false)->build();

        $this->assertFalse($container->has(SimpleService::class));
    }
}
