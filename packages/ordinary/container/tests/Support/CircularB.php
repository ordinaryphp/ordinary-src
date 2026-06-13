<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Participates in a circular dependency with CircularA for cycle-detection tests. */
final readonly class CircularB
{
    public function __construct(
        public CircularA $a,
    ) {}
}
