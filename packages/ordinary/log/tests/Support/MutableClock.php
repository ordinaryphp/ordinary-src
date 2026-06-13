<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A controllable clock for unit tests.
 *
 * Start with a fixed time and call advance() to move the clock forward.
 */
final class MutableClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $current) {}

    /**
     * Advances the clock by the given number of seconds.
     */
    public function advance(int $seconds): void
    {
        $this->current = $this->current->modify(\sprintf('+%d seconds', $seconds));
    }

    public function now(): DateTimeImmutable
    {
        return $this->current;
    }
}
