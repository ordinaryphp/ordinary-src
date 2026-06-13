<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** First implementation of ServiceInterface. */
final class ConcreteA implements ServiceInterface
{
    public function label(): string
    {
        return 'A';
    }
}
