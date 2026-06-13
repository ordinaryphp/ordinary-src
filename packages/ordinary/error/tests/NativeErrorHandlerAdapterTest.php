<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests;

use Ordinary\Error\NativeErrorHandlerAdapter;
use PHPUnit\Framework\TestCase;

final class NativeErrorHandlerAdapterTest extends TestCase
{
    public function testHandleInvokesCallableWithErrorExceptionProperties(): void
    {
        $capturedArgs = [];

        $adapter = new NativeErrorHandlerAdapter(
            static function (int $errno, string $errstr, string $errfile, int $errline) use (&$capturedArgs): bool {
                $capturedArgs = [$errno, $errstr, $errfile, $errline];

                return true;
            },
        );

        $exception = new \ErrorException('Division by zero', 0, E_WARNING, '/app/Foo.php', 99);
        $adapter->handle($exception);

        $this->assertSame([E_WARNING, 'Division by zero', '/app/Foo.php', 99], $capturedArgs);
    }

    public function testHandleIgnoresNonErrorExceptionThrowables(): void
    {
        $called = false;
        $adapter = new NativeErrorHandlerAdapter(
            static function () use (&$called): bool {
                $called = true;

                return true;
            },
        );

        $adapter->handle(new \RuntimeException('not an error'));

        $this->assertFalse($called);
    }
}
