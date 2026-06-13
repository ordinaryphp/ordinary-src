<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Second implementation of ServiceInterface. */
final class ConcreteB implements ServiceInterface
{
    public function label(): string
    {
        return 'B';
    }
}
