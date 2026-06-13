<?php

declare(strict_types=1);

namespace Ordinary\Error\Matcher;

use Ordinary\Error\ThrowableMatcherInterface;

/**
 * Passes when at least one composed matcher passes (logical OR).
 *
 * Returns `false` when no matchers are composed.
 */
final readonly class IsAny implements ThrowableMatcherInterface
{
    /** @var list<ThrowableMatcherInterface> */
    private array $matchers;

    public function __construct(ThrowableMatcherInterface ...$matchers)
    {
        $this->matchers = \array_values($matchers);
    }

    public function matches(\Throwable $throwable): bool
    {
        return \array_any($this->matchers, fn($matcher) => $matcher->matches($throwable));
    }
}
