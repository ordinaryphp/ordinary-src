<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogMatcherInterface;

final class IsAll implements LogMatcherInterface
{
    /** @var list<LogMatcherInterface> */
    private readonly array $matchers;

    /** @param list<LogMatcherInterface> $matchers */
    public function __construct(array $matchers)
    {
        $this->matchers = $matchers;
    }

    public function matches(LogItemInterface $logItem): bool
    {
        return array_all($this->matchers, fn($matcher) => $matcher->matches($logItem));
    }
}
