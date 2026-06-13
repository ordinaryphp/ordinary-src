<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests\FailureHandler;

use Ordinary\Error\FailureHandler\ErrorLogFailureHandler;
use Ordinary\Error\ThrowableHandlerInterface;
use PHPUnit\Framework\TestCase;

final class ErrorLogFailureHandlerTest extends TestCase
{
    public function testHandleFailureWritesToErrorLog(): void
    {
        $handler = new ErrorLogFailureHandler();

        $fakeHandler = new class implements ThrowableHandlerInterface {
            public function handle(\Throwable $throwable): void {}
        };

        $failure = new \RuntimeException('something went wrong');
        $original = new \LogicException('original problem');

        // Capture error_log output via a custom error log destination
        $logFile = \tempnam(\sys_get_temp_dir(), 'ordinary_error_test_');
        $this->assertIsString($logFile);
        $prevDest = \ini_set('error_log', $logFile);

        try {
            $handler->handleFailure($fakeHandler, $failure, $original);
        } finally {
            \ini_set('error_log', $prevDest !== false ? $prevDest : '');
        }

        $contents = \file_get_contents($logFile);
        \unlink($logFile);

        $this->assertIsString($contents);
        $this->assertStringContainsString('RuntimeException', $contents);
        $this->assertStringContainsString('something went wrong', $contents);
        $this->assertStringContainsString('LogicException', $contents);
    }
}
