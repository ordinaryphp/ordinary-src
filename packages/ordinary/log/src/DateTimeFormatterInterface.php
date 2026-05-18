<?php

declare(strict_types=1);

namespace Ordinary\Log;

use DateTimeInterface;

interface DateTimeFormatterInterface
{
    public function formatDate(DateTimeInterface $dateTime): string;
}
