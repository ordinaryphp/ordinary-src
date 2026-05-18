<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogMatcherInterface;

final class IsNot implements LogMatcherInterface
{
    public function __construct(private readonly LogMatcherInterface $matcher) {}

    public function matches(LogItemInterface $logItem): bool
    {
        return !$this->matcher->matches($logItem);
    }
}
