<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\FailureHandler;

use DateTimeImmutable;
use Ordinary\Log\FailureHandler\SyslogFailureHandler;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogFailureException;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyslogFailureHandler::class)]
final class SyslogFailureHandlerTest extends TestCase
{
    #[Test]
    public function it_does_not_throw(): void
    {
        $e = new LogFailureException(
            new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable()),
            new \RuntimeException('fail'),
        );

        new SyslogFailureHandler()->handleLogFailure($e);
        $this->addToAssertionCount(1);
    }
}
