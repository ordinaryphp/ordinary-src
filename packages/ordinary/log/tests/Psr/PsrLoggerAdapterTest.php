<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Psr;

use Ordinary\Log\LoggerInterface;
use Ordinary\Log\LogItemInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Psr\PsrLoggerAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel as PsrLogLevel;

#[CoversClass(PsrLoggerAdapter::class)]
final class PsrLoggerAdapterTest extends TestCase
{
    #[Test]
    public function it_translates_psr_exception_key_to_reserved_key(): void
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
        $this->assertArrayHasKey(LogItemInterface::RESERVED_EXCEPTION, $capturedContext);
        $this->assertSame($exception, $capturedContext[LogItemInterface::RESERVED_EXCEPTION]);
        $this->assertArrayNotHasKey('exception', $capturedContext);
    }

    #[Test]
    public function it_does_not_translate_non_throwable_exception_values(): void
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
        $this->assertArrayNotHasKey(LogItemInterface::RESERVED_EXCEPTION, $capturedContext);
    }

    #[Test]
    public function exception_translation_applies_to_all_named_methods(): void
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

            new PsrLoggerAdapter($inner)->{$method}('test', ['exception' => $exception]);

            $this->assertArrayHasKey(
                LogItemInterface::RESERVED_EXCEPTION,
                $capturedContext ?? [],
                \sprintf('Method %s() did not translate the exception key', $method),
            );
        }
    }

    #[Test]
    public function log_maps_psr_level_to_internal_level(): void
    {
        $capturedItem = null;

        $inner = $this->createStub(LoggerInterface::class);
        $inner->method('log')->willReturnCallback(
            function (LogItemInterface $item) use (&$capturedItem): void {
                $capturedItem = $item;
            },
        );

        new PsrLoggerAdapter($inner)->log(PsrLogLevel::WARNING, 'test');

        $this->assertInstanceOf(LogItemInterface::class, $capturedItem);
        $this->assertSame(LogLevel::Warning, $capturedItem->level);
    }

    #[Test]
    public function log_translates_exception_key_via_log_method(): void
    {
        $exception = new \RuntimeException('fail');
        $capturedItem = null;

        $inner = $this->createStub(LoggerInterface::class);
        $inner->method('log')->willReturnCallback(
            function (LogItemInterface $item) use (&$capturedItem): void {
                $capturedItem = $item;
            },
        );

        new PsrLoggerAdapter($inner)->log(PsrLogLevel::ERROR, 'test', ['exception' => $exception]);

        $this->assertInstanceOf(LogItemInterface::class, $capturedItem);
        $this->assertArrayHasKey(LogItemInterface::RESERVED_EXCEPTION, $capturedItem->context);
        $this->assertArrayNotHasKey('exception', $capturedItem->context);
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
