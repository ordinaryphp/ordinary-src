<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use DateTimeImmutable;
use Ordinary\Log\Driver\BufferingDriver;
use Ordinary\Log\FlushableInterface;
use Ordinary\Log\LogBatch;
use Ordinary\Log\LogBatchDriverInterface;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferingDriver::class)]
final class BufferingDriverTest extends TestCase
{
    private function makeItem(string $message = 'msg', LogLevel $level = LogLevel::Info): LogEntry
    {
        return new LogEntry($level, $message, new DateTimeImmutable());
    }

    #[Test]
    public function it_does_not_dispatch_until_flush(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new BufferingDriver($inner);
        $driver->handleLog($this->makeItem());
        $driver->handleLog($this->makeItem());

        $this->assertSame(0, $callCount);
        $driver->flush(); // clear so __destruct is a no-op
    }

    #[Test]
    public function flush_dispatches_all_buffered_items_in_order(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(
            function (LogEntryInterface $item) use (&$received): void {
                $received[] = $item->message;
            },
        );

        $driver = new BufferingDriver($inner);
        $driver->handleLog($this->makeItem('first'));
        $driver->handleLog($this->makeItem('second'));
        $driver->handleLog($this->makeItem('third'));
        $driver->flush();

        $this->assertSame(['first', 'second', 'third'], $received);
    }

    #[Test]
    public function flush_on_empty_buffer_is_a_noop(): void
    {
        $inner = $this->createMock(LogDriverInterface::class);
        $inner->expects($this->never())->method('handleLog');

        $driver = new BufferingDriver($inner);
        $driver->flush();
    }

    #[Test]
    public function buffer_is_cleared_after_flush(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new BufferingDriver($inner);
        $driver->handleLog($this->makeItem());
        $driver->flush();
        $driver->flush();

        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function flush_uses_handle_log_batch_when_inner_implements_it(): void
    {
        $receivedBatch = null;
        $inner = $this->createMock(LogBatchDriverInterface::class);
        $inner->expects($this->never())->method('handleLog');
        $inner->expects($this->once())
            ->method('handleLogBatch')
            ->willReturnCallback(function (LogBatch $batch) use (&$receivedBatch): void {
                $receivedBatch = $batch;
            });

        $a = $this->makeItem('a');
        $b = $this->makeItem('b');

        $driver = new BufferingDriver($inner);
        $driver->handleLog($a);
        $driver->handleLog($b);
        $driver->flush();

        $this->assertInstanceOf(LogBatch::class, $receivedBatch);
        $this->assertSame([$a, $b], $receivedBatch->items);
    }

    #[Test]
    public function auto_flush_triggers_at_threshold(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new BufferingDriver($inner, flushAfter: 3);
        $driver->handleLog($this->makeItem());
        $driver->handleLog($this->makeItem());
        $this->assertSame(0, $callCount);

        $driver->handleLog($this->makeItem());
        // @phpstan-ignore method.impossibleType
        $this->assertSame(3, $callCount);
    }

    #[Test]
    public function auto_flush_zero_disables_auto_flush(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new BufferingDriver($inner, flushAfter: 0);
        for ($i = 0; $i < 100; $i++) {
            $driver->handleLog($this->makeItem());
        }

        $this->assertSame(0, $callCount);
        $driver->flush(); // clear so __destruct is a no-op
    }

    #[Test]
    public function destruct_flushes_remaining_buffer(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(
            function (LogEntryInterface $item) use (&$received): void {
                $received[] = $item->message;
            },
        );

        $run = function () use ($inner): void {
            $driver = new BufferingDriver($inner);
            $driver->handleLog(new LogEntry(LogLevel::Info, 'alpha', new DateTimeImmutable()));
            $driver->handleLog(new LogEntry(LogLevel::Info, 'beta', new DateTimeImmutable()));
            // $driver goes out of scope → __destruct() → flush()
        };
        $run();

        $this->assertSame(['alpha', 'beta'], $received);
    }

    #[Test]
    public function it_implements_flushable_interface(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(FlushableInterface::class, new BufferingDriver($this->createStub(LogDriverInterface::class)));
    }
}
