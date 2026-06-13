<?php

declare(strict_types=1);

namespace Ordinary\Error\Matcher;

use Ordinary\Error\ThrowableMatcherInterface;

/**
 * Inverts another matcher (logical NOT).
 */
final readonly class IsNot implements ThrowableMatcherInterface
{
    public function __construct(private ThrowableMatcherInterface $matcher) {}

    public function matches(\Throwable $throwable): bool
    {
        return !$this->matcher->matches($throwable);
    }
}
