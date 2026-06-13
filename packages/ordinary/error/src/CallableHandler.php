<?php

declare(strict_types=1);

namespace Ordinary\Error;

/**
 * Wraps a `callable(\Throwable): void` as a {@see ThrowableHandlerInterface}.
 *
 * Use this to register inline closures without creating a dedicated class.
 */
final class CallableHandler implements ThrowableHandlerInterface
{
    /** @var callable(\Throwable): void */
    private $callable;

    /** @param callable(\Throwable): void $callable */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function handle(\Throwable $throwable): void
    {
        ($this->callable)($throwable);
    }
}
