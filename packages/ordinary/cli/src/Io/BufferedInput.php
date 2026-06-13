<?php

declare(strict_types=1);

namespace Ordinary\Cli\Io;

/**
 * In-memory InputInterface for use in tests.
 *
 * isInteractive is always false — buffered input is never a TTY.
 */
final class BufferedInput implements InputInterface
{
    public bool $isInteractive { get => false; }

    private int $position = 0;

    public function __construct(private readonly string $content = '') {}

    public function read(int $length = 8192): string
    {
        if ($this->position >= \strlen($this->content)) {
            return '';
        }

        $chunk = \substr($this->content, $this->position, $length);
        $this->position += \strlen($chunk);
        return $chunk;
    }

    public function readLine(): ?string
    {
        if ($this->position >= \strlen($this->content)) {
            return null;
        }

        $newline = \strpos($this->content, "\n", $this->position);

        if ($newline === false) {
            $line = \substr($this->content, $this->position);
            $this->position = \strlen($this->content);
            return \rtrim($line, "\r");
        }

        $line = \substr($this->content, $this->position, $newline - $this->position);
        $this->position = $newline + 1;
        return \rtrim($line, "\r");
    }
}
