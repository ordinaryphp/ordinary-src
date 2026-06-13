<?php

declare(strict_types=1);

namespace Ordinary\Container;

/**
 * Immutable description of how a single service is constructed and shared.
 *
 * Created by ContainerBuilder and consumed by Container at resolution time.
 *
 * @phpstan-type Factory \Closure(ContainerInterface): mixed
 */
final readonly class ServiceDefinition
{
    /**
     * @param \Closure(ContainerInterface): mixed $factory
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public Scope $scope,
        public \Closure $factory,
        public array $tags = [],
        public bool $lazy = false,
    ) {}
}
