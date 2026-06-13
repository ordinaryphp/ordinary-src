<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Participates in a circular dependency with CircularB for cycle-detection tests. */
final readonly class CircularA
{
    public function __construct(
        public CircularB $b,
    ) {}
}
