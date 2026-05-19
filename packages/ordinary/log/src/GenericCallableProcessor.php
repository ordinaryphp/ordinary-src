<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * Wraps a closure as a {@see LogProcessorInterface}.
 *
 * The closure receives an {@see ImmutableLogItemInterface} and returns a transformed
 * one. Use wither methods to produce the new item:
 *
 * ```php
 * $logger->addProcessor(new GenericCallableProcessor(
 *     fn(ImmutableLogItemInterface $item) => $item->withContext(['request_id' => $requestId]),
 * ));
 * ```
 */
final class GenericCallableProcessor implements LogProcessorInterface
{
    /** @param \Closure(ImmutableLogItemInterface): ImmutableLogItemInterface $processor */
    public function __construct(private readonly \Closure $processor) {}

    public function process(LogItemInterface $logItem): LogItemInterface
    {
        if (!($logItem instanceof ImmutableLogItemInterface)) {
            $logItem = new GenericLogItem($logItem->level, $logItem->message, $logItem->dateTime, $logItem->context);
        }

        return ($this->processor)($logItem);
    }
}
