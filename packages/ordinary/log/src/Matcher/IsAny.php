<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogMatcherInterface;

final readonly class IsAny implements LogMatcherInterface
{
    /** @param list<LogMatcherInterface> $matchers */
    public function __construct(private array $matchers) {}

    public function matches(LogItemInterface $logItem): bool
    {
        return \array_any($this->matchers, fn($matcher) => $matcher->matches($logItem));
    }
}
