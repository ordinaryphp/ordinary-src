<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Ordinary\Log\FlushableInterface;
use Ordinary\Log\LogBatch;
use Ordinary\Log\LogBatchDriverInterface;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntryInterface;

/**
 * Accumulates log items in memory and dispatches them in bulk on flush.
 *
 * Register this as the outermost driver and call Logger::flush() (or flush()
 * directly) at the end of each request or job. When the inner driver implements
 * LogBatchDriverInterface the entire buffer is dispatched in a single call;
 * otherwise each item is forwarded individually via handleLog.
 *
 * Set $flushAfter to a positive integer to auto-flush once the buffer reaches
 * that size, bounding memory usage in high-throughput scenarios.
 *
 * The buffer is also flushed automatically on object destruction.
 *
 * Items held in the buffer when flush() throws are lost — this class provides
 * buffering, not guaranteed delivery.
 *
 * ```php
 * $logger->add(new BufferingDriver(new CloudWatchDriver(...), flushAfter: 100));
 * // at request end:
 * $logger->flush();
 * ```
 */
final class BufferingDriver implements LogDriverInterface, FlushableInterface
{
    /** @var list<LogEntryInterface> */
    private array $buffer = [];

    /**
     * @param LogDriverInterface $inner Receives items during flush.
     * @param int $flushAfter Auto-flush when the buffer reaches this many items.
     *                        0 disables auto-flush (explicit flush only).
     */
    public function __construct(
        private readonly LogDriverInterface $inner,
        private readonly int $flushAfter = 0,
    ) {}

    public function handleLog(LogEntryInterface $logItem): void
    {
        $this->buffer[] = $logItem;

        if ($this->flushAfter > 0 && \count($this->buffer) >= $this->flushAfter) {
            $this->flush();
        }
    }

    /**
     * Dispatches all buffered items to the inner driver and clears the buffer.
     *
     * Uses handleLogBatch when the inner driver implements LogBatchDriverInterface;
     * falls back to individual handleLog calls otherwise. The buffer is cleared
     * before dispatch — items are lost if the inner driver throws.
     */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $items = $this->buffer;
        $this->buffer = [];

        if ($this->inner instanceof LogBatchDriverInterface) {
            $this->inner->handleLogBatch(LogBatch::of(...$items));
        } else {
            foreach ($items as $item) {
                $this->inner->handleLog($item);
            }
        }
    }

    public function __destruct()
    {
        try {
            $this->flush();
        } catch (\Throwable) {
        }
    }
}
