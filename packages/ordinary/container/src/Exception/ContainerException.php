<?php

declare(strict_types=1);

namespace Ordinary\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/** Base container exception. Implements PSR-11 ContainerExceptionInterface. */
class ContainerException extends RuntimeException implements ContainerExceptionInterface {}
