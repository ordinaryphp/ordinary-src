<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use DateTimeImmutable;
use Ordinary\Log\CallableProcessor;
use Ordinary\Log\FailureHandler\NoOpFailureHandler;
use Ordinary\Log\FlushableInterface;
use Ordinary\Log\ImmutableLogEntryInterface;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogFailureExceptionInterface;
use Ordinary\Log\LogFailureHandlerInterface;
use Ordinary\Log\Logger;
use Ordinary\Log\LogLevel;
use Ordinary\Log\LogProcessorInterface;
use Ordinary\Log\Matcher\IsLevelOrHigher;
use Ordinary\Log\SynchronousDriverInterface;
use Ordinary\Log\Tests\Support\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Logger::class)]
final class LoggerTest extends TestCase
{
    #[Test]
    public function it_fans_out_to_all_drivers_in_default_group(): void
    {
        $driverA = $this->createMock(LogDriverInterface::class);
        $driverA->expects($this->once())->method('handleLog');

        $driverB = $this->createMock(LogDriverInterface::class);
        $driverB->expects($this->once())->method('handleLog');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($driverA);
        $logger->add($driverB);
        $logger->info('test');
    }

    #[Test]
    public function named_methods_produce_correct_levels(): void
    {
        $items = [];
        $driver = $this->createStub(LogDriverInterface::class);
        $driver->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$items): void {
            $items[] = $item->level;
        });

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($driver);
        $logger->debug('d');
        $logger->info('i');
        $logger->notice('n');
        $logger->warning('w');
        $logger->error('e');
        $logger->critical('c');
        $logger->alert('a');
        $logger->emergency('em');

        $this->assertSame([
            LogLevel::Debug,
            LogLevel::Info,
            LogLevel::Notice,
            LogLevel::Warning,
            LogLevel::Error,
            LogLevel::Critical,
            LogLevel::Alert,
            LogLevel::Emergency,
        ], $items);
    }

    #[Test]
    public function it_filters_via_driver_matcher(): void
    {
        $driver = $this->createMock(LogDriverInterface::class);
        $driver->expects($this->never())->method('handleLog');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($driver, matcher: new IsLevelOrHigher(LogLevel::Error));
        $logger->info('below threshold');
    }

    #[Test]
    public function it_filters_via_group_matcher(): void
    {
        $driver = $this->createMock(LogDriverInterface::class);
        $driver->expects($this->never())->method('handleLog');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->addGroup('errors-only', matcher: new IsLevelOrHigher(LogLevel::Error));
        $logger->add($driver, group: 'errors-only');
        $logger->info('below threshold');
    }

    #[Test]
    public function group_matcher_and_driver_matcher_both_must_pass(): void
    {
        $driver = $this->createMock(LogDriverInterface::class);
        $driver->expects($this->never())->method('handleLog');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        // group passes warning+, driver requires error+ — info passes neither
        $logger->addGroup('warnings', matcher: new IsLevelOrHigher(LogLevel::Warning));
        $logger->add($driver, matcher: new IsLevelOrHigher(LogLevel::Error), group: 'warnings');
        $logger->warning('passes group but not driver');
    }

    #[Test]
    public function it_dispatches_to_multiple_groups(): void
    {
        $defaultDriver = $this->createMock(LogDriverInterface::class);
        $defaultDriver->expects($this->once())->method('handleLog');

        $errorDriver = $this->createMock(LogDriverInterface::class);
        $errorDriver->expects($this->once())->method('handleLog');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($defaultDriver);
        $logger->addGroup('errors', matcher: new IsLevelOrHigher(LogLevel::Error));
        $logger->add($errorDriver, group: 'errors');
        $logger->error('both groups should receive this');
    }

    #[Test]
    public function add_to_nonexistent_group_throws(): void
    {
        $logger = new Logger(onFailure: new NoOpFailureHandler());

        $this->expectException(\InvalidArgumentException::class);
        $logger->add($this->createStub(LogDriverInterface::class), group: 'nonexistent');
    }

    #[Test]
    public function addGroup_with_duplicate_id_throws(): void
    {
        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->addGroup('my-group');

        $this->expectException(\InvalidArgumentException::class);
        $logger->addGroup('my-group');
    }

    #[Test]
    public function removeGroup_removes_its_drivers(): void
    {
        $driver = $this->createMock(LogDriverInterface::class);
        $driver->expects($this->never())->method('handleLog');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->addGroup('temp');
        $logger->add($driver, group: 'temp');
        $logger->removeGroup('temp');
        $logger->info('test');
    }

    #[Test]
    public function removeGroup_default_throws(): void
    {
        $logger = new Logger(onFailure: new NoOpFailureHandler());

        $this->expectException(\InvalidArgumentException::class);
        $logger->removeGroup('default');
    }

    #[Test]
    public function removeGroup_nonexistent_throws(): void
    {
        $logger = new Logger(onFailure: new NoOpFailureHandler());

        $this->expectException(\InvalidArgumentException::class);
        $logger->removeGroup('nonexistent');
    }

    #[Test]
    public function it_defers_non_synchronous_drivers_via_dispatcher(): void
    {
        $callCount = 0;
        $driver = $this->createStub(LogDriverInterface::class);
        $driver->method('handleLog')->willReturnCallback(function () use (&$callCount): void {
            $callCount++;
        });

        /** @var list<\Closure(): void> $deferred */
        $deferred = [];
        $dispatcher = function (\Closure $fn) use (&$deferred): void {
            $deferred[] = $fn;
        };

        $logger = new Logger(dispatcher: $dispatcher, onFailure: new NoOpFailureHandler());
        $logger->add($driver);
        $logger->info('test');

        $this->assertSame(0, $callCount);
        $this->assertCount(1, $deferred);

        foreach ($deferred as $fn) {
            $fn();
        }

        // @phpstan-ignore method.impossibleType
        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function it_bypasses_dispatcher_for_synchronous_drivers(): void
    {
        $driver = $this->createMock(SynchronousDriverInterface::class);
        $driver->expects($this->once())->method('handleLog');

        $dispatcherCalled = false;
        $dispatcher = function (\Closure $fn) use (&$dispatcherCalled): void {
            $dispatcherCalled = true;
            $fn();
        };

        $logger = new Logger(dispatcher: $dispatcher, onFailure: new NoOpFailureHandler());
        $logger->add($driver);
        $logger->info('test');

        $this->assertFalse($dispatcherCalled);
    }

    #[Test]
    public function it_invokes_on_failure_when_driver_throws(): void
    {
        $exception = new \RuntimeException('boom');

        $driver = $this->createStub(LogDriverInterface::class);
        $driver->method('handleLog')->willThrowException($exception);

        $onFailure = $this->createMock(LogFailureHandlerInterface::class);
        $onFailure->expects($this->once())
            ->method('handleLogFailure')
            ->with($this->callback(function (LogFailureExceptionInterface $e) use ($driver): bool {
                $this->assertSame($driver, $e->failingDriver);
                $this->assertStringContainsString('boom', $e->getMessage());
                return true;
            }));

        $logger = new Logger(onFailure: $onFailure);
        $logger->add($driver);
        $logger->error('test');
    }

    #[Test]
    public function it_continues_to_next_driver_after_failure(): void
    {
        $failing = $this->createStub(LogDriverInterface::class);
        $failing->method('handleLog')->willThrowException(new \RuntimeException('boom'));

        $succeeding = $this->createMock(LogDriverInterface::class);
        $succeeding->expects($this->once())->method('handleLog');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($failing);
        $logger->add($succeeding);
        $logger->error('test');
    }

    #[Test]
    public function it_stamps_channel_on_every_log_item(): void
    {
        $capturedItem = null;

        $driver = $this->createStub(LogDriverInterface::class);
        $driver->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$capturedItem): void {
            $capturedItem = $item;
        });

        $logger = new Logger(channel: 'payment', onFailure: new NoOpFailureHandler());
        $logger->add($driver);
        $logger->error('charge failed');

        $this->assertInstanceOf(LogEntryInterface::class, $capturedItem);
        $this->assertSame('payment', $capturedItem->context[LogEntryInterface::RESERVED_CHANNEL]);
    }

    #[Test]
    public function it_does_not_stamp_channel_when_empty(): void
    {
        $capturedItem = null;

        $driver = $this->createStub(LogDriverInterface::class);
        $driver->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$capturedItem): void {
            $capturedItem = $item;
        });

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($driver);
        $logger->info('test');

        $this->assertInstanceOf(LogEntryInterface::class, $capturedItem);
        $this->assertArrayNotHasKey(LogEntryInterface::RESERVED_CHANNEL, $capturedItem->context);
    }

    #[Test]
    public function it_applies_processor_before_dispatch(): void
    {
        $capturedItem = null;

        $driver = $this->createStub(LogDriverInterface::class);
        $driver->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$capturedItem): void {
            $capturedItem = $item;
        });

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->addProcessor(new CallableProcessor(
            fn(ImmutableLogEntryInterface $item): ImmutableLogEntryInterface => $item->withContext(['enriched' => true]),
        ));
        $logger->add($driver);
        $logger->info('test');

        $this->assertInstanceOf(LogEntryInterface::class, $capturedItem);
        $this->assertTrue($capturedItem->context['enriched']);
    }

    #[Test]
    public function processors_run_in_registration_order(): void
    {
        $order = [];

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->addProcessor(new CallableProcessor(function (ImmutableLogEntryInterface $item) use (&$order): ImmutableLogEntryInterface {
            $order[] = 'first';
            return $item;
        }));
        $logger->addProcessor(new CallableProcessor(function (ImmutableLogEntryInterface $item) use (&$order): ImmutableLogEntryInterface {
            $order[] = 'second';
            return $item;
        }));
        $logger->add($this->createStub(LogDriverInterface::class));
        $logger->info('test');

        $this->assertSame(['first', 'second'], $order);
    }

    #[Test]
    public function processors_can_see_the_channel(): void
    {
        $channelSeenByProcessor = null;

        $logger = new Logger(channel: 'app', onFailure: new NoOpFailureHandler());
        $logger->addProcessor(new CallableProcessor(function (ImmutableLogEntryInterface $item) use (&$channelSeenByProcessor): ImmutableLogEntryInterface {
            $channelSeenByProcessor = $item->context[LogEntryInterface::RESERVED_CHANNEL] ?? null;
            return $item;
        }));
        $logger->add($this->createStub(LogDriverInterface::class));
        $logger->info('test');

        $this->assertSame('app', $channelSeenByProcessor);
    }

    #[Test]
    public function it_accepts_typed_processor_interface(): void
    {
        $processor = $this->createMock(LogProcessorInterface::class);
        $processor->expects($this->once())
            ->method('process')
            ->willReturnArgument(0);

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->addProcessor($processor);
        $logger->add($this->createStub(LogDriverInterface::class));
        $logger->info('test');
    }

    #[Test]
    public function flush_calls_flush_on_all_flushable_drivers(): void
    {
        $flushable = $this->createMock(FlushableLogDriverDouble::class);
        $flushable->expects($this->once())->method('flush');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($flushable);
        $logger->flush();
    }

    #[Test]
    public function flush_skips_non_flushable_drivers(): void
    {
        $driver = $this->createMock(LogDriverInterface::class);
        $driver->expects($this->never())->method('handleLog');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($driver);
        $logger->flush();
    }

    #[Test]
    public function flush_propagates_to_drivers_in_all_groups(): void
    {
        $flushableA = $this->createMock(FlushableLogDriverDouble::class);
        $flushableA->expects($this->once())->method('flush');

        $flushableB = $this->createMock(FlushableLogDriverDouble::class);
        $flushableB->expects($this->once())->method('flush');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($flushableA);
        $logger->addGroup('secondary');
        $logger->add($flushableB, group: 'secondary');
        $logger->flush();
    }

    #[Test]
    public function flush_attempts_all_drivers_before_rethrowing(): void
    {
        $failingA = $this->createStub(FlushableLogDriverDouble::class);
        $failingA->method('flush')->willThrowException(new \RuntimeException('first'));

        $failingB = $this->createMock(FlushableLogDriverDouble::class);
        $failingB->expects($this->once())->method('flush');

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($failingA);
        $logger->add($failingB);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('first');
        $logger->flush();
    }

    #[Test]
    public function flush_is_noop_when_no_flushable_drivers_registered(): void
    {
        $this->expectNotToPerformAssertions();
        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->flush();
    }

    #[Test]
    public function it_uses_injected_clock_for_log_item_timestamps(): void
    {
        $fixedTime = new DateTimeImmutable('2024-06-15T10:30:00Z');
        $clock = new MutableClock($fixedTime);

        $capturedItem = null;
        $driver = $this->createStub(LogDriverInterface::class);
        $driver->method('handleLog')->willReturnCallback(function (LogEntryInterface $item) use (&$capturedItem): void {
            $capturedItem = $item;
        });

        $logger = new Logger(onFailure: new NoOpFailureHandler(), clock: $clock);
        $logger->add($driver);
        $logger->error('timed event');

        $this->assertInstanceOf(LogEntryInterface::class, $capturedItem);
        $this->assertSame($fixedTime, $capturedItem->dateTime);
    }

    #[Test]
    public function to_psr_delegates_to_the_underlying_logger(): void
    {
        $received = [];
        $driver = $this->createMock(LogDriverInterface::class);
        $driver->expects($this->once())
            ->method('handleLog')
            ->willReturnCallback(static function (LogEntryInterface $item) use (&$received): void {
                $received[] = $item;
            });

        $logger = new Logger(onFailure: new NoOpFailureHandler());
        $logger->add($driver);

        $logger->toPsr()->info('psr message');

        $this->assertCount(1, $received);
        $this->assertSame('psr message', $received[0]->message);
        $this->assertSame(LogLevel::Info, $received[0]->level);
    }

    #[Test]
    public function it_uses_error_log_failure_handler_by_default(): void
    {
        $driver = $this->createStub(LogDriverInterface::class);
        $driver->method('handleLog')->willThrowException(new \RuntimeException('fail'));

        $tmpFile = \tempnam(\sys_get_temp_dir(), 'phpunit_log_');
        $this->assertIsString($tmpFile);
        $previousErrorLog = \ini_set('error_log', $tmpFile);

        try {
            $logger = new Logger();
            $logger->add($driver);
            $logger->error('something bad');

            $contents = \file_get_contents($tmpFile);
            $this->assertIsString($contents);
            $this->assertStringContainsString('fail', $contents);
        } finally {
            \ini_set('error_log', $previousErrorLog !== false ? $previousErrorLog : '');
            \unlink($tmpFile);
        }
    }
}

/**
 * Test double: a LogDriverInterface that is also FlushableInterface.
 */
abstract class FlushableLogDriverDouble implements LogDriverInterface, FlushableInterface {}
