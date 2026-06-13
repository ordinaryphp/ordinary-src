<?php

declare(strict_types=1);

namespace Ordinary\Error\Matcher;

use Ordinary\Error\ThrowableMatcherInterface;

/**
 * Wraps a `callable(\Throwable): bool` as a {@see ThrowableMatcherInterface}.
 *
 * Use this to register inline filter logic without creating a dedicated class.
 */
final class CallableMatcher implements ThrowableMatcherInterface
{
    /** @var callable(\Throwable): bool */
    private $callable;

    /** @param callable(\Throwable): bool $callable */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function matches(\Throwable $throwable): bool
    {
        return ($this->callable)($throwable);
    }
}
