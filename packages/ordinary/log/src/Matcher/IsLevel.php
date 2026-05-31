<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\LogMatcherInterface;

final readonly class IsLevel implements LogMatcherInterface
{
    /** @var list<LogLevel> */
    private array $levels;

    public function __construct(LogLevel ...$levels)
    {
        $this->levels = \array_values($levels);
    }

    public function matches(LogEntryInterface $logItem): bool
    {
        return \array_any($this->levels, fn(LogLevel $level): bool => $logItem->level->isSameAs($level));
    }
}
