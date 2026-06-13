<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests\Matcher;

use Ordinary\Error\Matcher\IsNot;
use Ordinary\Error\ThrowableMatcherInterface;
use PHPUnit\Framework\TestCase;

final class IsNotTest extends TestCase
{
    public function testInvertsTrueToFalse(): void
    {
        $alwaysTrue = new class implements ThrowableMatcherInterface {
            public function matches(\Throwable $throwable): bool
            {
                return true;
            }
        };

        $matcher = new IsNot($alwaysTrue);

        $this->assertFalse($matcher->matches(new \RuntimeException('test')));
    }

    public function testInvertsFalseToTrue(): void
    {
        $alwaysFalse = new class implements ThrowableMatcherInterface {
            public function matches(\Throwable $throwable): bool
            {
                return false;
            }
        };

        $matcher = new IsNot($alwaysFalse);

        $this->assertTrue($matcher->matches(new \RuntimeException('test')));
    }
}
