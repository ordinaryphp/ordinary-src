<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/**
 * Non-final concrete service used to verify PHP 8.4 lazy proxy behaviour.
 *
 * Accessing value() reads a property, which triggers lazy-proxy initialization.
 * A property-less method would run on the proxy itself without ever calling the factory.
 */
class LazyService
{
    private string $content = 'lazy';

    public function value(): string
    {
        return $this->content;
    }
}
