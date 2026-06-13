<?php

declare(strict_types=1);

namespace Ordinary\Error\Matcher;

use Ordinary\Error\ThrowableMatcherInterface;

/**
 * Passes only when every composed matcher passes (logical AND).
 *
 * Returns `true` when no matchers are composed (vacuous truth).
 */
final readonly class IsAll implements ThrowableMatcherInterface
{
    /** @var list<ThrowableMatcherInterface> */
    private array $matchers;

    public function __construct(ThrowableMatcherInterface ...$matchers)
    {
        $this->matchers = \array_values($matchers);
    }

    public function matches(\Throwable $throwable): bool
    {
        return \array_all($this->matchers, fn($matcher) => $matcher->matches($throwable));
    }
}
