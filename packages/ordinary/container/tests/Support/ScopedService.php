<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Scoped service for testing per-scope instance isolation. */
final readonly class ScopedService
{
    public function __construct(
        public string $id,
    ) {}
}
