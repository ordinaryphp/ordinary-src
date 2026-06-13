<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

/** Thrown during builder configuration for invalid option or argument declarations. */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface {}
