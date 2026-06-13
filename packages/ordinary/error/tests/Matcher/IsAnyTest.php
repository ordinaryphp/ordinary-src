<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests\Matcher;

use Ordinary\Error\Matcher\CallableMatcher;
use Ordinary\Error\Matcher\IsAny;
use Ordinary\Error\ThrowableMatcherInterface;
use PHPUnit\Framework\TestCase;

final class IsAnyTest extends TestCase
{
    private function alwaysTrue(): ThrowableMatcherInterface
    {
        return new class implements ThrowableMatcherInterface {
            public function matches(\Throwable $throwable): bool
            {
                return true;
            }
        };
    }

    private function alwaysFalse(): ThrowableMatcherInterface
    {
        return new class implements ThrowableMatcherInterface {
            public function matches(\Throwable $throwable): bool
            {
                return false;
            }
        };
    }

    public function testReturnsFalseWithNoMatchers(): void
    {
        $matcher = new IsAny();

        $this->assertFalse($matcher->matches(new \RuntimeException('test')));
    }

    public function testReturnsTrueWhenAnyMatcherPasses(): void
    {
        $matcher = new IsAny($this->alwaysFalse(), $this->alwaysTrue(), $this->alwaysFalse());

        $this->assertTrue($matcher->matches(new \RuntimeException('test')));
    }

    public function testReturnsFalseWhenAllMatchersFail(): void
    {
        $matcher = new IsAny($this->alwaysFalse(), $this->alwaysFalse());

        $this->assertFalse($matcher->matches(new \RuntimeException('test')));
    }

    public function testReturnsTrueWhenAllMatchersPass(): void
    {
        $matcher = new IsAny($this->alwaysTrue(), $this->alwaysTrue());

        $this->assertTrue($matcher->matches(new \RuntimeException('test')));
    }

    public function testShortCircuitsOnFirstMatch(): void
    {
        $secondCalled = false;
        $second = new CallableMatcher(static function (\Throwable $t) use (&$secondCalled): bool {
            $secondCalled = true;

            return false;
        });

        $matcher = new IsAny($this->alwaysTrue(), $second);
        $matcher->matches(new \RuntimeException('test'));

        $this->assertFalse($secondCalled, 'Second matcher should not be evaluated after first passes');
    }
}
