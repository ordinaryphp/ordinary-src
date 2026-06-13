<?php

declare(strict_types=1);

namespace Ordinary\Error;

/**
 * Marker for handlers that must always execute on the calling thread.
 *
 * When an {@see ErrorHandler} or {@see ExceptionHandler} is configured with a
 * dispatcher, handlers that do NOT implement this interface are wrapped and
 * passed to the dispatcher. Implement this interface to bypass the dispatcher
 * and guarantee immediate, synchronous execution — for example, a handler that
 * rethrows the throwable as a recoverable exception.
 */
interface SynchronousHandlerInterface extends ThrowableHandlerInterface {}
