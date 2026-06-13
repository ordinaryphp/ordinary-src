<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use DateTimeImmutable;
use Ordinary\Log\FlushableInterface;
use Ordinary\Log\ImmutableLogEntryInterface;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\UtcClock;
use Psr\Clock\ClockInterface;

/**
 * Suppresses repeated log items within a sliding time window, then re-dispatches
 * a summary when the window closes.
 *
 * The first occurrence of a fingerprint is forwarded immediately as a normal log.
 * Subsequent occurrences within the window replace the stored pending item (so the
 * latest context is always preserved) and their timestamps are accumulated. When the
 * window expires or flush() is called, any pending item that received at least one
 * duplicate is re-dispatched with `dedup_count` and `dedup_times` added to its
 * context. Pending items with no duplicates are silently discarded on flush.
 *
 * The pending state is fully cleared on flush, so fingerprints are treated as fresh
 * after a flush cycle. The destructor triggers a silent flush.
 *
 * The default fingerprint is "{level}:{message}". Provide a custom $fingerprint
 * closure for finer or coarser granularity:
 *
 * ```php
 * $logger->add(new DeduplicatingDriver(
 *     new StreamDriver(STDERR),
 *     windowSeconds: 300,
 *     fingerprint: fn(LogEntryInterface $item) => $item->level->name . ':' . ($item->context['event'] ?? $item->message),
 * ));
 * ```
 */
final class DeduplicatingDriver implements LogDriverInterface, FlushableInterface
{
    /**
     * @var array<string, array{item: LogEntryInterface, firstSeen: DateTimeImmutable, times: list<DateTimeImmutable>}>
     */
    private array $pending = [];

    /**
     * @param int $windowSeconds
     *                           Seconds a fingerprint is held. Expiry triggers a dedup-summary
     *                           dispatch on the next handleLog call.
     * @param \Closure(LogEntryInterface): string|null $fingerprint
     *                                                              Custom fingerprint callback. Defaults to "{level}:{message}".
     * @param ClockInterface $clock
     *                              Clock used for window tracking. Defaults to {@see UtcClock}. Inject a
     *                              test double to control time in unit tests.
     */
    public function __construct(
        private readonly LogDriverInterface $inner,
        private readonly int $windowSeconds = 60,
        private readonly ?\Closure $fingerprint = null,
        private readonly ClockInterface $clock = new UtcClock(),
    ) {}

    public function handleLog(LogEntryInterface $logItem): void
    {
        $this->evict();

        $key = $this->fingerprint instanceof \Closure
            ? ($this->fingerprint)($logItem)
            : $logItem->level->name . ':' . $logItem->message;

        if (!isset($this->pending[$key])) {
            $this->inner->handleLog($logItem);
            $this->pending[$key] = [
                'item' => $logItem,
                'firstSeen' => $this->clock->now(),
                'times' => [],
            ];

            return;
        }

        $this->pending[$key]['item'] = $logItem;
        $this->pending[$key]['times'][] = $this->clock->now();
    }

    /**
     * Re-dispatches all pending items that accumulated duplicates, then cascades
     * flush() to the inner driver if it implements FlushableInterface.
     *
     * Clears the entire pending state — fingerprints are treated as fresh after
     * a flush cycle. Items are lost if the inner driver throws during dispatch.
     */
    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $entry) {
            $this->dispatchEntry($entry);
        }

        if ($this->inner instanceof FlushableInterface) {
            $this->inner->flush();
        }
    }

    public function __destruct()
    {
        try {
            $this->flush();
        } catch (\Throwable) {
        }
    }

    private function evict(): void
    {
        if ($this->pending === []) {
            return;
        }

        $cutoff = $this->clock->now()->modify(\sprintf('-%d seconds', $this->windowSeconds));

        foreach (\array_keys($this->pending) as $key) {
            $entry = $this->pending[$key];

            if ($entry['firstSeen'] >= $cutoff) {
                continue;
            }

            unset($this->pending[$key]);
            $this->dispatchEntry($entry);
        }
    }

    /**
     * @param array{item: LogEntryInterface, firstSeen: DateTimeImmutable, times: list<DateTimeImmutable>} $entry
     */
    private function dispatchEntry(array $entry): void
    {
        if ($entry['times'] === []) {
            return;
        }

        $item = $entry['item'];
        $context = [
            'dedup_count' => \count($entry['times']),
            'dedup_times' => $entry['times'],
        ];

        if ($item instanceof ImmutableLogEntryInterface) {
            $item = $item->withContext($context);
        } else {
            $item = new LogEntry(
                $item->level,
                $item->message,
                $item->dateTime,
                \array_merge($item->context, $context),
            );
        }

        $this->inner->handleLog($item);
    }
}
