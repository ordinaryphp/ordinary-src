<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use DateTimeImmutable;
use Ordinary\Log\Driver\SyslogDriver;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\SynchronousDriverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyslogDriver::class)]
final class SyslogDriverTest extends TestCase
{
    #[Test]
    public function it_implements_synchronous_driver_interface(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(SynchronousDriverInterface::class, new SyslogDriver());
    }

    #[Test]
    public function it_does_not_throw_for_any_level(): void
    {
        $driver = new SyslogDriver();

        foreach (LogLevel::cases() as $level) {
            $driver->handleLog(new LogEntry($level, 'test message', new DateTimeImmutable()));
        }

        $this->addToAssertionCount(1);
    }
}
