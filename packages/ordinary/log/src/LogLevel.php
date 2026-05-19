<?php

declare(strict_types=1);

namespace Ordinary\Log;

enum LogLevel
{
    case Emergency;
    case Alert;
    case Critical;
    case Error;
    case Warning;
    case Notice;
    case Info;
    case Debug;

    public function getFullName(): string
    {
        return \strtolower($this->name);
    }

    /**
     * Returns the shortest commonly-recognisable abbreviation for this level,
     * used as the minimum prefix length accepted by {@see self::fromString()}.
     */
    public function getShortestIdentifiablePrefix(): string
    {
        return match ($this) {
            self::Emergency => 'emer',
            self::Alert => 'aler',
            self::Critical => 'crit',
            self::Error => 'err',
            self::Warning => 'warn',
            self::Notice => 'noti',
            self::Info => 'info',
            self::Debug => 'deb',
        };
    }

    /**
     * Resolves a string to a LogLevel.
     *
     * Accepts full names (case-insensitive) or any unambiguous prefix that is
     * at least as long as {@see self::getShortestIdentifiablePrefix()} and is
     * itself a prefix of the full level name.
     *
     * @throws \ValueError when the string cannot be resolved
     */
    public static function fromString(string $value): self
    {
        $normalized = \strtolower(\trim($value));

        $exact = \array_find(
            self::cases(),
            fn(self $case): bool => $case->getFullName() === $normalized,
        );

        if ($exact instanceof self) {
            return $exact;
        }

        $prefix = \array_find(
            self::cases(),
            fn(self $case): bool => \str_starts_with($case->getFullName(), $normalized)
                && \strlen($normalized) >= \strlen($case->getShortestIdentifiablePrefix()),
        );

        if ($prefix instanceof self) {
            return $prefix;
        }

        throw new \ValueError(\sprintf('"%s" is not a valid log level', $value));
    }

    public function isHigherThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }

    public function isLowerThan(self $other): bool
    {
        return $this->severity() < $other->severity();
    }

    public function isSameAs(self $other): bool
    {
        return $this === $other;
    }

    /**
     * Returns negative, zero, or positive when this level is lower than,
     * equal to, or higher than $other respectively.
     */
    public function compareTo(self $other): int
    {
        return $this->severity() <=> $other->severity();
    }

    private function severity(): int
    {
        return match ($this) {
            self::Debug => 0,
            self::Info => 1,
            self::Notice => 2,
            self::Warning => 3,
            self::Error => 4,
            self::Critical => 5,
            self::Alert => 6,
            self::Emergency => 7,
        };
    }
}
