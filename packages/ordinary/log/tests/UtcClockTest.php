<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Ordinary\Log\UtcClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(UtcClock::class)]
final class UtcClockTest extends TestCase
{
    #[Test]
    public function it_implements_psr_clock_interface(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(ClockInterface::class, new UtcClock());
    }

    #[Test]
    public function now_returns_datetime_immutable(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(DateTimeImmutable::class, new UtcClock()->now());
    }

    #[Test]
    public function now_returns_utc_timezone(): void
    {
        $this->assertSame('UTC', new UtcClock()->now()->getTimezone()->getName());
    }

    #[Test]
    public function now_returns_current_time(): void
    {
        $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = new UtcClock()->now();
        $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->assertGreaterThanOrEqual($before, $now);
        $this->assertLessThanOrEqual($after, $now);
    }
}
