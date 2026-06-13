<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Interface for alias and contextual binding test scenarios. */
interface ServiceInterface
{
    public function label(): string;
}
