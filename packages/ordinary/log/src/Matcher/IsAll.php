<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogMatcherInterface;

final readonly class IsAll implements LogMatcherInterface
{
    /** @var list<LogMatcherInterface> */
    private array $matchers;

    public function __construct(LogMatcherInterface ...$matchers)
    {
        $this->matchers = \array_values($matchers);
    }

    public function matches(LogEntryInterface $logItem): bool
    {
        return \array_all($this->matchers, fn($matcher) => $matcher->matches($logItem));
    }
}
