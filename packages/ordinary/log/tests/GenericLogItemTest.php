<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Ordinary\Log\GenericLogItem;
use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenericLogItem::class)]
final class GenericLogItemTest extends TestCase
{
    #[Test]
    public function it_exposes_level_message_and_context_as_properties(): void
    {
        $dt = new DateTimeImmutable();
        $item = new GenericLogItem(LogLevel::Info, 'Hello {name}', $dt, ['name' => 'world']);

        $this->assertSame(LogLevel::Info, $item->level);
        $this->assertSame('Hello {name}', $item->message);
        $this->assertSame($dt, $item->dateTime);
        $this->assertSame(['name' => 'world'], $item->context);
    }

    #[Test]
    public function it_defaults_to_empty_context(): void
    {
        $item = new GenericLogItem(LogLevel::Debug, 'msg', new DateTimeImmutable());

        $this->assertSame([], $item->context);
    }

    #[Test]
    public function it_stores_a_provided_datetime(): void
    {
        $dt = new DateTimeImmutable('2024-01-15 10:30:00', new DateTimeZone('UTC'));
        $item = new GenericLogItem(LogLevel::Error, 'msg', $dt);

        $this->assertSame($dt, $item->dateTime);
    }

    #[Test]
    public function with_context_merges_user_keys_immutably(): void
    {
        $dt = new DateTimeImmutable();
        $original = new GenericLogItem(LogLevel::Info, 'msg', $dt, ['a' => 1]);
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
        $item = new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable(), ['key' => 'old']);
        $updated = $item->withContext(['key' => 'new']);

        $this->assertSame('new', $updated->context['key']);
        $this->assertSame('old', $item->context['key']);
    }

    #[Test]
    public function with_context_throws_on_reserved_key(): void
    {
        $item = new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(LogItemInterface::RESERVED_EXCEPTION);

        $item->withContext([LogItemInterface::RESERVED_EXCEPTION => new \RuntimeException()]);
    }

    #[Test]
    public function with_reserved_context_sets_exception_key_immutably(): void
    {
        $e = new \RuntimeException('oops');
        $original = new GenericLogItem(LogLevel::Error, 'Something broke: {@exception}', new DateTimeImmutable());
        $updated = $original->withReservedContext([LogItemInterface::RESERVED_EXCEPTION => $e]);

        $this->assertSame([], $original->context);
        $this->assertArrayHasKey(LogItemInterface::RESERVED_EXCEPTION, $updated->context);
        $this->assertSame($e, $updated->context[LogItemInterface::RESERVED_EXCEPTION]);
    }

    #[Test]
    public function with_reserved_context_preserves_existing_user_context(): void
    {
        $e = new \RuntimeException('oops');
        $item = new GenericLogItem(LogLevel::Error, 'msg', new DateTimeImmutable(), ['user' => 'alice']);
        $updated = $item->withReservedContext([LogItemInterface::RESERVED_EXCEPTION => $e]);

        $this->assertSame('alice', $updated->context['user']);
        $this->assertArrayHasKey(LogItemInterface::RESERVED_EXCEPTION, $updated->context);
    }

    #[Test]
    public function with_reserved_context_accepts_any_reserved_key(): void
    {
        $item = new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable());
        $updated = $item->withReservedContext([
            LogItemInterface::RESERVED_DATE => '2024-01-01',
            LogItemInterface::RESERVED_LEVEL => 'info',
            LogItemInterface::RESERVED_EXCEPTION => new \RuntimeException(),
        ]);

        $this->assertArrayHasKey(LogItemInterface::RESERVED_DATE, $updated->context);
        $this->assertArrayHasKey(LogItemInterface::RESERVED_LEVEL, $updated->context);
        $this->assertArrayHasKey(LogItemInterface::RESERVED_EXCEPTION, $updated->context);
    }

    #[Test]
    public function with_reserved_context_throws_on_non_reserved_key(): void
    {
        $item = new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('user_key');

        $item->withReservedContext(['user_key' => 'value']);
    }

    #[Test]
    public function reserved_constants_are_defined_on_interface(): void
    {
        $this->assertSame('@date', LogItemInterface::RESERVED_DATE);
        $this->assertSame('@level', LogItemInterface::RESERVED_LEVEL);
        $this->assertSame('@exception', LogItemInterface::RESERVED_EXCEPTION);
        $this->assertSame('@exception.message', LogItemInterface::RESERVED_EXCEPTION_MESSAGE);
        $this->assertSame('@exception.line', LogItemInterface::RESERVED_EXCEPTION_LINE);
        $this->assertSame('@exception.code', LogItemInterface::RESERVED_EXCEPTION_CODE);
    }

    #[Test]
    public function reserved_constants_are_accessible_via_concrete_class(): void
    {
        $this->assertSame(LogItemInterface::RESERVED_DATE, GenericLogItem::RESERVED_DATE);
        $this->assertSame(LogItemInterface::RESERVED_LEVEL, GenericLogItem::RESERVED_LEVEL);
        $this->assertSame(LogItemInterface::RESERVED_EXCEPTION, GenericLogItem::RESERVED_EXCEPTION);
        $this->assertSame(LogItemInterface::RESERVED_EXCEPTION_MESSAGE, GenericLogItem::RESERVED_EXCEPTION_MESSAGE);
        $this->assertSame(LogItemInterface::RESERVED_EXCEPTION_LINE, GenericLogItem::RESERVED_EXCEPTION_LINE);
        $this->assertSame(LogItemInterface::RESERVED_EXCEPTION_CODE, GenericLogItem::RESERVED_EXCEPTION_CODE);
    }
}
