<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use DateTimeImmutable;
use Ordinary\Log\Driver\FingersCrossedDriver;
use Ordinary\Log\FlushableInterface;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FingersCrossedDriver::class)]
final class FingersCrossedDriverTest extends TestCase
{
    private function item(LogLevel $level, string $message = 'msg'): LogEntry
    {
        return new LogEntry($level, $message, new DateTimeImmutable());
    }

    #[Test]
    public function items_below_threshold_are_buffered_and_not_dispatched(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new FingersCrossedDriver($inner, LogLevel::Error);
        $driver->handleLog($this->item(LogLevel::Debug));
        $driver->handleLog($this->item(LogLevel::Info));
        $driver->handleLog($this->item(LogLevel::Warning));

        $this->assertSame(0, $callCount);
        $this->assertFalse($driver->isActivated());
    }

    #[Test]
    public function activation_flushes_buffer_and_dispatches_trigger(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item->message;
        });

        $driver = new FingersCrossedDriver($inner, LogLevel::Error);
        $driver->handleLog($this->item(LogLevel::Info, 'before-1'));
        $driver->handleLog($this->item(LogLevel::Info, 'before-2'));
        $driver->handleLog($this->item(LogLevel::Error, 'trigger'));

        $this->assertTrue($driver->isActivated());
        $this->assertSame(['before-1', 'before-2', 'trigger'], $received);
    }

    #[Test]
    public function items_after_activation_go_directly_to_inner(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item->message;
        });

        $driver = new FingersCrossedDriver($inner, LogLevel::Error);
        $driver->handleLog($this->item(LogLevel::Error, 'trigger'));
        $driver->handleLog($this->item(LogLevel::Info, 'after-1'));
        $driver->handleLog($this->item(LogLevel::Debug, 'after-2'));

        $this->assertSame(['trigger', 'after-1', 'after-2'], $received);
    }

    #[Test]
    public function flush_without_activation_discards_buffer(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new FingersCrossedDriver($inner, LogLevel::Error);
        $driver->handleLog($this->item(LogLevel::Info));
        $driver->handleLog($this->item(LogLevel::Warning));
        $driver->flush();

        $this->assertSame(0, $callCount);
    }

    #[Test]
    public function flush_after_activation_cascades_to_flushable_inner(): void
    {
        $inner = $this->createMock(FlushableFingersCrossedInner::class);
        $inner->expects($this->once())->method('flush');

        $driver = new FingersCrossedDriver($inner, LogLevel::Error);
        $driver->handleLog($this->item(LogLevel::Error, 'trigger'));
        $driver->flush();
    }

    #[Test]
    public function flush_without_activation_does_not_cascade_to_flushable_inner(): void
    {
        $inner = $this->createMock(FlushableFingersCrossedInner::class);
        $inner->expects($this->never())->method('flush');

        $driver = new FingersCrossedDriver($inner, LogLevel::Error);
        $driver->handleLog($this->item(LogLevel::Info));
        $driver->flush();
    }

    #[Test]
    public function reset_on_flush_returns_to_buffering_state(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item->message;
        });

        $driver = new FingersCrossedDriver($inner, LogLevel::Error, resetOnFlush: true);
        $driver->handleLog($this->item(LogLevel::Error, 'first'));
        $driver->flush();

        $this->assertFalse($driver->isActivated());

        $driver->handleLog($this->item(LogLevel::Info, 'buffered-after-reset'));
        $driver->flush();

        // buffered-after-reset was never dispatched since it's below threshold
        $this->assertNotContains('buffered-after-reset', $received);
    }

    #[Test]
    public function max_buffer_drops_oldest_item_when_full(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item->message;
        });

        $driver = new FingersCrossedDriver($inner, LogLevel::Error, maxBuffer: 2);
        $driver->handleLog($this->item(LogLevel::Info, 'oldest'));
        $driver->handleLog($this->item(LogLevel::Info, 'middle'));
        $driver->handleLog($this->item(LogLevel::Info, 'newest'));
        $driver->handleLog($this->item(LogLevel::Error, 'trigger'));

        // oldest was dropped; middle + newest + trigger dispatched
        $this->assertNotContains('oldest', $received);
        $this->assertContains('middle', $received);
        $this->assertContains('newest', $received);
        $this->assertContains('trigger', $received);
    }

    #[Test]
    public function activation_level_exactly_at_threshold_triggers(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new FingersCrossedDriver($inner, LogLevel::Warning);
        $driver->handleLog($this->item(LogLevel::Warning, 'exactly at threshold'));

        $this->assertSame(1, $callCount);
        $this->assertTrue($driver->isActivated());
    }

    #[Test]
    public function it_implements_flushable_interface(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(
            FlushableInterface::class,
            new FingersCrossedDriver($this->createStub(LogDriverInterface::class)),
        );
    }
}

abstract class FlushableFingersCrossedInner implements LogDriverInterface, FlushableInterface {}
