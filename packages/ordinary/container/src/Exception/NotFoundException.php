<?php

declare(strict_types=1);

namespace Ordinary\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/** Thrown when get() is called for an id that has no registered definition or alias. */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface {}
