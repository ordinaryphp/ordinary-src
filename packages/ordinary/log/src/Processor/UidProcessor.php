<?php

declare(strict_types=1);

namespace Ordinary\Log\Processor;

use Ordinary\Log\ImmutableLogEntryInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogProcessorInterface;

/**
 * Stamps a unique identifier on every log item produced within the same process.
 *
 * The UID is generated once at construction time and reused for all subsequent
 * log items. Registering a fresh instance per request gives each request its own
 * UID, making it easy to correlate all log entries for a single operation:
 *
 * ```php
 * // Built-in hex generator (default)
 * $logger->addProcessor(new UidProcessor());
 *
 * // Custom generator — e.g. UUID v4 via a library
 * $logger->addProcessor(new UidProcessor(generator: fn () => Uuid::v4()->toString()));
 * ```
 *
 * The context key defaults to `"uid"` and is configurable. The built-in generator
 * produces a lowercase hex string of the requested character length (default 7);
 * `$length` is ignored when a custom `$generator` is supplied.
 */
final readonly class UidProcessor implements LogProcessorInterface
{
    private string $uid;

    /**
     * @param string $contextKey Key under which the UID is stored in context.
     * @param int $length Length of the generated hex UID (1–32). Ignored when $generator is supplied.
     * @param (\Closure(): string)|null $generator Custom UID generator called once at construction.
     *                                             Must return a non-empty string.
     *
     * @throws \UnexpectedValueException When $generator returns an empty string.
     */
    public function __construct(
        private string $contextKey = 'uid',
        private int $length = 7,
        ?\Closure $generator = null,
    ) {
        if ($generator instanceof \Closure) {
            $uid = $generator();

            if ($uid === '') {
                throw new \UnexpectedValueException('UID generator must return a non-empty string.');
            }

            $this->uid = $uid;
        } else {
            $bytes = \max(1, (int) \ceil($this->length / 2));
            $this->uid = \substr(\bin2hex(\random_bytes($bytes)), 0, $this->length);
        }
    }

    public function process(LogEntryInterface $logItem): LogEntryInterface
    {
        $context = [$this->contextKey => $this->uid];

        if ($logItem instanceof ImmutableLogEntryInterface) {
            return $logItem->withContext($context);
        }

        return new LogEntry(
            $logItem->level,
            $logItem->message,
            $logItem->dateTime,
            \array_merge($logItem->context, $context),
        );
    }

    /** Returns the UID assigned to this processor instance. */
    public function getUid(): string
    {
        return $this->uid;
    }
}
