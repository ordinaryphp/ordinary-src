<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogEntry::class)]
final class LogEntryTest extends TestCase
{
    #[Test]
    public function it_exposes_level_message_and_context_as_properties(): void
    {
        $dt = new DateTimeImmutable();
        $item = new LogEntry(LogLevel::Info, 'Hello {name}', $dt, ['name' => 'world']);

        $this->assertSame(LogLevel::Info, $item->level);
        $this->assertSame('Hello {name}', $item->message);
        $this->assertSame($dt, $item->dateTime);
        $this->assertSame(['name' => 'world'], $item->context);
    }

    #[Test]
    public function it_defaults_to_empty_context(): void
    {
        $item = new LogEntry(LogLevel::Debug, 'msg', new DateTimeImmutable());

        $this->assertSame([], $item->context);
    }

    #[Test]
    public function it_stores_a_provided_datetime(): void
    {
        $dt = new DateTimeImmutable('2024-01-15 10:30:00', new DateTimeZone('UTC'));
        $item = new LogEntry(LogLevel::Error, 'msg', $dt);

        $this->assertSame($dt, $item->dateTime);
    }

    #[Test]
    public function with_context_merges_user_keys_immutably(): void
    {
        $dt = new DateTimeImmutable();
        $original = new LogEntry(LogLevel::Info, 'msg', $dt, ['a' => 1]);
        $updated = $original->withContext(['b' => 2]);

        $this->assertSame(['a' => 1], $original->context);
        $this->assertSame(['a' => 1, 'b' => 2], $updated->context);
        $this->assertSame($original->level, $updated->level);
        $this->assertSame($original->message, $updated->message);
        $this->assertSame($original->dateTime, $updated->dateTime);
    }

    #[Test]
    public function with_context_overwrites_existing_user_key(): void
    {
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['key' => 'old']);
        $updated = $item->withContext(['key' => 'new']);

        $this->assertSame('new', $updated->context['key']);
        $this->assertSame('old', $item->context['key']);
    }

    #[Test]
    public function with_context_accepts_reserved_exception_key(): void
    {
        $e = new \RuntimeException('oops');
        $original = new LogEntry(LogLevel::Error, 'Something broke', new DateTimeImmutable());
        $updated = $original->withContext([LogEntryInterface::RESERVED_EXCEPTION => $e]);

        $this->assertSame([], $original->context);
        $this->assertArrayHasKey(LogEntryInterface::RESERVED_EXCEPTION, $updated->context);
        $this->assertSame($e, $updated->context[LogEntryInterface::RESERVED_EXCEPTION]);
    }

    #[Test]
    public function with_context_accepts_any_reserved_key(): void
    {
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());
        $updated = $item->withContext([
            LogEntryInterface::RESERVED_DATE      => '2024-01-01',
            LogEntryInterface::RESERVED_LEVEL     => 'info',
            LogEntryInterface::RESERVED_EXCEPTION => new \RuntimeException(),
        ]);

        $this->assertArrayHasKey(LogEntryInterface::RESERVED_DATE, $updated->context);
        $this->assertArrayHasKey(LogEntryInterface::RESERVED_LEVEL, $updated->context);
        $this->assertArrayHasKey(LogEntryInterface::RESERVED_EXCEPTION, $updated->context);
    }

    #[Test]
    public function with_context_preserves_existing_context_when_adding_exception(): void
    {
        $e = new \RuntimeException('oops');
        $item = new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable(), ['user' => 'alice']);
        $updated = $item->withContext([LogEntryInterface::RESERVED_EXCEPTION => $e]);

        $this->assertSame('alice', $updated->context['user']);
        $this->assertArrayHasKey(LogEntryInterface::RESERVED_EXCEPTION, $updated->context);
    }

    #[Test]
    public function with_level_returns_new_instance_with_changed_level(): void
    {
        $dt = new DateTimeImmutable();
        $original = new LogEntry(LogLevel::Info, 'msg', $dt);
        $updated = $original->withLevel(LogLevel::Error);

        $this->assertSame(LogLevel::Error, $updated->level);
        $this->assertSame(LogLevel::Info, $original->level);
        $this->assertSame('msg', $updated->message);
        $this->assertSame($dt, $updated->dateTime);
    }

    #[Test]
    public function with_message_returns_new_instance_with_changed_message(): void
    {
        $dt = new DateTimeImmutable();
        $original = new LogEntry(LogLevel::Info, 'original', $dt);
        $updated = $original->withMessage('updated');

        $this->assertSame('updated', $updated->message);
        $this->assertSame('original', $original->message);
        $this->assertSame(LogLevel::Info, $updated->level);
        $this->assertSame($dt, $updated->dateTime);
    }

    #[Test]
    public function with_datetime_returns_new_instance_with_changed_datetime(): void
    {
        $original = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable('2024-01-01T00:00:00Z'));
        $newDt = new DateTimeImmutable('2024-06-01T12:00:00Z');
        $updated = $original->withDateTime($newDt);

        $this->assertSame($newDt, $updated->dateTime);
        $this->assertNotSame($original->dateTime, $updated->dateTime);
        $this->assertSame('msg', $updated->message);
        $this->assertSame(LogLevel::Info, $updated->level);
    }

    #[Test]
    public function reserved_constants_use_psr3_compatible_names(): void
    {
        $constants = new \ReflectionClass(LogEntryInterface::class)->getConstants();
        $this->assertSame('date', $constants['RESERVED_DATE']);
        $this->assertSame('level', $constants['RESERVED_LEVEL']);
        $this->assertSame('exception', $constants['RESERVED_EXCEPTION']);
        $this->assertSame('exception.message', $constants['RESERVED_EXCEPTION_MESSAGE']);
        $this->assertSame('exception.line', $constants['RESERVED_EXCEPTION_LINE']);
        $this->assertSame('exception.code', $constants['RESERVED_EXCEPTION_CODE']);
        $this->assertSame('channel', $constants['RESERVED_CHANNEL']);
    }
}
