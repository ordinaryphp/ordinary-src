<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/**
 * Service that depends on ServiceInterface.
 *
 * Used with contextual bindings: when()->needs(ServiceInterface::class)->give(ConcreteB::class)
 * should inject ConcreteB even though the default alias resolves to ConcreteA.
 */
final readonly class ContextualConsumer
{
    public function __construct(
        public ServiceInterface $service,
    ) {}
}
