<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeInterface;

/**
 * Extends {@see LogItemInterface} with wither methods for functional transformation.
 *
 * Each method returns a new instance (or `$this` for mutable implementations) with
 * the specified field replaced. Implementations must not mutate the original item.
 *
 * {@see GenericLogItem} is the canonical implementation. {@see GenericCallableProcessor}
 * passes items as this type to processor closures.
 *
 * ```php
 * $logger->addProcessor(new GenericCallableProcessor(
 *     fn(ImmutableLogItemInterface $item) => $item->withContext(['request_id' => $requestId]),
 * ));
 * ```
 */
interface ImmutableLogItemInterface extends LogItemInterface
{
    public function withLevel(LogLevel $level): static;

    public function withMessage(string $message): static;

    public function withDateTime(DateTimeInterface $dateTime): static;

    /**
     * Returns a new instance with the given context entries merged in.
     *
     * @param array<string, mixed> $context
     */
    public function withContext(array $context): static;
}
