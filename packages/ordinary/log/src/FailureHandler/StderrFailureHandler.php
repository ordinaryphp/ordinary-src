<?php

declare(strict_types=1);

namespace Ordinary\Log\FailureHandler;

use Ordinary\Log\LogFailureExceptionInterface;
use Ordinary\Log\LogFailureHandlerInterface;

/**
 * Writes log dispatch failures to STDERR (or any writable stream).
 *
 * Accepts an optional stream resource so that the output destination can be
 * overridden in tests or redirected to a file.
 */
final class StderrFailureHandler implements LogFailureHandlerInterface
{
    /** @var resource */
    private $stream;

    /** @param resource $stream Defaults to STDERR. */
    public function __construct($stream = \STDERR)
    {
        $this->stream = $stream;
    }

    public function handleLogFailure(LogFailureExceptionInterface $e): void
    {
        \fwrite($this->stream, $e->getMessage() . \PHP_EOL);
    }
}
