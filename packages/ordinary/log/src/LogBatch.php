<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * An ordered, non-empty collection of log items dispatched as a unit.
 *
 * Produced by BufferingDriver during a flush and passed to LogBatchDriverInterface
 * implementations that can handle bulk writes more efficiently than per-item dispatch.
 *
 * Use the static factory: LogBatch::of($itemA, $itemB) or LogBatch::of(...$list).
 */
final readonly class LogBatch
{
    /**
     * @param list<LogEntryInterface> $items
     */
    private function __construct(
        public array $items,
    ) {}

    /**
     * Creates a batch from one or more log items.
     *
     * @throws \InvalidArgumentException if called with no arguments
     */
    public static function of(LogEntryInterface ...$logs): self
    {
        if ($logs === []) {
            throw new \InvalidArgumentException('LogBatch must contain at least one item');
        }

        return new self(\array_values($logs));
    }
}
