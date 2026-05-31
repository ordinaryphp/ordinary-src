<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogFailureException;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogFailureException::class)]
final class LogFailureExceptionTest extends TestCase
{
    #[Test]
    public function it_exposes_the_log_item(): void
    {
        $logItem = new LogEntry(LogLevel::Error, 'something failed', new DateTimeImmutable());
        $e = new LogFailureException($logItem, new \RuntimeException('original'));

        $this->assertSame($logItem, $e->logItem);
    }

    #[Test]
    public function it_chains_the_previous_exception(): void
    {
        $previous = new \RuntimeException('original');
        $e = new LogFailureException(new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable()), $previous);

        $this->assertSame($previous, $e->getPrevious());
    }

    #[Test]
    public function it_includes_level_and_message_in_exception_message(): void
    {
        $logItem = new LogEntry(LogLevel::Warning, 'disk full', new DateTimeImmutable());
        $e = new LogFailureException($logItem, new \RuntimeException('write error'));

        $this->assertStringContainsString('warning', $e->getMessage());
        $this->assertStringContainsString('disk full', $e->getMessage());
        $this->assertStringContainsString('write error', $e->getMessage());
    }

    #[Test]
    public function it_returns_null_failing_driver_by_default(): void
    {
        $e = new LogFailureException(
            new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()),
            new \RuntimeException('fail'),
        );

        $this->assertNotInstanceOf(LogDriverInterface::class, $e->failingDriver);
    }

    #[Test]
    public function it_exposes_the_failing_driver_when_provided(): void
    {
        $driver = $this->createStub(LogDriverInterface::class);
        $e = new LogFailureException(
            new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable()),
            new \RuntimeException('fail'),
            $driver,
        );

        $this->assertSame($driver, $e->failingDriver);
    }
}
