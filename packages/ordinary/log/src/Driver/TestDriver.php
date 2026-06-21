<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;

/**
 * Collects log items in memory for use in tests.
 *
 * Register this driver with a {@see \Ordinary\Log\Logger} under test, then
 * assert on what was logged:
 *
 * ```php
 * $driver = new TestDriver();
 * $logger->add($driver);
 *
 * $service->doSomething(); // triggers $logger->error(...)
 *
 * $this->assertTrue($driver->hasRecordThatContains('Payment failed', LogLevel::Error));
 * ```
 *
 * Call {@see reset()} between test cases if the driver is shared across multiple tests.
 */
final class TestDriver implements LogDriverInterface
{
    /** @var list<LogEntryInterface> All log items received since construction or last {@see reset()}. */
    public private(set) array $records = [];

    public function handleLog(LogEntryInterface $logItem): void
    {
        $this->records[] = $logItem;
    }

    /** Clears all recorded log items. */
    public function reset(): void
    {
        $this->records = [];
    }

    /** Returns true if any item at the given level has been recorded. */
    public function hasRecordAtLevel(LogLevel $level): bool
    {
        return \array_any($this->records, fn(LogEntryInterface $record): bool => $record->level === $level);
    }

    /** Returns true if an item matching both level and exact message has been recorded. */
    public function hasRecord(LogLevel $level, string $message): bool
    {
        return \array_any($this->records, fn(LogEntryInterface $record): bool => $record->level === $level && $record->message === $message);
    }

    /**
     * Returns true if any item whose message contains `$needle` has been recorded.
     * Optionally restrict the search to a specific level.
     */
    public function hasRecordThatContains(string $needle, ?LogLevel $level = null): bool
    {
        foreach ($this->records as $record) {
            if ($level instanceof LogLevel && $record->level !== $level) {
                continue;
            }

            if (\str_contains($record->message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns all items recorded at the given level, in the order they were received.
     *
     * @return list<LogEntryInterface>
     */
    public function getRecordsAtLevel(LogLevel $level): array
    {
        return \array_values(
            \array_filter($this->records, fn(LogEntryInterface $r): bool => $r->level === $level),
        );
    }
}
