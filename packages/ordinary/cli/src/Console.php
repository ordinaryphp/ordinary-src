<?php

declare(strict_types=1);

namespace Ordinary\Cli;

use Ordinary\Cli\Io\InputInterface;
use Ordinary\Cli\Io\OutputInterface;
use Ordinary\Cli\Io\StreamInput;
use Ordinary\Cli\Io\StreamOutput;

/**
 * Holds the three standard I/O streams for a command handler.
 *
 * Inject via the handler closure signature: \Closure(ParsedInputInterface, Console): int.
 * In tests, construct with BufferedInput and BufferedOutput instances.
 */
final readonly class Console
{
    public function __construct(
        public InputInterface $input,
        public OutputInterface $output,
        public OutputInterface $error,
    ) {}

    /**
     * Construct from the live PHP stream constants.
     *
     * Use this only in production entry points (bin/ scripts).
     * In tests, pass BufferedInput and BufferedOutput directly to the constructor.
     */
    public static function fromStreams(): self
    {
        return new self(
            input: new StreamInput(\STDIN),
            output: new StreamOutput(\STDOUT),
            error: new StreamOutput(\STDERR),
        );
    }
}
