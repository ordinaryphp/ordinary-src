<?php

declare(strict_types=1);

namespace Ordinary\Container;

/**
 * Defines how service instances are shared within the container.
 *
 * Singleton: one instance is created and reused across all calls.
 * Transient: a new instance is created on every call to get().
 * Scoped: one instance per named scope; the instance is discarded when the scope ends.
 */
enum Scope
{
    case Singleton;
    case Transient;
    case Scoped;
}
