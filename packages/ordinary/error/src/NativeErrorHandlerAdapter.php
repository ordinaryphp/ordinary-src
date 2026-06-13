<?php

declare(strict_types=1);

namespace Ordinary\Error;

/**
 * Adapts a native `set_error_handler` callback for use inside an {@see ErrorHandler}.
 *
 * Useful for chaining a previously registered PHP error handler. The adapted
 * callable is only invoked when the throwable is an {@see \ErrorException};
 * other throwable types are silently ignored.
 */
final class NativeErrorHandlerAdapter implements ThrowableHandlerInterface
{
    /** @var callable(int, string, string, int): bool */
    private $callable;

    /** @param callable(int, string, string, int): bool $callable */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function handle(\Throwable $throwable): void
    {
        if (!($throwable instanceof \ErrorException)) {
            return;
        }

        ($this->callable)(
            $throwable->getSeverity(),
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
        );
    }
}
