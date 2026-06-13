<?php

declare(strict_types=1);

namespace Ordinary\Error;

/**
 * Receives and processes a throwable produced by an error or exception handler.
 */
interface ThrowableHandlerInterface
{
    public function handle(\Throwable $throwable): void;
}
