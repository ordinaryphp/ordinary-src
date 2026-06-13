<?php

declare(strict_types=1);

namespace Ordinary\Container\Exception;

/**
 * Thrown when a cycle is detected in the service dependency graph during resolution.
 *
 * The chain lists every service id in the cycle in resolution order, with the last
 * entry being the duplicate that closed the loop.
 */
final class CircularDependencyException extends ContainerException
{
    /**
     * @param list<string> $chain Service ids in resolution order, last entry closes the cycle.
     */
    public function __construct(
        private readonly array $chain,
    ) {
        parent::__construct(\sprintf(
            'Circular dependency detected: %s',
            \implode(' → ', $this->chain),
        ));
    }

    /** @return list<string> */
    public function chain(): array
    {
        return $this->chain;
    }
}
