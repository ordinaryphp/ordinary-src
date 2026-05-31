<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeInterface;

/**
 * Extends {@see LogEntryInterface} with wither methods for functional transformation.
 *
 * Each method returns a new instance with the specified field replaced. Implementations
 * must not mutate the original entry.
 *
 * {@see LogEntry} is the canonical implementation. {@see CallableProcessor}
 * passes entries as this type to processor closures.
 *
 * ```php
 * $logger->addProcessor(new CallableProcessor(
 *     fn(ImmutableLogEntryInterface $entry) => $entry->withContext(['request_id' => $requestId]),
 * ));
 * ```
 */
interface ImmutableLogEntryInterface extends LogEntryInterface
{
    public function withLevel(LogLevel $level): static;

    public function withMessage(string $message): static;

    public function withDateTime(DateTimeInterface $dateTime): static;

    /**
     * Returns a new instance with the given context entries merged in.
     *
     * All keys are accepted, including reserved ones such as
     * {@see LogEntryInterface::RESERVED_EXCEPTION}. Existing keys are overwritten
     * by the incoming values.
     *
     * @param array<string, mixed> $context
     */
    public function withContext(array $context): static;
}
