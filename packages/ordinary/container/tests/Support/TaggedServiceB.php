<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Second tagged service. Used to verify tag-based collection. */
final class TaggedServiceB
{
    public function name(): string
    {
        return 'tagged-b';
    }
}
