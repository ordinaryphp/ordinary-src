<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** First tagged service. Used to verify tag-based collection. */
final class TaggedServiceA
{
    public function name(): string
    {
        return 'tagged-a';
    }
}
