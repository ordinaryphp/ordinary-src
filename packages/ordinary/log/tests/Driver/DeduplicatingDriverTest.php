<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use DateTimeImmutable;
use Ordinary\Log\Driver\DeduplicatingDriver;
use Ordinary\Log\FlushableInterface;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Tests\Support\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeduplicatingDriver::class)]
final class DeduplicatingDriverTest extends TestCase
{
    /** @param array<string, mixed> $context */
    private function makeItem(string $message = 'msg', LogLevel $level = LogLevel::Error, array $context = []): LogEntry
    {
        return new LogEntry($level, $message, new DateTimeImmutable(), $context);
    }

    private function makeClock(string $time = '2024-01-01T00:00:00Z'): MutableClock
    {
        return new MutableClock(new DateTimeImmutable($time));
    }

    #[Test]
    public function first_occurrence_is_forwarded_immediately(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item;
        });

        $driver = new DeduplicatingDriver($inner);
        $driver->handleLog($this->makeItem());
        $driver->flush();

        $this->assertCount(1, $received);
        $this->assertArrayNotHasKey('dedup_count', $received[0]->context);
    }

    #[Test]
    public function duplicate_is_not_dispatched_immediately(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new DeduplicatingDriver($inner, windowSeconds: 60);
        $driver->handleLog($this->makeItem('same'));

        $beforeSecondCall = $callCount;
        $driver->handleLog($this->makeItem('same'));

        $this->assertSame($beforeSecondCall, $callCount);
        $driver->flush();
    }

    #[Test]
    public function duplicates_are_flushed_with_dedup_context(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item;
        });

        $clock = $this->makeClock();
        $driver = new DeduplicatingDriver($inner, windowSeconds: 60, clock: $clock);
        $driver->handleLog($this->makeItem('msg'));

        $clock->advance(1);
        $driver->handleLog($this->makeItem('msg'));
        $clock->advance(1);
        $driver->handleLog($this->makeItem('msg'));
        $driver->flush();

        $this->assertCount(2, $received);
        $this->assertArrayNotHasKey('dedup_count', $received[0]->context);
        $this->assertSame(2, $received[1]->context['dedup_count']);
        $times = $received[1]->context['dedup_times'];
        $this->assertIsArray($times);
        $this->assertCount(2, $times);
        $this->assertContainsOnlyInstancesOf(DateTimeImmutable::class, $times);
    }

    #[Test]
    public function dedup_times_record_occurrence_timestamps(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item;
        });

        $clock = new MutableClock(new DateTimeImmutable('2024-06-01T12:00:00Z'));

        $driver = new DeduplicatingDriver($inner, windowSeconds: 60, clock: $clock);
        $driver->handleLog($this->makeItem('msg')); // firstSeen captured internally

        $clock->advance(10);
        $expectedT1 = $clock->now(); // same object the driver will store
        $driver->handleLog($this->makeItem('msg')); // times[0] = expectedT1

        $clock->advance(10);
        $expectedT2 = $clock->now(); // same object the driver will store
        $driver->handleLog($this->makeItem('msg')); // times[1] = expectedT2
        $driver->flush();

        $this->assertCount(2, $received);
        $times = $received[1]->context['dedup_times'];
        $this->assertIsArray($times);
        $this->assertSame($expectedT1, $times[0]);
        $this->assertSame($expectedT2, $times[1]);
    }

    #[Test]
    public function pending_item_is_replaced_with_latest_duplicate(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item;
        });

        $driver = new DeduplicatingDriver($inner, windowSeconds: 60);
        $driver->handleLog($this->makeItem('msg', context: ['version' => 1]));
        $driver->handleLog($this->makeItem('msg', context: ['version' => 2]));
        $driver->handleLog($this->makeItem('msg', context: ['version' => 3]));
        $driver->flush();

        $this->assertCount(2, $received);
        $this->assertSame(3, $received[1]->context['version']);
    }

    #[Test]
    public function no_extra_dispatch_when_no_duplicates_occurred(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new DeduplicatingDriver($inner, windowSeconds: 60);
        $driver->handleLog($this->makeItem('only-once'));
        $driver->flush();

        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function different_messages_are_distinct_fingerprints(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new DeduplicatingDriver($inner);
        $driver->handleLog($this->makeItem('alpha'));
        $driver->handleLog($this->makeItem('beta'));
        $driver->flush();

        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function different_levels_are_distinct_fingerprints(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new DeduplicatingDriver($inner);
        $driver->handleLog($this->makeItem('msg', LogLevel::Info));
        $driver->handleLog($this->makeItem('msg', LogLevel::Error));
        $driver->flush();

        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function custom_fingerprint_is_used(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item;
        });

        $driver = new DeduplicatingDriver(
            $inner,
            fingerprint: fn(LogEntryInterface $item): string => 'fixed-key',
        );
        $driver->handleLog($this->makeItem('first message'));
        $driver->handleLog($this->makeItem('completely different message'));
        $driver->flush();

        // Both share the same fingerprint, so only 2 dispatches: initial + dedup flush
        $this->assertCount(2, $received);
        $this->assertSame(1, $received[1]->context['dedup_count']);
    }

    #[Test]
    public function expired_item_is_evicted_and_re_dispatched_on_next_call(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item;
        });

        $clock = $this->makeClock();
        $driver = new DeduplicatingDriver($inner, windowSeconds: 60, clock: $clock);
        $driver->handleLog($this->makeItem('msg'));

        $clock->advance(61); // push past the 60-second window
        $driver->handleLog($this->makeItem('msg')); // triggers evict() → first entry expired, no dups → dropped

        // The second call is now treated as a fresh first occurrence.
        $driver->flush();

        $this->assertCount(2, $received);
        $this->assertArrayNotHasKey('dedup_count', $received[0]->context);
        $this->assertArrayNotHasKey('dedup_count', $received[1]->context);
    }

    #[Test]
    public function expired_item_with_duplicates_is_flushed_on_eviction(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item;
        });

        $clock = $this->makeClock();
        $driver = new DeduplicatingDriver($inner, windowSeconds: 60, clock: $clock);
        $driver->handleLog($this->makeItem('msg'));

        $clock->advance(10);
        $driver->handleLog($this->makeItem('msg')); // duplicate accumulates

        $clock->advance(55); // total 65s — past the window
        $driver->handleLog($this->makeItem('other')); // triggers evict(): 'msg' expires + dispatches summary; 'other' is dispatched as new

        // received: [initial_msg, dedup_summary_msg, initial_other]
        $this->assertCount(3, $received);
        $this->assertArrayNotHasKey('dedup_count', $received[0]->context); // initial 'msg'
        $this->assertSame(1, $received[1]->context['dedup_count']); // dedup summary flushed on eviction
        $this->assertArrayNotHasKey('dedup_count', $received[2]->context); // initial 'other'
        $driver->flush();
    }

    #[Test]
    public function flush_resets_dedup_state_allowing_fresh_dispatch(): void
    {
        $callCount = 0;
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        $driver = new DeduplicatingDriver($inner, windowSeconds: 60);
        $driver->handleLog($this->makeItem('msg'));
        $driver->flush();
        $driver->handleLog($this->makeItem('msg')); // fresh first occurrence after flush
        $driver->flush();

        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function flush_cascades_to_inner_when_flushable(): void
    {
        $inner = $this->createMock(FlushableLogDriverStub::class);
        $inner->expects($this->atLeastOnce())->method('flush');

        $driver = new DeduplicatingDriver($inner);
        $driver->flush();
    }

    #[Test]
    public function flush_is_noop_when_inner_is_not_flushable(): void
    {
        $inner = $this->createMock(LogDriverInterface::class);
        $inner->expects($this->never())->method('handleLog');

        $driver = new DeduplicatingDriver($inner);
        $driver->flush();
    }

    #[Test]
    public function destruct_flushes_pending_dedup_items(): void
    {
        $received = [];
        $inner = $this->createStub(LogDriverInterface::class);
        $inner->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$received): void {
            $received[] = $item;
        });

        $run = function () use ($inner): void {
            $driver = new DeduplicatingDriver($inner, windowSeconds: 60);
            $driver->handleLog(new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable()));
            $driver->handleLog(new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable()));
            // $driver goes out of scope → __destruct() → flush()
        };
        $run();

        $this->assertCount(2, $received);
        $this->assertSame(1, $received[1]->context['dedup_count']);
    }

    #[Test]
    public function it_implements_flushable_interface(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(FlushableInterface::class, new DeduplicatingDriver($this->createStub(LogDriverInterface::class)));
    }
}

/**
 * Test double: a LogDriverInterface that is also FlushableInterface.
 */
abstract class FlushableLogDriverStub implements LogDriverInterface, FlushableInterface {}
