<?php

declare(strict_types=1);

namespace Ordinary\Error\Matcher;

use Ordinary\Error\ThrowableMatcherInterface;

/**
 * Matches throwables that are instances of a specific class or interface.
 */
final readonly class IsInstanceOf implements ThrowableMatcherInterface
{
    /**
     * @param class-string<\Throwable> $class Fully-qualified class or interface name to match against.
     */
    public function __construct(private string $class) {}

    public function matches(\Throwable $throwable): bool
    {
        return $throwable instanceof $this->class;
    }
}
