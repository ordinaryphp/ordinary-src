<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/**
 * Service with a nullable primitive constructor parameter and no default.
 *
 * Used to verify that autowire() injects null when a parameter is nullable,
 * has no default value, and is a built-in type (so it cannot be resolved from the container).
 */
final readonly class NullableParamService
{
    public function __construct(
        public ?string $value,
    ) {}
}
