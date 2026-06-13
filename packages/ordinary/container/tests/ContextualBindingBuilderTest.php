<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests;

use Ordinary\Container\ContainerBuilder;
use Ordinary\Container\ContextualBindingBuilder;
use Ordinary\Container\Tests\Support\ConcreteA;
use Ordinary\Container\Tests\Support\ConcreteB;
use Ordinary\Container\Tests\Support\ContextualConsumer;
use Ordinary\Container\Tests\Support\ServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContextualBindingBuilder::class)]
final class ContextualBindingBuilderTest extends TestCase
{
    #[Test]
    public function whenReturnsContextualBindingBuilder(): void
    {
        $builder = new ContainerBuilder();
        $result = $builder->when(ContextualConsumer::class);

        $this->assertInstanceOf(ContextualBindingBuilder::class, $result);
    }

    #[Test]
    public function giveReturnsContainerBuilder(): void
    {
        $builder = new ContainerBuilder();
        $result = $builder
            ->when(ContextualConsumer::class)
            ->needs(ServiceInterface::class)
            ->give(ConcreteA::class);

        $this->assertInstanceOf(ContainerBuilder::class, $result);
    }

    #[Test]
    public function multipleConsumersCanShareOneBinding(): void
    {
        $builder = new ContainerBuilder();
        $builder
            ->singleton(ConcreteA::class, static fn(): ConcreteA => new ConcreteA())
            ->singleton(ConcreteB::class, static fn(): ConcreteB => new ConcreteB())
            ->alias(ServiceInterface::class, ConcreteA::class)
            ->autowire(ContextualConsumer::class)
            ->when([ContextualConsumer::class])
            ->needs(ServiceInterface::class)
            ->give(ConcreteB::class);

        $container = $builder->build();
        $consumer = $container->get(ContextualConsumer::class);

        $this->assertInstanceOf(ConcreteB::class, $consumer->service);
    }

    #[Test]
    public function contextualBindingWithStringArrayConsumers(): void
    {
        $builder = new ContainerBuilder();
        $result = $builder->when(['ConsumerA', 'ConsumerB']);

        $this->assertInstanceOf(ContextualBindingBuilder::class, $result);
    }
}
