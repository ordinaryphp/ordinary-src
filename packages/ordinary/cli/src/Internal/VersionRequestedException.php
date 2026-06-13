<?php

declare(strict_types=1);

namespace Ordinary\Cli\Internal;

/**
 * @internal Thrown by the parser when --version or -V is encountered.
 *           Caught by CommandDispatcher::run() to print the version string and exit 0.
 */
final class VersionRequestedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Version requested');
    }
}
