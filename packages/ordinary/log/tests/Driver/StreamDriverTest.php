<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use DateTimeImmutable;
use Ordinary\Log\DateTimeFormatter;
use Ordinary\Log\Driver\StreamDriver;
use Ordinary\Log\LevelFormatter;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogFormatterInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\TextFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamDriver::class)]
final class StreamDriverTest extends TestCase
{
    private TextFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TextFormatter(
            new DateTimeFormatter(),
            new LevelFormatter(),
        );
    }

    #[Test]
    public function it_writes_formatted_line_to_stream(): void
    {
        $stream = \fopen('php://memory', 'w+');
        $this->assertNotFalse($stream);

        $driver = new StreamDriver($stream, $this->formatter);
        $driver->handleLog(new LogEntry(LogLevel::Info, 'test message', new DateTimeImmutable()));

        \rewind($stream);
        $output = \stream_get_contents($stream);
        \fclose($stream);

        $this->assertIsString($output);
        $this->assertStringContainsString('test message', $output);
        $this->assertStringEndsWith(\PHP_EOL, $output);
    }

    #[Test]
    public function it_appends_newline_after_each_entry(): void
    {
        $stream = \fopen('php://memory', 'w+');
        $this->assertNotFalse($stream);

        $driver = new StreamDriver($stream, $this->formatter);
        $driver->handleLog(new LogEntry(LogLevel::Info, 'first', new DateTimeImmutable()));
        $driver->handleLog(new LogEntry(LogLevel::Info, 'second', new DateTimeImmutable()));

        \rewind($stream);
        $output = \stream_get_contents($stream);
        \fclose($stream);

        $this->assertIsString($output);
        $lines = \explode(\PHP_EOL, \rtrim($output, \PHP_EOL));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('first', $lines[0]);
        $this->assertStringContainsString('second', $lines[1]);
    }

    #[Test]
    public function it_uses_generic_log_formatter_by_default(): void
    {
        $stream = \fopen('php://memory', 'w+');
        $this->assertNotFalse($stream);

        $driver = new StreamDriver($stream);
        $driver->handleLog(new LogEntry(LogLevel::Info, 'default formatter test', new DateTimeImmutable()));

        \rewind($stream);
        $output = \stream_get_contents($stream);
        \fclose($stream);

        $this->assertIsString($output);
        $this->assertStringContainsString('default formatter test', $output);
    }

    #[Test]
    public function it_propagates_exceptions_from_formatter(): void
    {
        $stream = \fopen('php://memory', 'w+');
        $this->assertNotFalse($stream);

        $formatter = $this->createStub(LogFormatterInterface::class);
        $formatter->method('formatLog')->willThrowException(new \RuntimeException('format failed'));

        $driver = new StreamDriver($stream, $formatter);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('format failed');
        $driver->handleLog(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));

        \fclose($stream);
    }
}
