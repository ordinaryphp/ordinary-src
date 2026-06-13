<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use Ordinary\Log\CallableProcessor;
use Ordinary\Log\ImmutableLogEntryInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallableProcessor::class)]
final class CallableProcessorTest extends TestCase
{
    #[Test]
    public function it_delegates_to_the_closure(): void
    {
        $item = new LogEntry(LogLevel::Info, 'original', new DateTimeImmutable());
        $called = false;

        $processor = new CallableProcessor(function (ImmutableLogEntryInterface $m) use (&$called): ImmutableLogEntryInterface {
            $called = true;
            return $m;
        });

        $processor->process($item);

        $this->assertTrue($called);
    }

    #[Test]
    public function it_receives_the_log_item_state(): void
    {
        $dt = new DateTimeImmutable();
        $item = new LogEntry(LogLevel::Warning, 'msg', $dt, ['key' => 'value']);
        $seenLevel = null;
        $seenMessage = null;
        $seenContext = null;

        $processor = new CallableProcessor(
            function (ImmutableLogEntryInterface $m) use (&$seenLevel, &$seenMessage, &$seenContext): ImmutableLogEntryInterface {
                $seenLevel = $m->level;
                $seenMessage = $m->message;
                $seenContext = $m->context;
                return $m;
            },
        );

        $processor->process($item);

        $this->assertSame(LogLevel::Warning, $seenLevel);
        $this->assertSame('msg', $seenMessage);
        $this->assertSame(['key' => 'value'], $seenContext);
    }

    #[Test]
    public function it_can_enrich_context(): void
    {
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $processor = new CallableProcessor(
            fn(ImmutableLogEntryInterface $i): ImmutableLogEntryInterface => $i->withContext(['enriched' => true]),
        );

        $result = $processor->process($item);

        $this->assertTrue($result->context['enriched']);
        $this->assertArrayNotHasKey('enriched', $item->context);
    }

    #[Test]
    public function it_reflects_closure_transformations(): void
    {
        $item = new LogEntry(LogLevel::Info, 'original', new DateTimeImmutable());

        $processor = new CallableProcessor(
            fn(ImmutableLogEntryInterface $i): ImmutableLogEntryInterface => $i->withLevel(LogLevel::Error)->withMessage('replaced'),
        );

        $result = $processor->process($item);

        $this->assertSame(LogLevel::Error, $result->level);
        $this->assertSame('replaced', $result->message);
        $this->assertSame(LogLevel::Info, $item->level);
        $this->assertSame('original', $item->message);
    }

    #[Test]
    public function it_upcasts_non_immutable_items_before_passing_to_closure(): void
    {
        $dt = new DateTimeImmutable();
        $nonImmutable = new class ($dt) implements LogEntryInterface {
            public function __construct(private readonly DateTimeImmutable $dt) {}

            public LogLevel $level { get => LogLevel::Warning; }

            public string $message { get => 'stubbed'; }

            public \DateTimeInterface $dateTime { get => $this->dt; }

            /** @var array<string, mixed> */
            public array $context { get => ['x' => 1]; }
        };

        $received = null;
        $processor = new CallableProcessor(
            function (ImmutableLogEntryInterface $item) use (&$received): ImmutableLogEntryInterface {
                $received = $item;
                return $item;
            },
        );

        $processor->process($nonImmutable);

        $this->assertInstanceOf(LogEntry::class, $received);
        $this->assertSame(LogLevel::Warning, $received->level);
        $this->assertSame('stubbed', $received->message);
        $this->assertSame(['x' => 1], $received->context);
    }
}
