<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Ordinary\Log\DateTimeFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateTimeFormatter::class)]
final class DateTimeFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_with_default_atom_format_in_utc(): void
    {
        $formatter = new DateTimeFormatter();
        $dt = new DateTimeImmutable('2024-06-01 12:00:00', new DateTimeZone('UTC'));

        $result = $formatter->formatDate($dt);

        $this->assertSame('2024-06-01T12:00:00Z', $result);
    }

    #[Test]
    public function it_formats_with_custom_format(): void
    {
        $formatter = new DateTimeFormatter('Y-m-d H:i:s');
        $dt = new DateTimeImmutable('2024-06-01 12:00:00', new DateTimeZone('UTC'));

        $this->assertSame('2024-06-01 12:00:00', $formatter->formatDate($dt));
    }

    #[Test]
    public function it_converts_timezone_before_formatting(): void
    {
        $formatter = new DateTimeFormatter('H:i', 'America/New_York');
        $dt = new DateTimeImmutable('2024-06-01 12:00:00', new DateTimeZone('UTC'));

        // UTC 12:00 is 08:00 in America/New_York (EDT, UTC-4)
        $this->assertSame('08:00', $formatter->formatDate($dt));
    }

    #[Test]
    public function it_accepts_datetimezone_object_directly(): void
    {
        $tz = new DateTimeZone('Europe/London');
        $formatter = new DateTimeFormatter('P', $tz);
        $dt = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'));

        // Europe/London is UTC+00:00 in winter
        $this->assertSame('+00:00', $formatter->formatDate($dt));
    }
}
