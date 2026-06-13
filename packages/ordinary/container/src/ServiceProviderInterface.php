<?php

declare(strict_types=1);

namespace Ordinary\Container;

/**
 * Bundles a cohesive group of service definitions for registration as a single unit.
 *
 * Implement this interface to encapsulate all bindings for a feature or module,
 * then pass an instance to ContainerBuilder::provider().
 */
interface ServiceProviderInterface
{
    /** Register all services provided by this provider onto the builder. */
    public function register(ContainerBuilder $builder): void;
}
