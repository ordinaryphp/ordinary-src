<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeInterface;
use InvalidArgumentException;

final class GenericLogItem implements ImmutableLogItemInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly LogLevel $level,
        public readonly string $message,
        public readonly DateTimeInterface $dateTime,
        public readonly array $context = [],
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
     * Returns a new instance with the given user-context entries merged in.
     *
     * Rejects any reserved key — use {@see self::withReservedContext()} for those.
     *
     * @param array<string, mixed> $context
     *
     * @throws InvalidArgumentException when $context contains any reserved key
     */
    public function withContext(array $context): static
    {
        foreach (self::RESERVED_KEYS as $key) {
            if (\array_key_exists($key, $context)) {
                throw new InvalidArgumentException(
                    \sprintf('"%s" is a reserved context key', $key),
                );
            }
        }

        return clone($this, ['context' => \array_merge($this->context, $context)]);
    }

    /**
     * Returns a new instance with the given reserved-context entries merged in.
     *
     * Only keys listed in {@see LogItemInterface::RESERVED_KEYS} are accepted.
     *
     * @param array<string, mixed> $context
     *
     * @throws InvalidArgumentException when $context contains a non-reserved key
     */
    public function withReservedContext(array $context): static
    {
        foreach (\array_keys($context) as $key) {
            if (!\in_array($key, self::RESERVED_KEYS, true)) {
                throw new InvalidArgumentException(
                    \sprintf('"%s" is not a reserved context key', $key),
                );
            }
        }

        return clone($this, ['context' => \array_merge($this->context, $context)]);
    }

}
