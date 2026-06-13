<?php

declare(strict_types=1);

namespace Ordinary\Container\Attribute;

use Attribute;

/**
 * Marks a class as a transient-scoped service.
 *
 * Read by ContainerBuilder::autowireFromAttribute() to set Scope::Transient automatically.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Transient {}
