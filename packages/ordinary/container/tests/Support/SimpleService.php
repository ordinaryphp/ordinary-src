<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Minimal concrete service with no dependencies. Used across multiple test scenarios. */
final class SimpleService
{
    public function value(): string
    {
        return 'simple';
    }
}
