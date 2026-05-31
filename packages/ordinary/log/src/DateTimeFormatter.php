<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class DateTimeFormatter implements DateTimeFormatterInterface
{
    public private(set) DateTimeZone $timezone {
        set(DateTimeZone|string $value) {
            $this->timezone = $value instanceof DateTimeZone ? $value : new DateTimeZone($value);
        }
    }

    public function __construct(
        public readonly string $format = 'Y-m-d\TH:i:sp',
        DateTimeZone|string $timezone = new DateTimeZone('UTC'),
    ) {
        $this->timezone = $timezone;
    }

    public function formatDate(DateTimeInterface $dateTime): string
    {
        return DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone($this->timezone)
            ->format($this->format);
    }
}
