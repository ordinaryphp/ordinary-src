<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Exception;

use Ordinary\Container\Exception\CircularDependencyException;
use Ordinary\Container\Exception\ContainerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;

#[CoversClass(CircularDependencyException::class)]
final class CircularDependencyExceptionTest extends TestCase
{
    #[Test]
    public function extendsContainerException(): void
    {
        $e = new CircularDependencyException(['A', 'B', 'A']);

        $this->assertInstanceOf(ContainerException::class, $e);
        $this->assertInstanceOf(ContainerExceptionInterface::class, $e);
    }

    #[Test]
    public function chainIsPreserved(): void
    {
        $chain = ['ServiceA', 'ServiceB', 'ServiceA'];
        $e = new CircularDependencyException($chain);

        $this->assertSame($chain, $e->chain());
    }

    #[Test]
    public function messageContainsArrowSeparatedChain(): void
    {
        $e = new CircularDependencyException(['A', 'B', 'A']);

        $this->assertStringContainsString('A → B → A', $e->getMessage());
        $this->assertStringContainsString('Circular dependency detected', $e->getMessage());
    }

    #[Test]
    public function emptyChainProducesMessage(): void
    {
        $e = new CircularDependencyException([]);

        $this->assertStringContainsString('Circular dependency detected', $e->getMessage());
    }
}
