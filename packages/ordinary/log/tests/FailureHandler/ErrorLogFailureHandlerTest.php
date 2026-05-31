<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\FailureHandler;

use DateTimeImmutable;
use Ordinary\Log\FailureHandler\ErrorLogFailureHandler;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogFailureException;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorLogFailureHandler::class)]
final class ErrorLogFailureHandlerTest extends TestCase
{
    #[Test]
    public function it_does_not_throw(): void
    {
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'phpunit_errorlog_');
        $this->assertIsString($tmpFile);
        $previousErrorLog = \ini_set('error_log', $tmpFile);

        try {
            $e = new LogFailureException(
                new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable()),
                new \RuntimeException('fail'),
            );

            new ErrorLogFailureHandler()->handleLogFailure($e);
            $this->addToAssertionCount(1);
        } finally {
            \ini_set('error_log', $previousErrorLog !== false ? $previousErrorLog : '');
            \unlink($tmpFile);
        }
    }

    #[Test]
    public function it_writes_to_error_log_with_driver_class_and_message(): void
    {
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'phpunit_errorlog_');
        $this->assertIsString($tmpFile);

        $previousErrorLog = \ini_set('error_log', $tmpFile);

        try {
            $stream = \fopen('php://memory', 'w+');
            $this->assertNotFalse($stream);

            $driver = $this->createStub(LogDriverInterface::class);
            $logItem = new LogEntry(LogLevel::Error, 'connection lost', new DateTimeImmutable());
            $e = new LogFailureException($logItem, new \RuntimeException('timeout'), $driver);

            new ErrorLogFailureHandler()->handleLogFailure($e);

            $contents = \file_get_contents($tmpFile);
            $this->assertIsString($contents);
            $this->assertStringContainsString('error', $contents);
            $this->assertStringContainsString('connection lost', $contents);
            $this->assertStringContainsString('timeout', $contents);
        } finally {
            \ini_set('error_log', $previousErrorLog !== false ? $previousErrorLog : '');
            \unlink($tmpFile);
        }
    }
}
