<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests;

use Ordinary\Container\Attribute\Singleton as SingletonAttr;
use Ordinary\Container\Attribute\Transient as TransientAttr;
use Ordinary\Container\Container;
use Ordinary\Container\ContainerBuilder;
use Ordinary\Container\Exception\ContainerException;
use Ordinary\Container\ServiceDefinition;
use Ordinary\Container\Tests\Support\AbstractService;
use Ordinary\Container\Tests\Support\ConcreteA;
use Ordinary\Container\Tests\Support\ConcreteB;
use Ordinary\Container\Tests\Support\DependentService;
use Ordinary\Container\Tests\Support\InjectableService;
use Ordinary\Container\Tests\Support\NullableParamService;
use Ordinary\Container\Tests\Support\PrimitiveService;
use Ordinary\Container\Tests\Support\ServiceInterface;
use Ordinary\Container\Tests\Support\SimpleService;
use Ordinary\Container\Tests\Support\TestServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContainerBuilder::class)]
#[CoversClass(ServiceDefinition::class)]
final class ContainerBuilderTest extends TestCase
{
    private ContainerBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
    }

    // ── singleton / transient / scoped ──────────────────────────────────────

    #[Test]
    public function singletonRegistersDefinitionWithSingletonScope(): void
    {
        $this->builder->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService());
        $container = $this->builder->build();

        $a = $container->get(SimpleService::class);
        $b = $container->get(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $a);
        $this->assertSame($a, $b);
    }

    #[Test]
    public function transientRegistersDefinitionWithTransientScope(): void
    {
        $this->builder->transient(SimpleService::class, static fn(): SimpleService => new SimpleService());
        $container = $this->builder->build();

        $a = $container->get(SimpleService::class);
        $b = $container->get(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $a);
        $this->assertNotSame($a, $b);
    }

    #[Test]
    public function scopedRegistersDefinitionWithScopedScope(): void
    {
        $this->builder->scoped(SimpleService::class, static fn(): SimpleService => new SimpleService());
        $container = $this->builder->build();

        $scope = $container->scope('test');

        $a = $scope->get(SimpleService::class);
        $b = $scope->get(SimpleService::class);

        $this->assertSame($a, $b);
    }

    #[Test]
    public function tagsCanBePassedToSingleton(): void
    {
        $this->builder->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService(), 'tag1', 'tag2');
        $container = $this->builder->build();

        $collected = \iterator_to_array($container->tagged('tag1'));

        $this->assertCount(1, $collected);
    }

    // ── lazySingleton ───────────────────────────────────────────────────────

    #[Test]
    public function lazySingletonIsFlaggedInDefinition(): void
    {
        $this->builder->lazySingleton(SimpleService::class, static fn(): SimpleService => new SimpleService());

        $this->assertTrue($this->builder->build()->has(SimpleService::class));
    }

    // ── alias ───────────────────────────────────────────────────────────────

    #[Test]
    public function aliasResolvesToConcreteService(): void
    {
        $this->builder
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->alias(ServiceInterface::class, ConcreteA::class);

        $container = $this->builder->build();

        $this->assertInstanceOf(ConcreteA::class, $container->get(ServiceInterface::class));
    }

    // ── tag ─────────────────────────────────────────────────────────────────

    #[Test]
    public function tagAddsTagToExistingDefinition(): void
    {
        $this->builder->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService());
        $this->builder->tag(SimpleService::class, 'worker');

        $container = $this->builder->build();
        $items = \iterator_to_array($container->tagged('worker'));

        $this->assertCount(1, $items);
    }

    #[Test]
    public function tagThrowsForUnknownService(): void
    {
        $this->expectException(ContainerException::class);
        $this->builder->tag('unknown.service', 'some-tag');
    }

    // ── autowire ────────────────────────────────────────────────────────────

    #[Test]
    public function autowireResolvesConstructorByType(): void
    {
        $this->builder
            ->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService())
            ->autowire(DependentService::class);

        $container = $this->builder->build();
        $svc = $container->get(DependentService::class);

        $this->assertInstanceOf(DependentService::class, $svc);
        $this->assertInstanceOf(SimpleService::class, $svc->simple);
    }

    #[Test]
    public function autowireUsesDefaultValuesForPrimitives(): void
    {
        $this->builder->autowire(PrimitiveService::class, overrides: ['host' => 'localhost']);
        $container = $this->builder->build();

        $svc = $container->get(PrimitiveService::class);

        $this->assertInstanceOf(PrimitiveService::class, $svc);
        $this->assertSame('localhost', $svc->host);
        $this->assertSame(3306, $svc->port);
    }

    #[Test]
    public function autowireOverridesSupersedeDefaults(): void
    {
        $this->builder->autowire(PrimitiveService::class, overrides: ['host' => 'db.example.com', 'port' => 5432]);
        $container = $this->builder->build();

        $svc = $container->get(PrimitiveService::class);

        $this->assertSame('db.example.com', $svc->host);
        $this->assertSame(5432, $svc->port);
    }

    #[Test]
    public function autowireHonoursInjectAttribute(): void
    {
        $this->builder
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->singleton(ConcreteB::class, static fn(): ConcreteB => new ConcreteB())
            ->alias(ServiceInterface::class, ConcreteA::class)
            ->autowire(InjectableService::class);

        $container = $this->builder->build();
        $svc = $container->get(InjectableService::class);

        $this->assertInstanceOf(InjectableService::class, $svc);
        $this->assertInstanceOf(ConcreteB::class, $svc->service);
    }

    #[Test]
    public function autowireThrowsForNonExistentClass(): void
    {
        $this->expectException(ContainerException::class);
        $this->builder->autowire('DoesNotExist\Class');
    }

    #[Test]
    public function autowireThrowsForInterface(): void
    {
        $this->expectException(ContainerException::class);
        $this->builder->autowire(ServiceInterface::class);
    }

    #[Test]
    public function autowireThrowsForAbstractClass(): void
    {
        $this->expectException(ContainerException::class);
        $this->builder->autowire(AbstractService::class);
    }

    #[Test]
    public function autowireUsesNullForNullableBuiltinParameterWithNoDefault(): void
    {
        $this->builder->autowire(NullableParamService::class);
        $container = $this->builder->build();

        $svc = $container->get(NullableParamService::class);

        $this->assertInstanceOf(NullableParamService::class, $svc);
        $this->assertNull($svc->value);
    }

    #[Test]
    public function autowireThrowsForUnresolvablePrimitive(): void
    {
        $this->builder->autowire(PrimitiveService::class);
        $container = $this->builder->build();

        $this->expectException(ContainerException::class);
        $container->get(PrimitiveService::class);
    }

    // ── autowireFromAttribute ───────────────────────────────────────────────

    #[Test]
    public function autowireFromAttributeReadsSingletonAttribute(): void
    {
        $class = new #[SingletonAttr] class {
            public function value(): string
            {
                return 'singleton-attr';
            }
        };

        $this->builder->autowireFromAttribute($class::class);
        $container = $this->builder->build();

        $this->assertSame($container->get($class::class), $container->get($class::class));
    }

    #[Test]
    public function autowireFromAttributeReadsTransientAttribute(): void
    {
        $class = new #[TransientAttr] class {
            public function value(): string
            {
                return 'transient-attr';
            }
        };

        $this->builder->autowireFromAttribute($class::class);
        $container = $this->builder->build();

        $this->assertNotSame($container->get($class::class), $container->get($class::class));
    }

    #[Test]
    public function autowireFromAttributeDefaultsToSingleton(): void
    {
        $class = new class {
            public function value(): string
            {
                return 'no-attr';
            }
        };

        $this->builder->autowireFromAttribute($class::class);
        $container = $this->builder->build();

        $this->assertSame($container->get($class::class), $container->get($class::class));
    }

    #[Test]
    public function autowireFromAttributeThrowsForNonExistentClass(): void
    {
        $this->expectException(ContainerException::class);
        $this->builder->autowireFromAttribute('DoesNotExist\Class');
    }

    // ── provider ────────────────────────────────────────────────────────────

    #[Test]
    public function providerCallsRegisterAndRegistersServices(): void
    {
        $this->builder->provider(new TestServiceProvider());
        $container = $this->builder->build();

        $this->assertTrue($container->has(SimpleService::class));
        $this->assertTrue($container->has(DependentService::class));
    }

    #[Test]
    public function providerReturnsBuilderForChaining(): void
    {
        $result = $this->builder->provider(new TestServiceProvider());

        $this->assertSame($this->builder, $result);
    }

    // ── when / needs / give ─────────────────────────────────────────────────

    #[Test]
    public function contextualBindingIsStoredForLaterUse(): void
    {
        $this->builder
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->singleton(ConcreteB::class, static fn(): ConcreteB => new ConcreteB())
            ->when('SomeConsumer')
            ->needs(ServiceInterface::class)
            ->give(ConcreteB::class);

        // Build succeeds without exception
        $container = $this->builder->build();
        $this->assertInstanceOf(Container::class, $container);
    }

    // ── build ────────────────────────────────────────────────────────────────

    #[Test]
    public function buildReturnsContainer(): void
    {
        $container = $this->builder->build();

        $this->assertInstanceOf(Container::class, $container);
    }

    #[Test]
    public function buildCanBeCalledMultipleTimes(): void
    {
        $this->builder->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService());

        $c1 = $this->builder->build();
        $c2 = $this->builder->build();

        $this->assertNotSame($c1, $c2);
        $this->assertTrue($c1->has(SimpleService::class));
        $this->assertTrue($c2->has(SimpleService::class));
    }

    // ── useAutowiring ────────────────────────────────────────────────────────

    #[Test]
    public function useAutowiringReturnsSelfForChaining(): void
    {
        $result = $this->builder->useAutowiring(false);

        $this->assertSame($this->builder, $result);
    }

    #[Test]
    public function useAutowiringFalseProducesContainerThatRejectsUnregisteredClass(): void
    {
        $container = $this->builder->useAutowiring(false)->build();

        $this->assertFalse($container->has(SimpleService::class));
    }
}
