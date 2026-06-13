<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Processor;

use DateTimeImmutable;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Processor\TagProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TagProcessor::class)]
final class TagProcessorTest extends TestCase
{
    #[Test]
    public function it_adds_tags_to_log_item(): void
    {
        $processor = new TagProcessor(['env' => 'production', 'service' => 'api']);
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertSame('production', $result->context['env']);
        $this->assertSame('api', $result->context['service']);
    }

    #[Test]
    public function it_preserves_existing_context(): void
    {
        $processor = new TagProcessor(['env' => 'production']);
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['user_id' => 42]);

        $result = $processor->process($item);

        $this->assertSame(42, $result->context['user_id']);
        $this->assertSame('production', $result->context['env']);
    }

    #[Test]
    public function tags_overwrite_existing_context_keys(): void
    {
        $processor = new TagProcessor(['env' => 'production']);
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['env' => 'staging']);

        $result = $processor->process($item);

        $this->assertSame('production', $result->context['env']);
    }

    #[Test]
    public function it_does_not_mutate_original_item(): void
    {
        $processor = new TagProcessor(['env' => 'production']);
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $processor->process($item);

        $this->assertArrayNotHasKey('env', $item->context);
    }

    #[Test]
    public function it_handles_empty_tags(): void
    {
        $processor = new TagProcessor([]);
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['a' => 1]);

        $result = $processor->process($item);

        $this->assertSame(['a' => 1], $result->context);
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
            public array $context { get => ['existing' => 'value']; }
        };

        $processor = new TagProcessor(['env' => 'prod']);
        $result = $processor->process($nonImmutable);

        $this->assertInstanceOf(LogEntry::class, $result);
        $this->assertSame('prod', $result->context['env']);
        $this->assertSame('value', $result->context['existing']);
    }
}
