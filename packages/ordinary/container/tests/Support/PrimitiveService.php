<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Service with a required primitive and an optional primitive. Used for autowiring tests. */
final readonly class PrimitiveService
{
    public function __construct(
        public string $host,
        public int $port = 3306,
    ) {}
}
