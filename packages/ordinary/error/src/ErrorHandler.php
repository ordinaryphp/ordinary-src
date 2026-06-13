<?php

declare(strict_types=1);

namespace Ordinary\Error;

use Ordinary\Error\FailureHandler\ErrorLogFailureHandler;

/**
 * Invokable error handler for use with `set_error_handler`.
 *
 * Converts each PHP error into an {@see ErrorException} and fans it out to all
 * registered handlers whose matchers pass. Handlers implementing
 * {@see SynchronousHandlerInterface} always run immediately; others are
 * dispatched through the optional `$dispatcher` closure.
 *
 * ```php
 * $handler = new ErrorHandler();
 * $handler->add(new LogHandler($logger), new IsErrorSeverity(E_WARNING, E_NOTICE));
 * $registered = ErrorHandler::register($handler, chainCurrent: true);
 * ```
 *
 * @see set_error_handler()
 */
final class ErrorHandler
{
    /** @var list<array{0: ThrowableHandlerInterface, 1: ThrowableMatcherInterface|null}> */
    private array $handlers = [];

    /**
     * @param null|\Closure(\Closure(): void): void $dispatcher
     *                                                          When provided, handlers that do not implement
     *                                                          {@see SynchronousHandlerInterface} are passed to this closure as a
     *                                                          zero-argument operation rather than being invoked immediately.
     * @param HandlerFailureHandlerInterface $onFailure
     *                                                  Called when an asynchronously dispatched handler throws. Defaults to
     *                                                  {@see ErrorLogFailureHandler}. Synchronous handler exceptions are
     *                                                  propagated to the caller instead.
     */
    public function __construct(
        private readonly ?\Closure $dispatcher = null,
        private readonly HandlerFailureHandlerInterface $onFailure = new ErrorLogFailureHandler(),
    ) {}

    /**
     * Registers a handler, optionally filtered by a matcher.
     *
     * Handlers are invoked in registration order. A handler is skipped when its
     * matcher returns `false`.
     */
    public function add(
        ThrowableHandlerInterface $handler,
        ?ThrowableMatcherInterface $matcher = null,
    ): self {
        $this->handlers[] = [$handler, $matcher];

        return $this;
    }

    /**
     * Invoked by PHP for each raised error.
     *
     * Returns `false` for errors suppressed by the `@` operator so that PHP's
     * built-in handler can process them. Returns `true` otherwise, preventing
     * the default handler from running.
     *
     * @throws \Throwable when a synchronous handler throws
     */
    public function __invoke(
        int $errno,
        string $errstr,
        string $errfile = '',
        int $errline = 0,
    ): bool {
        if ((\error_reporting() & $errno) === 0) {
            return false;
        }

        $exception = ErrorException::fromPhpError($errno, $errstr, $errfile, $errline);

        foreach ($this->handlers as [$handler, $matcher]) {
            if ($matcher !== null && !$matcher->matches($exception)) {
                continue;
            }

            $this->dispatch($handler, $exception);
        }

        return true;
    }

    /**
     * Registers `$handler` with PHP's error handling machinery, or amends an
     * already-registered `ErrorHandler` instance.
     *
     * When an `ErrorHandler` is already registered, every handler from `$handler`
     * is appended to it and that existing instance is returned. This allows
     * multiple call sites (e.g. a framework and a plugin) to contribute handlers
     * without replacing each other.
     *
     * When no `ErrorHandler` is registered and `$chainCurrent` is `true`, the
     * previously registered callable (if any) is wrapped with
     * {@see NativeErrorHandlerAdapter} and appended so it still receives errors.
     *
     * @param self|null $handler Pre-configured handler to register. A fresh
     *                           instance is created when `null`.
     * @param bool $chainCurrent Wrap and append the previously registered PHP
     *                           error handler, if any.
     * @param int $types Error types to receive; passed as the second
     *                   argument to `set_error_handler`.
     */
    public static function register(
        ?self $handler = null,
        bool $chainCurrent = false,
        int $types = E_ALL,
    ): self {
        $handler ??= new self();

        $existing = \set_error_handler(null);
        \restore_error_handler();

        if ($existing instanceof self) {
            foreach ($handler->handlers as [$h, $m]) {
                $existing->add($h, $m);
            }

            return $existing;
        }

        if ($chainCurrent && $existing !== null) {
            $handler->add(new NativeErrorHandlerAdapter($existing));
        }

        \set_error_handler($handler, $types);

        return $handler;
    }

    private function dispatch(ThrowableHandlerInterface $handler, ErrorException $exception): void
    {
        if ($this->dispatcher instanceof \Closure && !($handler instanceof SynchronousHandlerInterface)) {
            ($this->dispatcher)(function () use ($handler, $exception): void {
                try {
                    $handler->handle($exception);
                } catch (\Throwable $throwable) {
                    $this->onFailure->handleFailure($handler, $throwable, $exception);
                }
            });

            return;
        }

        $handler->handle($exception);
    }
}
