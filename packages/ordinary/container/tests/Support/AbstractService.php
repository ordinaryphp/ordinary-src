<?php

declare(strict_types=1);

namespace Ordinary\Container\Tests\Support;

/** Abstract class used to verify that autowire() rejects non-instantiable classes. */
abstract class AbstractService
{
    abstract public function work(): string;
}
