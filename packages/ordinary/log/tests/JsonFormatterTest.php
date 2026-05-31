<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Ordinary\Log\DateTimeFormatter;
use Ordinary\Log\ExceptionFormatter;
use Ordinary\Log\JsonFormatter;
use Ordinary\Log\LevelFormatter;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonFormatter::class)]
final class JsonFormatterTest extends TestCase
{
    private JsonFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new JsonFormatter(
            new DateTimeFormatter('Y-m-d', 'UTC'),
            new LevelFormatter(),
        );
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        $data = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        return $data;
    }

    #[Test]
    public function it_produces_valid_json(): void
    {
        $item = new LogEntry(LogLevel::Info, 'hello', new DateTimeImmutable());

        $result = $this->formatter->formatLog($item);

        $this->assertJson($result);
    }

    #[Test]
    public function it_includes_level_date_message_and_context_fields(): void
    {
        $dt = new DateTimeImmutable('2024-06-01', new DateTimeZone('UTC'));
        $item = new LogEntry(LogLevel::Warning, 'Something happened', $dt, ['key' => 'value']);

        $data = $this->decode($this->formatter->formatLog($item));

        $this->assertSame('warning', $data['level']);
        $this->assertSame('2024-06-01', $data['date']);
        $this->assertSame('Something happened', $data['message']);
        $this->assertSame(['key' => 'value'], $data['context']);
    }

    #[Test]
    public function it_preserves_raw_message_template_without_interpolation(): void
    {
        $item = new LogEntry(LogLevel::Info, 'User {username} logged in', new DateTimeImmutable(), ['username' => 'john']);

        $data = $this->decode($this->formatter->formatLog($item));

        $this->assertSame('User {username} logged in', $data['message']);
        $this->assertSame(['username' => 'john'], $data['context']);
    }

    #[Test]
    public function it_extracts_exception_to_top_level_field(): void
    {
        $e = new \RuntimeException('boom');
        $item = new LogEntry(LogLevel::Error, 'Error occurred', new DateTimeImmutable())
            ->withContext([LogEntryInterface::RESERVED_EXCEPTION => $e]);

        $data = $this->decode($this->formatter->formatLog($item));

        $this->assertArrayHasKey('exception', $data);
        $this->assertIsString($data['exception']);
        $this->assertStringContainsString('RuntimeException', $data['exception']);
        $context = $data['context'];
        $this->assertIsArray($context);
        $this->assertArrayNotHasKey(LogEntryInterface::RESERVED_EXCEPTION, $context);
    }

    #[Test]
    public function it_uses_exception_formatter_when_provided(): void
    {
        $formatter = new JsonFormatter(
            new DateTimeFormatter(),
            new LevelFormatter(),
            new ExceptionFormatter(includeTrace: false),
        );

        $e = new \RuntimeException('detailed', 99);
        $item = new LogEntry(LogLevel::Error, 'Error', new DateTimeImmutable())
            ->withContext([LogEntryInterface::RESERVED_EXCEPTION => $e]);

        $data = $this->decode($formatter->formatLog($item));

        $exception = $data['exception'];
        $this->assertIsString($exception);
        $this->assertStringContainsString('code 99', $exception);
    }

    #[Test]
    public function it_omits_exception_field_when_none_provided(): void
    {
        $item = new LogEntry(LogLevel::Info, 'no exception', new DateTimeImmutable());

        $data = $this->decode($this->formatter->formatLog($item));

        $this->assertArrayNotHasKey('exception', $data);
    }

    #[Test]
    public function non_throwable_exception_value_stays_in_context(): void
    {
        $item = new LogEntry(
            LogLevel::Info,
            'msg',
            new DateTimeImmutable(),
            [LogEntryInterface::RESERVED_EXCEPTION => 'just a string'],
        );

        $data = $this->decode($this->formatter->formatLog($item));

        $this->assertArrayNotHasKey('exception', $data);
        $context = $data['context'];
        $this->assertIsArray($context);
        $this->assertArrayHasKey('exception', $context);
        $this->assertSame('just a string', $context['exception']);
    }

    #[Test]
    public function it_normalizes_bool_context_values(): void
    {
        $item = new LogEntry(LogLevel::Debug, 'flags', new DateTimeImmutable(), ['t' => true, 'f' => false]);

        $data = $this->decode($this->formatter->formatLog($item));

        $context = $data['context'];
        $this->assertIsArray($context);
        $this->assertTrue($context['t']);
        $this->assertFalse($context['f']);
    }

    #[Test]
    public function it_normalizes_null_context_values(): void
    {
        $item = new LogEntry(LogLevel::Debug, 'null', new DateTimeImmutable(), ['v' => null]);

        $data = $this->decode($this->formatter->formatLog($item));

        $context = $data['context'];
        $this->assertIsArray($context);
        $this->assertNull($context['v']);
    }

    #[Test]
    public function it_normalizes_nan_float_to_string(): void
    {
        $item = new LogEntry(LogLevel::Debug, 'nan', new DateTimeImmutable(), ['v' => \NAN]);

        $data = $this->decode($this->formatter->formatLog($item));

        $context = $data['context'];
        $this->assertIsArray($context);
        $this->assertSame('NaN', $context['v']);
    }

    #[Test]
    public function it_normalizes_infinity_float_to_string(): void
    {
        $item = new LogEntry(LogLevel::Debug, 'inf', new DateTimeImmutable(), ['v' => \INF]);

        $data = $this->decode($this->formatter->formatLog($item));

        $context = $data['context'];
        $this->assertIsArray($context);
        $this->assertSame('Infinity', $context['v']);
    }

    #[Test]
    public function it_extracts_channel_as_first_top_level_field(): void
    {
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable())
            ->withContext([LogEntryInterface::RESERVED_CHANNEL => 'payment']);

        $data = $this->decode($this->formatter->formatLog($item));

        $this->assertSame('payment', $data['channel']);
        $this->assertSame('channel', \array_key_first($data));
        $context = $data['context'];
        $this->assertIsArray($context);
        $this->assertArrayNotHasKey(LogEntryInterface::RESERVED_CHANNEL, $context);
    }

    #[Test]
    public function it_omits_channel_field_when_not_set(): void
    {
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $data = $this->decode($this->formatter->formatLog($item));

        $this->assertArrayNotHasKey('channel', $data);
    }

    #[Test]
    public function it_normalizes_datetime_interface_to_atom_string(): void
    {
        $dt = new DateTimeImmutable('2024-06-01T12:00:00+00:00');
        $item = new LogEntry(LogLevel::Info, 'ts', new DateTimeImmutable(), ['ts' => $dt]);

        $data = $this->decode($this->formatter->formatLog($item));

        $context = $data['context'];
        $this->assertIsArray($context);
        $this->assertIsString($context['ts']);
        $this->assertStringStartsWith('2024-06-01T12:00:00', $context['ts']);
    }

    #[Test]
    public function it_normalizes_datetime_in_dedup_times_array(): void
    {
        $t1 = new DateTimeImmutable('2024-06-01T12:00:00+00:00');
        $t2 = new DateTimeImmutable('2024-06-01T12:00:10+00:00');
        $item = new LogEntry(
            LogLevel::Error,
            'msg',
            new DateTimeImmutable(),
            ['dedup_times' => [$t1, $t2]],
        );

        $data = $this->decode($this->formatter->formatLog($item));

        $context = $data['context'];
        $this->assertIsArray($context);
        $times = $context['dedup_times'];
        $this->assertIsArray($times);
        $this->assertIsString($times[0]);
        $this->assertIsString($times[1]);
        $this->assertDoesNotMatchRegularExpression('/\[object/', $times[0]);
    }

    #[Test]
    public function it_normalizes_stringable_object(): void
    {
        $obj = new class implements \Stringable {
            public function __toString(): string
            {
                return 'my-value';
            }
        };

        $item = new LogEntry(LogLevel::Info, 'obj', new DateTimeImmutable(), ['o' => $obj]);

        $data = $this->decode($this->formatter->formatLog($item));

        $context = $data['context'];
        $this->assertIsArray($context);
        $this->assertSame('my-value', $context['o']);
    }
}
