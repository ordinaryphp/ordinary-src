<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests\Matcher;

use Ordinary\Error\Matcher\IsInstanceOf;
use PHPUnit\Framework\TestCase;

final class IsInstanceOfTest extends TestCase
{
    public function testMatchesExactClass(): void
    {
        $matcher = new IsInstanceOf(\RuntimeException::class);

        $this->assertTrue($matcher->matches(new \RuntimeException('test')));
    }

    public function testMatchesSubclass(): void
    {
        $matcher = new IsInstanceOf(\RuntimeException::class);

        $this->assertTrue($matcher->matches(new \OverflowException('test')));
    }

    public function testMatchesInterface(): void
    {
        $matcher = new IsInstanceOf(\Throwable::class);

        $this->assertTrue($matcher->matches(new \Error('test')));
        $this->assertTrue($matcher->matches(new \Exception('test')));
    }

    public function testDoesNotMatchDifferentClass(): void
    {
        $matcher = new IsInstanceOf(\LogicException::class);

        $this->assertFalse($matcher->matches(new \RuntimeException('test')));
    }

    public function testDoesNotMatchUnrelatedClass(): void
    {
        $matcher = new IsInstanceOf(\InvalidArgumentException::class);

        $this->assertFalse($matcher->matches(new \OverflowException('test')));
    }
}
