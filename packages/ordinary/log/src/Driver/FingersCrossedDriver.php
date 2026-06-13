<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Ordinary\Log\FlushableInterface;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;

/**
 * Buffers all log items silently until an item at or above the activation level
 * is received, then flushes the entire buffer to the inner driver.
 *
 * This pattern gives you full diagnostic context in production without drowning
 * in verbose logs during normal operation:
 *
 * ```php
 * $logger->add(new FingersCrossedDriver(
 *     inner: new StreamDriver(fopen('/var/log/app.log', 'a')),
 *     activationLevel: LogLevel::Error,
 * ));
 * ```
 *
 * After activation all subsequent items go directly to the inner driver without
 * buffering. If `$resetOnFlush` is `true`, calling `flush()` returns the driver to
 * its initial buffering state, useful for request-scoped logging.
 *
 * If the activation threshold is never reached, `flush()` discards the buffer silently.
 *
 * Set `$maxBuffer` to a positive integer to cap memory usage; once the buffer is full
 * the oldest item is dropped to make room for the newest.
 */
final class FingersCrossedDriver implements LogDriverInterface, FlushableInterface
{
    /** @var list<LogEntryInterface> */
    private array $buffer = [];

    private bool $activated = false;

    /**
     * @param LogDriverInterface $inner Receives all items once activated.
     * @param LogLevel $activationLevel Minimum level that triggers activation.
     * @param int $maxBuffer Maximum buffer size; 0 = unlimited.
     * @param bool $resetOnFlush Return to buffering state after flush().
     */
    public function __construct(
        private readonly LogDriverInterface $inner,
        private readonly LogLevel $activationLevel = LogLevel::Warning,
        private readonly int $maxBuffer = 0,
        private readonly bool $resetOnFlush = false,
    ) {}

    public function handleLog(LogEntryInterface $logItem): void
    {
        if ($this->activated) {
            $this->inner->handleLog($logItem);

            return;
        }

        if (!$logItem->level->isLowerThan($this->activationLevel)) {
            $this->activated = true;
            $buffer = $this->buffer;
            $this->buffer = [];

            foreach ($buffer as $buffered) {
                $this->inner->handleLog($buffered);
            }

            $this->inner->handleLog($logItem);

            return;
        }

        $this->buffer[] = $logItem;

        if ($this->maxBuffer > 0 && \count($this->buffer) > $this->maxBuffer) {
            \array_shift($this->buffer);
        }
    }

    /**
     * Cascades flush to the inner driver when activated.
     *
     * When not yet activated, the buffer is discarded — nothing is sent to the
     * inner driver. If `$resetOnFlush` is set, the driver returns to its initial
     * buffering state regardless of whether it was activated.
     */
    public function flush(): void
    {
        if (!$this->activated) {
            $this->buffer = [];

            return;
        }

        if ($this->inner instanceof FlushableInterface) {
            $this->inner->flush();
        }

        if ($this->resetOnFlush) {
            $this->activated = false;
            $this->buffer = [];
        }
    }

    /** Returns true once the activation level has been seen. */
    public function isActivated(): bool
    {
        return $this->activated;
    }
}
