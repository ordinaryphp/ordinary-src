<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

use Ordinary\Container\Attribute\Inject;

/**
 * Service that uses #[Inject] to override the resolved binding for one parameter.
 *
 * The parameter type is ServiceInterface, but the inject attribute forces ConcreteB to be used.
 */
final readonly class InjectableService
{
    public function __construct(
        #[Inject(ConcreteB::class)]
        public ServiceInterface $service,
    ) {}
}
