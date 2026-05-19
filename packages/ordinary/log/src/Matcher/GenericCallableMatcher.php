<?php

declare(strict_types=1);

namespace Ordinary\Log\Matcher;

use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogMatcherInterface;

/**
 * Wraps a closure as a {@see LogMatcherInterface}.
 *
 * Use this when you need a quick inline matcher without defining a dedicated class:
 *
 * ```php
 * $logger->add($driver, matcher: new GenericCallableMatcher(
 *     fn(LogItemInterface $item) => isset($item->context['user_id']),
 * ));
 * ```
 */
final class GenericCallableMatcher implements LogMatcherInterface
{
    /** @param \Closure(LogItemInterface): bool $matcher */
    public function __construct(private readonly \Closure $matcher) {}

    public function matches(LogItemInterface $logItem): bool
    {
        return ($this->matcher)($logItem);
    }
}
