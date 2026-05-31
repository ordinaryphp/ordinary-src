<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use DateTimeImmutable;
use Ordinary\Log\Driver\TestDriver;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestDriver::class)]
final class TestDriverTest extends TestCase
{
    private function item(LogLevel $level, string $message): LogEntry
    {
        return new LogEntry($level, $message, new DateTimeImmutable());
    }

    #[Test]
    public function it_records_received_items(): void
    {
        $driver = new TestDriver();
        $driver->handleLog($this->item(LogLevel::Info, 'hello'));

        $this->assertCount(1, $driver->records);
        $this->assertSame('hello', $driver->records[0]->message);
    }

    #[Test]
    public function reset_clears_all_records(): void
    {
        $driver = new TestDriver();
        $driver->handleLog($this->item(LogLevel::Info, 'a'));
        $driver->handleLog($this->item(LogLevel::Info, 'b'));
        $driver->reset();

        $this->assertCount(0, $driver->records);
    }

    #[Test]
    public function has_record_at_level_returns_true_when_match(): void
    {
        $driver = new TestDriver();
        $driver->handleLog($this->item(LogLevel::Error, 'boom'));

        $this->assertTrue($driver->hasRecordAtLevel(LogLevel::Error));
    }

    #[Test]
    public function has_record_at_level_returns_false_when_no_match(): void
    {
        $driver = new TestDriver();
        $driver->handleLog($this->item(LogLevel::Info, 'fine'));

        $this->assertFalse($driver->hasRecordAtLevel(LogLevel::Error));
    }

    #[Test]
    public function has_record_matches_level_and_exact_message(): void
    {
        $driver = new TestDriver();
        $driver->handleLog($this->item(LogLevel::Warning, 'Cache miss'));

        $this->assertTrue($driver->hasRecord(LogLevel::Warning, 'Cache miss'));
        $this->assertFalse($driver->hasRecord(LogLevel::Error, 'Cache miss'));
        $this->assertFalse($driver->hasRecord(LogLevel::Warning, 'Different message'));
    }

    #[Test]
    public function has_record_that_contains_matches_substring(): void
    {
        $driver = new TestDriver();
        $driver->handleLog($this->item(LogLevel::Error, 'Payment failed for order 42'));

        $this->assertTrue($driver->hasRecordThatContains('Payment failed'));
        $this->assertTrue($driver->hasRecordThatContains('order 42'));
        $this->assertFalse($driver->hasRecordThatContains('timeout'));
    }

    #[Test]
    public function has_record_that_contains_can_filter_by_level(): void
    {
        $driver = new TestDriver();
        $driver->handleLog($this->item(LogLevel::Info, 'Payment processed'));
        $driver->handleLog($this->item(LogLevel::Error, 'Payment failed'));

        $this->assertTrue($driver->hasRecordThatContains('Payment', LogLevel::Info));
        $this->assertTrue($driver->hasRecordThatContains('Payment', LogLevel::Error));
        $this->assertFalse($driver->hasRecordThatContains('failed', LogLevel::Info));
    }

    #[Test]
    public function get_records_at_level_returns_only_matching(): void
    {
        $driver = new TestDriver();
        $driver->handleLog($this->item(LogLevel::Info, 'a'));
        $driver->handleLog($this->item(LogLevel::Error, 'b'));
        $driver->handleLog($this->item(LogLevel::Error, 'c'));
        $driver->handleLog($this->item(LogLevel::Info, 'd'));

        $errors = $driver->getRecordsAtLevel(LogLevel::Error);

        $this->assertCount(2, $errors);
        $this->assertSame('b', $errors[0]->message);
        $this->assertSame('c', $errors[1]->message);
    }

    #[Test]
    public function get_records_at_level_returns_empty_when_no_match(): void
    {
        $driver = new TestDriver();
        $driver->handleLog($this->item(LogLevel::Info, 'fine'));

        $this->assertSame([], $driver->getRecordsAtLevel(LogLevel::Emergency));
    }

    #[Test]
    public function records_are_publicly_readable(): void
    {
        $driver = new TestDriver();
        $this->assertSame([], $driver->records);
    }
}
