<?php

declare(strict_types=1);

namespace Ordinary\Container\Attribute;

use Attribute;

/**
 * Marks a class as a singleton-scoped service.
 *
 * Read by ContainerBuilder::autowireFromAttribute() to set Scope::Singleton automatically.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Singleton {}
