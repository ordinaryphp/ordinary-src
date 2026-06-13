<?php

declare(strict_types=1);

namespace Ordinary\Error;

use Ordinary\Error\FailureHandler\ErrorLogFailureHandler;

/**
 * Invokable exception handler for use with `set_exception_handler`.
 *
 * Fans each uncaught throwable out to all registered handlers whose matchers
 * pass. Handlers implementing {@see SynchronousHandlerInterface} always run
 * immediately; others are dispatched through the optional `$dispatcher` closure.
 *
 * All handler exceptions are caught and forwarded to `$onFailure` rather than
 * propagated, because rethrowing inside a `set_exception_handler` callback
 * produces a fatal error.
 *
 * ```php
 * $handler = new ExceptionHandler();
 * $handler->add(new LogHandler($logger));
 * $registered = ExceptionHandler::register($handler, chainCurrent: true);
 * ```
 *
 * @see set_exception_handler()
 */
final class ExceptionHandler
{
    /** @var list<array{0: ThrowableHandlerInterface, 1: ThrowableMatcherInterface|null}> */
    private array $handlers = [];

    /**
     * @param null|\Closure(\Closure(): void): void $dispatcher
     *                                                          When provided, handlers that do not implement
     *                                                          {@see SynchronousHandlerInterface} are passed to this closure as a
     *                                                          zero-argument operation rather than being invoked immediately.
     * @param HandlerFailureHandlerInterface $onFailure
     *                                                  Called when any handler throws. Defaults to
     *                                                  {@see ErrorLogFailureHandler}.
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
     * Invoked by PHP for each uncaught throwable.
     */
    public function __invoke(\Throwable $throwable): void
    {
        foreach ($this->handlers as [$handler, $matcher]) {
            if ($matcher !== null && !$matcher->matches($throwable)) {
                continue;
            }

            $this->dispatch($handler, $throwable);
        }
    }

    /**
     * Registers `$handler` with PHP's exception handling machinery, or amends
     * an already-registered `ExceptionHandler` instance.
     *
     * When an `ExceptionHandler` is already registered, every handler from
     * `$handler` is appended to it and that existing instance is returned.
     *
     * When no `ExceptionHandler` is registered and `$chainCurrent` is `true`,
     * the previously registered callable (if any) is wrapped with
     * {@see CallableHandler} and appended.
     *
     * @param self|null $handler Pre-configured handler to register. A fresh
     *                           instance is created when `null`.
     * @param bool $chainCurrent Wrap and append the previously registered PHP
     *                           exception handler, if any.
     */
    public static function register(
        ?self $handler = null,
        bool $chainCurrent = false,
    ): self {
        $handler ??= new self();

        $existing = \set_exception_handler(null);
        \restore_exception_handler();

        if ($existing instanceof self) {
            foreach ($handler->handlers as [$h, $m]) {
                $existing->add($h, $m);
            }

            return $existing;
        }

        if ($chainCurrent && $existing !== null) {
            $handler->add(new CallableHandler($existing));
        }

        \set_exception_handler($handler);

        return $handler;
    }

    private function dispatch(ThrowableHandlerInterface $handler, \Throwable $throwable): void
    {
        if ($this->dispatcher instanceof \Closure && !($handler instanceof SynchronousHandlerInterface)) {
            ($this->dispatcher)(function () use ($handler, $throwable): void {
                try {
                    $handler->handle($throwable);
                } catch (\Throwable $failure) {
                    $this->onFailure->handleFailure($handler, $failure, $throwable);
                }
            });

            return;
        }

        try {
            $handler->handle($throwable);
        } catch (\Throwable $failure) {
            $this->onFailure->handleFailure($handler, $failure, $throwable);
        }
    }
}
