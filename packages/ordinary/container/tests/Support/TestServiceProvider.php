<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

use Ordinary\Container\ContainerBuilder;
use Ordinary\Container\ServiceProviderInterface;

/** Registers SimpleService and DependentService; used to test provider registration. */
final class TestServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->singleton(SimpleService::class, static fn(): SimpleService => new SimpleService());
        $builder->singleton(DependentService::class, static fn($c): DependentService => new DependentService($c->get(SimpleService::class)));
    }
}
