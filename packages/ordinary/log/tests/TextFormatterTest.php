<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Ordinary\Log\DateTimeFormatter;
use Ordinary\Log\ExceptionFormatter;
use Ordinary\Log\LevelFormatter;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\TextFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextFormatter::class)]
final class TextFormatterTest extends TestCase
{
    private TextFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TextFormatter(
            new DateTimeFormatter('Y-m-d', 'UTC'),
            new LevelFormatter(),
        );
    }

    #[Test]
    public function it_interpolates_context_placeholders(): void
    {
        $item = new LogEntry(LogLevel::Info, 'Hello {name}', new DateTimeImmutable(), ['name' => 'world']);

        $this->assertSame('Hello world', $this->formatter->formatLog($item));
    }

    #[Test]
    public function it_injects_formatted_date_via_reserved_key(): void
    {
        $dt = new DateTimeImmutable('2024-03-15', new DateTimeZone('UTC'));
        $item = new LogEntry(LogLevel::Info, 'Date: {date}', $dt);

        $this->assertSame('Date: 2024-03-15', $this->formatter->formatLog($item));
    }

    #[Test]
    public function it_injects_formatted_level_via_reserved_key(): void
    {
        $item = new LogEntry(LogLevel::Warning, 'Level: {level}', new DateTimeImmutable());

        $this->assertSame('Level: warning', $this->formatter->formatLog($item));
    }

    #[Test]
    public function it_converts_throwable_to_short_form_without_exception_formatter(): void
    {
        $exception = new \RuntimeException('something broke');
        $item = new LogEntry(LogLevel::Error, '{err}', new DateTimeImmutable(), ['err' => $exception]);

        $result = $this->formatter->formatLog($item);

        $this->assertSame('RuntimeException: something broke', $result);
    }

    #[Test]
    public function it_uses_exception_formatter_for_throwable_when_provided(): void
    {
        $formatter = new TextFormatter(
            new DateTimeFormatter('Y-m-d', 'UTC'),
            new LevelFormatter(),
            new ExceptionFormatter(includeTrace: false),
        );

        $exception = new \RuntimeException('something broke');
        $item = new LogEntry(LogLevel::Error, '{exception}', new DateTimeImmutable())
            ->withContext([LogEntryInterface::RESERVED_EXCEPTION => $exception]);

        $result = $formatter->formatLog($item);

        $this->assertStringContainsString('RuntimeException', $result);
        $this->assertStringContainsString('something broke', $result);
        $this->assertStringContainsString('code', $result);
    }

    #[Test]
    public function it_converts_stringable_object_to_string(): void
    {
        $obj = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };

        $item = new LogEntry(LogLevel::Info, 'val: {obj}', new DateTimeImmutable(), ['obj' => $obj]);

        $this->assertSame('val: stringable-value', $this->formatter->formatLog($item));
    }

    #[Test]
    public function it_converts_integer_context_value(): void
    {
        $item = new LogEntry(LogLevel::Info, 'count: {n}', new DateTimeImmutable(), ['n' => 42]);

        $this->assertSame('count: 42', $this->formatter->formatLog($item));
    }

    #[Test]
    public function it_converts_float_context_value(): void
    {
        $item = new LogEntry(LogLevel::Info, 'ratio: {r}', new DateTimeImmutable(), ['r' => 3.14]);

        $this->assertSame('ratio: 3.14', $this->formatter->formatLog($item));
    }

    #[Test]
    public function it_converts_true_to_string_true(): void
    {
        $item = new LogEntry(LogLevel::Info, 'flag: {f}', new DateTimeImmutable(), ['f' => true]);

        $this->assertSame('flag: true', $this->formatter->formatLog($item));
    }

    #[Test]
    public function it_converts_false_to_string_false(): void
    {
        $item = new LogEntry(LogLevel::Info, 'flag: {f}', new DateTimeImmutable(), ['f' => false]);

        $this->assertSame('flag: false', $this->formatter->formatLog($item));
    }

    #[Test]
    public function it_converts_null_context_value(): void
    {
        $item = new LogEntry(LogLevel::Info, 'val: {v}', new DateTimeImmutable(), ['v' => null]);

        $this->assertSame('val: null', $this->formatter->formatLog($item));
    }

    #[Test]
    public function it_leaves_unmatched_placeholders_intact(): void
    {
        $item = new LogEntry(LogLevel::Info, 'Hello {missing}', new DateTimeImmutable());

        $this->assertSame('Hello {missing}', $this->formatter->formatLog($item));
    }
}
