<?php

declare(strict_types=1);

namespace Ordinary\Error;

/**
 * Determines whether a throwable should be passed to a particular handler.
 */
interface ThrowableMatcherInterface
{
    public function matches(\Throwable $throwable): bool;
}
