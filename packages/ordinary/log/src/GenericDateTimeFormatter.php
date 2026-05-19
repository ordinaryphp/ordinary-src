<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final readonly class GenericDateTimeFormatter implements DateTimeFormatterInterface
{
    private DateTimeZone $timezone;

    public function __construct(
        private string $format = \DATE_ATOM,
        DateTimeZone|string $timezone = 'UTC',
    ) {
        $this->timezone = $timezone instanceof DateTimeZone
            ? $timezone
            : new DateTimeZone($timezone);
    }

    public function formatDate(DateTimeInterface $dateTime): string
    {
        return DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone($this->timezone)
            ->format($this->format);
    }
}
