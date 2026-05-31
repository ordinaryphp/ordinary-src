<?php

declare(strict_types=1);

namespace Ordinary\Log\Processor;

use Ordinary\Log\ImmutableLogEntryInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogProcessorInterface;

/**
 * Adds current memory usage to every log item.
 *
 * Adds `memory.usage` (bytes) and, when {@see $includePeak} is enabled,
 * `memory.peak_usage` (bytes):
 *
 * ```php
 * $logger->addProcessor(new MemoryUsageProcessor(includePeak: true));
 * ```
 *
 * Pass `realUsage: true` to get the true size allocated by the system rather
 * than the amount used by emalloc (see {@see memory_get_usage()}).
 */
final readonly class MemoryUsageProcessor implements LogProcessorInterface
{
    /**
     * @param bool $realUsage When true, report memory allocated by the system.
     * @param bool $includePeak Also add `memory.peak_usage` to each item.
     */
    public function __construct(
        private bool $realUsage = false,
        private bool $includePeak = false,
    ) {}

    public function process(LogEntryInterface $logItem): LogEntryInterface
    {
        $context = ['memory.usage' => \memory_get_usage($this->realUsage)];

        if ($this->includePeak) {
            $context['memory.peak_usage'] = \memory_get_peak_usage($this->realUsage);
        }

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
}
