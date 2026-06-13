<?php

declare(strict_types=1);

namespace Ordinary\Cli\Tests\Io;

use Ordinary\Cli\Io\BufferedOutput;
use PHPUnit\Framework\TestCase;

final class BufferedOutputTest extends TestCase
{
    public function testWriteAppendsContentWithoutNewline(): void
    {
        $out = new BufferedOutput();
        $out->write('hello');
        $out->write(' world');
        $this->assertSame('hello world', $out->content());
    }

    public function testWritelnAppendsContentWithNewline(): void
    {
        $out = new BufferedOutput();
        $out->writeln('line one');
        $out->writeln('line two');
        $this->assertSame("line one\nline two\n", $out->content());
    }

    public function testWritelnWithNoArgumentWritesBlankLine(): void
    {
        $out = new BufferedOutput();
        $out->writeln();
        $this->assertSame("\n", $out->content());
    }

    public function testClearResetsBuffer(): void
    {
        $out = new BufferedOutput();
        $out->writeln('some output');
        $out->clear();
        $this->assertSame('', $out->content());
    }
}
