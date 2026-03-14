<?php

declare(strict_types=1);

namespace Ordinary\Uid;

use DateTimeImmutable;

/**
 * Interface for Ordinary Universal Identifier (OUID).
 *
 * Format: <namespace>-<time-seconds><time-microsecond>-<random-bytes>
 */
interface OuidInterface
{
    /**
     * The full OUID string.
     */
    public string $value { get; }

    /**
     * The namespace portion of the OUID.
     */
    public string $namespace { get; }

    /**
     * The UTC datetime corresponding to the seconds and microseconds fields.
     */
    public DateTimeImmutable $datetime { get; }

    /**
     * The raw random bytes.
     */
    public string $randomBytes { get; }
}
