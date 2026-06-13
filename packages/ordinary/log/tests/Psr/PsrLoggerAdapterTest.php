<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Psr;

use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LoggerInterface;
use Ordinary\Log\Psr\PsrLoggerAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel as PsrLogLevel;

#[CoversClass(PsrLoggerAdapter::class)]
final class PsrLoggerAdapterTest extends TestCase
{
    #[Test]
    public function it_passes_psr3_exception_key_directly_to_inner_logger(): void
    {
        $exception = new \RuntimeException('something went wrong');
        $capturedContext = null;

        $inner = $this->createStub(LoggerInterface::class);
        $inner->method('error')->willReturnCallback(
            function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            },
        );

        new PsrLoggerAdapter($inner)->error('test', ['exception' => $exception]);

        $this->assertIsArray($capturedContext);
        $this->assertArrayHasKey(LogEntryInterface::RESERVED_EXCEPTION, $capturedContext);
        $this->assertSame($exception, $capturedContext[LogEntryInterface::RESERVED_EXCEPTION]);
    }

    #[Test]
    public function it_passes_non_throwable_exception_value_through_unchanged(): void
    {
        $capturedContext = null;

        $inner = $this->createStub(LoggerInterface::class);
        $inner->method('error')->willReturnCallback(
            function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            },
        );

        new PsrLoggerAdapter($inner)->error('test', ['exception' => 'just a string']);

        $this->assertIsArray($capturedContext);
        $this->assertArrayHasKey('exception', $capturedContext);
        $this->assertSame('just a string', $capturedContext['exception']);
    }

    #[Test]
    public function exception_context_passes_through_all_named_methods(): void
    {
        $exception = new \RuntimeException('fail');
        $methods = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

        foreach ($methods as $method) {
            $capturedContext = null;

            $inner = $this->createStub(LoggerInterface::class);
            $inner->method($method)->willReturnCallback(
                function (string $message, array $context) use (&$capturedContext): void {
                    $capturedContext = $context;
                },
            );

            // @phpstan-ignore method.dynamicName
            new PsrLoggerAdapter($inner)->{$method}('test', ['exception' => $exception]);

            $this->assertArrayHasKey(
                LogEntryInterface::RESERVED_EXCEPTION,
                $capturedContext ?? [],
                \sprintf('Method %s() did not pass the exception key', $method),
            );
        }
    }

    /** @return \Iterator<string, array{non-empty-string, non-empty-string}> */
    public static function psrLevelToMethodProvider(): \Iterator
    {
        yield 'emergency' => [PsrLogLevel::EMERGENCY, 'emergency'];
        yield 'alert' => [PsrLogLevel::ALERT,     'alert'];
        yield 'critical' => [PsrLogLevel::CRITICAL,  'critical'];
        yield 'error' => [PsrLogLevel::ERROR,      'error'];
        yield 'warning' => [PsrLogLevel::WARNING,    'warning'];
        yield 'notice' => [PsrLogLevel::NOTICE,     'notice'];
        yield 'info' => [PsrLogLevel::INFO,       'info'];
        yield 'debug' => [PsrLogLevel::DEBUG,      'debug'];
    }

    /**
     * @param non-empty-string $psrLevel
     * @param non-empty-string $method
     */
    #[Test]
    #[DataProvider('psrLevelToMethodProvider')]
    public function log_dispatches_to_named_method_for_level(string $psrLevel, string $method): void
    {
        $inner = $this->createMock(LoggerInterface::class);
        $inner->expects($this->once())->method($method)->with('test msg', []);

        new PsrLoggerAdapter($inner)->log($psrLevel, 'test msg');
    }

    #[Test]
    public function log_passes_exception_context_via_log_method(): void
    {
        $exception = new \RuntimeException('fail');
        $capturedContext = null;

        $inner = $this->createStub(LoggerInterface::class);
        $inner->method('error')->willReturnCallback(
            function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            },
        );

        new PsrLoggerAdapter($inner)->log(PsrLogLevel::ERROR, 'test', ['exception' => $exception]);

        $this->assertIsArray($capturedContext);
        $this->assertArrayHasKey(LogEntryInterface::RESERVED_EXCEPTION, $capturedContext);
        $this->assertSame($exception, $capturedContext[LogEntryInterface::RESERVED_EXCEPTION]);
    }

    #[Test]
    public function log_throws_for_invalid_level_string(): void
    {
        $inner = $this->createStub(LoggerInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown PSR log level');
        new PsrLoggerAdapter($inner)->log('not-a-level', 'test');
    }

    #[Test]
    public function log_throws_for_non_string_level(): void
    {
        $inner = $this->createStub(LoggerInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Log level must be a string');
        new PsrLoggerAdapter($inner)->log(42, 'test');
    }
}
