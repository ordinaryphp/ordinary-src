<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\LogMatcherInterface;

final class IsLevel implements LogMatcherInterface
{
    /** @var list<LogLevel> */
    private readonly array $levels;

    public function __construct(LogLevel ...$levels)
    {
        $this->levels = $levels;
    }

    public function matches(LogItemInterface $logItem): bool
    {
        foreach ($this->levels as $level) {
            if ($logItem->level->isSameAs($level)) {
                return true;
            }
        }

        return false;
    }
}
