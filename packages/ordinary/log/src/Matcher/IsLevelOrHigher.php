<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\LogMatcherInterface;

final readonly class IsLevelOrHigher implements LogMatcherInterface
{
    public function __construct(private LogLevel $level) {}

    public function matches(LogItemInterface $logItem): bool
    {
        return $logItem->level->compareTo($this->level) >= 0;
    }
}
