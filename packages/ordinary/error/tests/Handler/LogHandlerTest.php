<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests\Handler;

use Ordinary\Error\Handler\LogHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

final class LogHandlerTest extends TestCase
{
    private function makeLogger(): CapturingLogger
    {
        return new CapturingLogger();
    }

    public function testHandleLogsMessageAndPassesExceptionAsContext(): void
    {
        $logger = $this->makeLogger();
        $handler = new LogHandler($logger);

        $throwable = new \RuntimeException('something failed');
        $handler->handle($throwable);

        $this->assertCount(1, $logger->records);
        $this->assertSame('something failed', $logger->records[0]['message']);
        $this->assertSame($throwable, $logger->records[0]['context']['exception']);
    }

    public function testHandleUsesDefaultLevelForNonErrorException(): void
    {
        $logger = $this->makeLogger();
        $handler = new LogHandler($logger);

        $handler->handle(new \RuntimeException('test'));

        $this->assertSame(LogLevel::ERROR, $logger->records[0]['level']);
    }

    public function testHandleUsesCustomDefaultLevelForNonErrorException(): void
    {
        $logger = $this->makeLogger();
        $handler = new LogHandler($logger, defaultLevel: LogLevel::CRITICAL);

        $handler->handle(new \RuntimeException('test'));

        $this->assertSame(LogLevel::CRITICAL, $logger->records[0]['level']);
    }

    /** @return \Iterator<string, array{int, string}> */
    public static function severityToLevelProvider(): \Iterator
    {
        yield 'E_ERROR maps to critical' => [E_ERROR, LogLevel::CRITICAL];
        yield 'E_USER_ERROR maps to critical' => [E_USER_ERROR, LogLevel::CRITICAL];
        yield 'E_CORE_ERROR maps to critical' => [E_CORE_ERROR, LogLevel::CRITICAL];
        yield 'E_COMPILE_ERROR maps to critical' => [E_COMPILE_ERROR, LogLevel::CRITICAL];
        yield 'E_RECOVERABLE_ERROR maps to error' => [E_RECOVERABLE_ERROR, LogLevel::ERROR];
        yield 'E_WARNING maps to warning' => [E_WARNING, LogLevel::WARNING];
        yield 'E_USER_WARNING maps to warning' => [E_USER_WARNING, LogLevel::WARNING];
        yield 'E_CORE_WARNING maps to warning' => [E_CORE_WARNING, LogLevel::WARNING];
        yield 'E_COMPILE_WARNING maps to warning' => [E_COMPILE_WARNING, LogLevel::WARNING];
        yield 'E_NOTICE maps to notice' => [E_NOTICE, LogLevel::NOTICE];
        yield 'E_USER_NOTICE maps to notice' => [E_USER_NOTICE, LogLevel::NOTICE];
        yield 'E_DEPRECATED maps to info' => [E_DEPRECATED, LogLevel::INFO];
        yield 'E_USER_DEPRECATED maps to info' => [E_USER_DEPRECATED, LogLevel::INFO];
    }

    #[DataProvider('severityToLevelProvider')]
    public function testHandleMapsErrorSeverityToPsrLevel(int $severity, string $expectedLevel): void
    {
        $logger = $this->makeLogger();
        $handler = new LogHandler($logger);

        $handler->handle(new \ErrorException('test', 0, $severity));

        $this->assertSame($expectedLevel, $logger->records[0]['level']);
    }

    public function testHandleUsesDefaultLevelForUnknownErrorSeverity(): void
    {
        $logger = $this->makeLogger();
        $handler = new LogHandler($logger, defaultLevel: LogLevel::WARNING);

        // Severity 0 doesn't match any known constant
        $handler->handle(new \ErrorException('test', 0, 0));

        $this->assertSame(LogLevel::WARNING, $logger->records[0]['level']);
    }
}

final class CapturingLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    /** @param array<mixed> $context */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
