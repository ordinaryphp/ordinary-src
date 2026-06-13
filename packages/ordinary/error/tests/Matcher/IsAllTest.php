<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests\Matcher;

use Ordinary\Error\Matcher\CallableMatcher;
use Ordinary\Error\Matcher\IsAll;
use Ordinary\Error\ThrowableMatcherInterface;
use PHPUnit\Framework\TestCase;

final class IsAllTest extends TestCase
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

    public function testReturnsTrueWithNoMatchers(): void
    {
        $matcher = new IsAll();

        $this->assertTrue($matcher->matches(new \RuntimeException('test')));
    }

    public function testReturnsTrueWhenAllMatchersPass(): void
    {
        $matcher = new IsAll($this->alwaysTrue(), $this->alwaysTrue(), $this->alwaysTrue());

        $this->assertTrue($matcher->matches(new \RuntimeException('test')));
    }

    public function testReturnsFalseWhenAnyMatcherFails(): void
    {
        $matcher = new IsAll($this->alwaysTrue(), $this->alwaysFalse(), $this->alwaysTrue());

        $this->assertFalse($matcher->matches(new \RuntimeException('test')));
    }

    public function testReturnsFalseWhenAllMatchersFail(): void
    {
        $matcher = new IsAll($this->alwaysFalse(), $this->alwaysFalse());

        $this->assertFalse($matcher->matches(new \RuntimeException('test')));
    }

    public function testShortCircuitsOnFirstFailure(): void
    {
        $secondCalled = false;
        $second = new CallableMatcher(static function (\Throwable $t) use (&$secondCalled): bool {
            $secondCalled = true;

            return true;
        });

        $matcher = new IsAll($this->alwaysFalse(), $second);
        $matcher->matches(new \RuntimeException('test'));

        $this->assertFalse($secondCalled, 'Second matcher should not be evaluated after first fails');
    }
}
