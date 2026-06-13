<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests;

use Ordinary\Error\CallableHandler;
use PHPUnit\Framework\TestCase;

final class CallableHandlerTest extends TestCase
{
    public function testHandleDelegatesToCallable(): void
    {
        $received = null;
        $throwable = new \RuntimeException('test');

        $handler = new CallableHandler(static function (\Throwable $t) use (&$received): void {
            $received = $t;
        });

        $handler->handle($throwable);

        $this->assertSame($throwable, $received);
    }

    public function testHandlePropagatesExceptionFromCallable(): void
    {
        $handler = new CallableHandler(static function (\Throwable $t): never {
            throw new \LogicException('from callable');
        });

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('from callable');

        $handler->handle(new \RuntimeException('original'));
    }
}
