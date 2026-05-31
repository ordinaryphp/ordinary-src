<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * Wraps a closure as a {@see LogProcessorInterface}.
 *
 * The closure receives an {@see ImmutableLogEntryInterface} and returns a transformed
 * one. Use wither methods to produce the new item:
 *
 * ```php
 * $logger->addProcessor(new CallableProcessor(
 *     fn(ImmutableLogEntryInterface $item) => $item->withContext(['request_id' => $requestId]),
 * ));
 * ```
 */
final readonly class CallableProcessor implements LogProcessorInterface
{
    /** @param \Closure(ImmutableLogEntryInterface): ImmutableLogEntryInterface $processor */
    public function __construct(private \Closure $processor) {}

    public function process(LogEntryInterface $logItem): LogEntryInterface
    {
        if (!($logItem instanceof ImmutableLogEntryInterface)) {
            $logItem = new LogEntry($logItem->level, $logItem->message, $logItem->dateTime, $logItem->context);
        }

        return ($this->processor)($logItem);
    }
}
