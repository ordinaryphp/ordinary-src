<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\FailureHandler;

use DateTimeImmutable;
use Ordinary\Log\FailureHandler\StderrFailureHandler;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogFailureException;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StderrFailureHandler::class)]
final class StderrFailureHandlerTest extends TestCase
{
    #[Test]
    public function it_writes_the_failure_message_to_the_stream(): void
    {
        $stream = \fopen('php://memory', 'w+');

        if ($stream === false) {
            $this->fail('Could not open in-memory stream');
        }

        $logItem = new LogEntry(LogLevel::Error, 'something failed', new DateTimeImmutable());
        $e = new LogFailureException($logItem, new \RuntimeException('network timeout'));

        new StderrFailureHandler($stream)->handleLogFailure($e);

        \rewind($stream);
        $output = \stream_get_contents($stream);
        \fclose($stream);

        $this->assertIsString($output);
        $this->assertStringContainsString('network timeout', $output);
        $this->assertStringEndsWith(\PHP_EOL, $output);
    }
}
