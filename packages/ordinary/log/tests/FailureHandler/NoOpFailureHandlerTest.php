<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\FailureHandler;

use DateTimeImmutable;
use Ordinary\Log\FailureHandler\NoOpFailureHandler;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogFailureException;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoOpFailureHandler::class)]
final class NoOpFailureHandlerTest extends TestCase
{
    #[Test]
    public function it_silently_discards_failures(): void
    {
        $handler = new NoOpFailureHandler();
        $e = new LogFailureException(
            new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable()),
            new \RuntimeException('fail'),
        );

        $handler->handleLogFailure($e);
        $this->addToAssertionCount(1);
    }
}
