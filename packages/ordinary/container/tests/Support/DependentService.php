<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Service that requires SimpleService as a constructor dependency. */
final readonly class DependentService
{
    public function __construct(
        public SimpleService $simple,
    ) {}
}
