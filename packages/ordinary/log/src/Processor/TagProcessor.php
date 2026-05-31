<?php

declare(strict_types=1);

namespace Ordinary\Log\Processor;

use Ordinary\Log\ImmutableLogEntryInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogProcessorInterface;

/**
 * Adds a fixed set of key-value pairs to every log item.
 *
 * Useful for stamping environment info, deployment metadata, or application
 * identifiers on every log entry without repeating them at each call site:
 *
 * ```php
 * $logger->addProcessor(new TagProcessor([
 *     'env'     => 'production',
 *     'release' => 'v2.3.1',
 *     'service' => 'payment-api',
 * ]));
 * ```
 *
 * Existing context keys are overwritten by the provided tags.
 */
final readonly class TagProcessor implements LogProcessorInterface
{
    /** @param array<string, mixed> $tags Key-value pairs stamped on every log item. */
    public function __construct(private array $tags) {}

    public function process(LogEntryInterface $logItem): LogEntryInterface
    {
        if ($logItem instanceof ImmutableLogEntryInterface) {
            return $logItem->withContext($this->tags);
        }

        return new LogEntry(
            $logItem->level,
            $logItem->message,
            $logItem->dateTime,
            \array_merge($logItem->context, $this->tags),
        );
    }
}
