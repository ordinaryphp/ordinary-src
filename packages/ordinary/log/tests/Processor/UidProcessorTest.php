<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Processor;

use DateTimeImmutable;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Processor\UidProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[CoversClass(UidProcessor::class)]
final class UidProcessorTest extends TestCase
{
    #[Test]
    public function it_adds_uid_to_log_item(): void
    {
        $processor = new UidProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertArrayHasKey('uid', $result->context);
        $this->assertIsString($result->context['uid']);
    }

    #[Test]
    public function uid_is_the_same_across_multiple_calls(): void
    {
        $processor = new UidProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $first = $processor->process($item);
        $second = $processor->process($item);

        $this->assertSame($first->context['uid'], $second->context['uid']);
    }

    #[Test]
    public function different_processor_instances_have_different_uids(): void
    {
        $a = new UidProcessor();
        $b = new UidProcessor();

        $this->assertNotSame($a->getUid(), $b->getUid());
    }

    #[Test]
    public function get_uid_returns_the_same_uid_as_context(): void
    {
        $processor = new UidProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertSame($processor->getUid(), $result->context['uid']);
    }

    /** @return \Iterator<string, array{int}> */
    public static function lengthProvider(): \Iterator
    {
        yield 'length 1' => [1];
        yield 'length 7' => [7];
        yield 'length 14' => [14];
        yield 'length 32' => [32];
    }

    #[Test]
    #[DataProvider('lengthProvider')]
    public function uid_has_correct_length(int $length): void
    {
        $processor = new UidProcessor(length: $length);

        $this->assertSame($length, \strlen($processor->getUid()));
    }

    #[Test]
    public function uid_is_hexadecimal(): void
    {
        $processor = new UidProcessor(length: 16);

        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $processor->getUid());
    }

    #[Test]
    public function custom_context_key_is_used(): void
    {
        $processor = new UidProcessor(contextKey: 'request_id');
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertArrayHasKey('request_id', $result->context);
        $this->assertArrayNotHasKey('uid', $result->context);
    }

    // ── Custom generator ──────────────────────────────────────────────────────

    #[Test]
    public function it_uses_the_return_value_of_a_custom_generator(): void
    {
        $processor = new UidProcessor(generator: static fn(): string => 'my-custom-uid');

        $this->assertSame('my-custom-uid', $processor->getUid());
    }

    #[Test]
    public function custom_generator_uid_appears_in_processed_item_context(): void
    {
        $processor = new UidProcessor(generator: static fn(): string => 'trace-abc');
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertSame('trace-abc', $result->context['uid']);
    }

    #[Test]
    public function it_throws_when_generator_returns_empty_string(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('UID generator must return a non-empty string.');

        new UidProcessor(generator: static fn(): string => '');
    }
}
