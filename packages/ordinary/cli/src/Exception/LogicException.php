<?php

declare(strict_types=1);

namespace Ordinary\Cli\Exception;

/**
 * Thrown during compile() for programmer wiring errors: missing handler on a leaf
 * command, a command declaring both subcommands and positional arguments, or duplicate
 * short option names at the same command level.
 */
class LogicException extends \LogicException implements ExceptionInterface {}
