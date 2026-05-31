<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use Ordinary\Log\LogBatch;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogBatch::class)]
final class LogBatchTest extends TestCase
{
    #[Test]
    public function it_holds_items_passed_to_the_factory(): void
    {
        $a = new LogEntry(LogLevel::Info, 'first', new DateTimeImmutable());
        $b = new LogEntry(LogLevel::Error, 'second', new DateTimeImmutable());

        $batch = LogBatch::of($a, $b);

        $this->assertSame([$a, $b], $batch->items);
    }

    #[Test]
    public function it_accepts_a_spread_list(): void
    {
        $items = [
            new LogEntry(LogLevel::Info, 'a', new DateTimeImmutable()),
            new LogEntry(LogLevel::Info, 'b', new DateTimeImmutable()),
        ];

        $batch = LogBatch::of(...$items);

        $this->assertSame($items, $batch->items);
    }

    #[Test]
    public function it_accepts_a_single_item(): void
    {
        $item = new LogEntry(LogLevel::Debug, 'lone', new DateTimeImmutable());

        $batch = LogBatch::of($item);

        $this->assertSame([$item], $batch->items);
    }

    #[Test]
    public function it_rejects_an_empty_call(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LogBatch::of();
    }
}
