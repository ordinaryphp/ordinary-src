<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeInterface;

final readonly class LogEntry implements ImmutableLogEntryInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public LogLevel $level,
        public string $message,
        public DateTimeInterface $dateTime,
        public array $context = [],
    ) {}

    public function withLevel(LogLevel $level): static
    {
        return clone($this, ['level' => $level]);
    }

    public function withMessage(string $message): static
    {
        return clone($this, ['message' => $message]);
    }

    public function withDateTime(DateTimeInterface $dateTime): static
    {
        return clone($this, ['dateTime' => $dateTime]);
    }

    /**
     * Returns a new instance with the given context entries merged in.
     *
     * All keys are accepted, including reserved ones such as
     * {@see LogEntryInterface::RESERVED_EXCEPTION}. Existing keys are overwritten
     * by the incoming values.
     *
     * @param array<string, mixed> $context
     */
    public function withContext(array $context): static
    {
        return clone($this, ['context' => \array_merge($this->context, $context)]);
    }
}
