<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogMatcherInterface;

final readonly class IsNot implements LogMatcherInterface
{
    public function __construct(private LogMatcherInterface $matcher) {}

    public function matches(LogEntryInterface $logItem): bool
    {
        return !$this->matcher->matches($logItem);
    }
}
