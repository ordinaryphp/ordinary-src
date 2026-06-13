<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests\Matcher;

use Ordinary\Error\Matcher\CallableMatcher;
use PHPUnit\Framework\TestCase;

final class CallableMatcherTest extends TestCase
{
    public function testMatchesDelegatesToCallable(): void
    {
        $throwable = new \RuntimeException('test');
        $received = null;

        $matcher = new CallableMatcher(static function (\Throwable $t) use (&$received): bool {
            $received = $t;

            return true;
        });

        $result = $matcher->matches($throwable);

        $this->assertTrue($result);
        $this->assertSame($throwable, $received);
    }

    public function testReturnsFalseWhenCallableReturnsFalse(): void
    {
        $matcher = new CallableMatcher(static fn(\Throwable $t): bool => false);

        $this->assertFalse($matcher->matches(new \RuntimeException('test')));
    }
}
