<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogMatcherInterface;

/**
 * Wraps a closure as a {@see LogMatcherInterface}.
 *
 * Use this when you need a quick inline matcher without defining a dedicated class:
 *
 * ```php
 * $logger->add($driver, matcher: new CallableMatcher(
 *     fn(LogEntryInterface $item) => isset($item->context['user_id']),
 * ));
 * ```
 */
final readonly class CallableMatcher implements LogMatcherInterface
{
    /** @param \Closure(LogEntryInterface): bool $matcher */
    public function __construct(private \Closure $matcher) {}

    public function matches(LogEntryInterface $logItem): bool
    {
        return ($this->matcher)($logItem);
    }
}
