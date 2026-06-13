<?php

declare(strict_types=1);

namespace Ordinary\Container\Attribute;

use Attribute;

/**
 * Overrides which container binding is injected for a specific constructor parameter.
 *
 * When placed on a parameter during autowiring, the given $id is resolved from the
 * container instead of using the parameter's declared type as the lookup key.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    public function __construct(
        public string $id,
    ) {}
}
