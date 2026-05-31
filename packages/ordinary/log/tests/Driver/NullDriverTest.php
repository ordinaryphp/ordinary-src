<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use DateTimeImmutable;
use Ordinary\Log\Driver\NullDriver;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\SynchronousDriverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullDriver::class)]
final class NullDriverTest extends TestCase
{
    #[Test]
    public function it_silently_discards_log_items(): void
    {
        $driver = new NullDriver();

        $this->expectNotToPerformAssertions();
        $driver->handleLog(new LogEntry(LogLevel::Error, 'discarded', new DateTimeImmutable()));
    }

    #[Test]
    public function it_implements_synchronous_driver_interface(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(SynchronousDriverInterface::class, new NullDriver());
    }
}
