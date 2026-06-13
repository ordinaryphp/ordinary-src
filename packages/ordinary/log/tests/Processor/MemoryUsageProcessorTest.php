<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Processor;

use DateTimeImmutable;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Processor\MemoryUsageProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemoryUsageProcessor::class)]
final class MemoryUsageProcessorTest extends TestCase
{
    #[Test]
    public function it_adds_memory_usage_to_context(): void
    {
        $processor = new MemoryUsageProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertArrayHasKey('memory.usage', $result->context);
        $this->assertIsInt($result->context['memory.usage']);
        $this->assertGreaterThan(0, $result->context['memory.usage']);
    }

    #[Test]
    public function it_does_not_add_peak_usage_by_default(): void
    {
        $processor = new MemoryUsageProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertArrayNotHasKey('memory.peak_usage', $result->context);
    }

    #[Test]
    public function it_adds_peak_usage_when_enabled(): void
    {
        $processor = new MemoryUsageProcessor(includePeak: true);
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertArrayHasKey('memory.peak_usage', $result->context);
        $this->assertIsInt($result->context['memory.peak_usage']);
        $this->assertGreaterThanOrEqual($result->context['memory.usage'], $result->context['memory.peak_usage']);
    }

    #[Test]
    public function it_does_not_mutate_original_item(): void
    {
        $processor = new MemoryUsageProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $processor->process($item);

        $this->assertArrayNotHasKey('memory.usage', $item->context);
    }

    #[Test]
    public function it_preserves_existing_context(): void
    {
        $processor = new MemoryUsageProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['user_id' => 42]);

        $result = $processor->process($item);

        $this->assertSame(42, $result->context['user_id']);
        $this->assertArrayHasKey('memory.usage', $result->context);
    }

    #[Test]
    public function it_upcasts_non_immutable_items(): void
    {
        $dt = new DateTimeImmutable();
        $nonImmutable = new class ($dt) implements LogEntryInterface {
            public function __construct(private readonly DateTimeImmutable $dt) {}

            public LogLevel $level { get => LogLevel::Info; }

            public string $message { get => 'msg'; }

            public \DateTimeInterface $dateTime { get => $this->dt; }

            /** @var array<string, mixed> */
            public array $context { get => []; }
        };

        $processor = new MemoryUsageProcessor();
        $result = $processor->process($nonImmutable);

        $this->assertInstanceOf(LogEntry::class, $result);
        $this->assertArrayHasKey('memory.usage', $result->context);
    }
}
