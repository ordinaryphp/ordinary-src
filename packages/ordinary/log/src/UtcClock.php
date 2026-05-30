<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * Returns the current time in UTC.
 *
 * Used as the default clock in Logger and DeduplicatingDriver when no explicit
 * ClockInterface is provided.
 */
final class UtcClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
