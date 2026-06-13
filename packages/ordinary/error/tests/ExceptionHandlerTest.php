<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests;

use Ordinary\Error\ExceptionHandler;
use Ordinary\Error\FailureHandler\NoOpFailureHandler;
use Ordinary\Error\HandlerFailureHandlerInterface;
use Ordinary\Error\SynchronousHandlerInterface;
use Ordinary\Error\ThrowableHandlerInterface;
use Ordinary\Error\ThrowableMatcherInterface;
use PHPUnit\Framework\TestCase;

final class ExceptionHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        \restore_exception_handler();
    }

    public function testInvokeCallsHandlerWithThrowable(): void
    {
        $spy = new class implements ThrowableHandlerInterface {
            public ?\Throwable $received = null;

            public function handle(\Throwable $throwable): void
            {
                $this->received = $throwable;
            }
        };

        $handler = new ExceptionHandler();
        $handler->add($spy);

        $throwable = new \RuntimeException('uncaught');
        $handler->__invoke($throwable);

        $this->assertSame($throwable, $spy->received);
    }

    public function testInvokeSkipsHandlerWhenMatcherReturnsFalse(): void
    {
        $spy = new class implements ThrowableHandlerInterface {
            public bool $called = false;

            public function handle(\Throwable $throwable): void
            {
                $this->called = true;
            }
        };

        $handler = new ExceptionHandler();

        $neverMatcher = new class implements ThrowableMatcherInterface {
            public function matches(\Throwable $throwable): bool
            {
                return false;
            }
        };

        $handler->add($spy, $neverMatcher);

        $handler->__invoke(new \RuntimeException('test'));

        $this->assertFalse($spy->called);
    }

    public function testInvokeCatchesHandlerExceptionAndForwardsToOnFailure(): void
    {
        $onFailureSpy = new class implements HandlerFailureHandlerInterface {
            public bool $failureHandled = false;

            public function handleFailure(
                ThrowableHandlerInterface $handler,
                \Throwable $failure,
                \Throwable $original,
            ): void {
                $this->failureHandled = true;
            }
        };

        $handler = new ExceptionHandler(onFailure: $onFailureSpy);
        $handler->add(new class implements ThrowableHandlerInterface {
            public function handle(\Throwable $throwable): void
            {
                throw new \RuntimeException('handler exploded');
            }
        });

        $handler->__invoke(new \LogicException('original'));

        $this->assertTrue($onFailureSpy->failureHandled);
    }

    public function testInvokeContinuesWithRemainingHandlersAfterFailure(): void
    {
        $spy = new class implements ThrowableHandlerInterface {
            public bool $called = false;

            public function handle(\Throwable $throwable): void
            {
                $this->called = true;
            }
        };

        $handler = new ExceptionHandler(onFailure: new NoOpFailureHandler());

        $handler->add(new class implements ThrowableHandlerInterface {
            public function handle(\Throwable $throwable): void
            {
                throw new \RuntimeException('first failed');
            }
        });

        $handler->add($spy);

        $handler->__invoke(new \LogicException('original'));

        $this->assertTrue($spy->called, 'Second handler should still run after first fails');
    }

    public function testInvokeQueuesNonSynchronousHandlerThroughDispatcher(): void
    {
        /** @var list<\Closure(): void> $queue */
        $queue = [];
        $dispatcher = static function (\Closure $op) use (&$queue): void {
            $queue[] = $op;
        };

        $spy = new class implements ThrowableHandlerInterface {
            public bool $called = false;

            public function handle(\Throwable $throwable): void
            {
                $this->called = true;
            }
        };

        $handler = new ExceptionHandler(dispatcher: $dispatcher);
        $handler->add($spy);

        $handler->__invoke(new \RuntimeException('test'));

        $this->assertFalse($spy->called);
        $this->assertCount(1, $queue);

        ($queue[0])();

        $this->assertTrue($spy->called);
    }

    public function testAsyncHandlerFailureIsSentToOnFailure(): void
    {
        $onFailureSpy = new class implements HandlerFailureHandlerInterface {
            public bool $failureHandled = false;

            public function handleFailure(
                ThrowableHandlerInterface $handler,
                \Throwable $failure,
                \Throwable $original,
            ): void {
                $this->failureHandled = true;
            }
        };

        /** @var list<\Closure(): void> $queue */
        $queue = [];
        $dispatcher = static function (\Closure $op) use (&$queue): void {
            $queue[] = $op;
        };

        $handler = new ExceptionHandler(dispatcher: $dispatcher, onFailure: $onFailureSpy);
        $handler->add(new class implements ThrowableHandlerInterface {
            public function handle(\Throwable $throwable): void
            {
                throw new \RuntimeException('async handler failed');
            }
        });

        $handler->__invoke(new \LogicException('original'));
        ($queue[0])();

        $this->assertTrue($onFailureSpy->failureHandled);
    }

    public function testInvokeRunsSynchronousHandlerImmediatelyEvenWithDispatcher(): void
    {
        $queue = [];
        $dispatcher = static function (\Closure $op) use (&$queue): void {
            $queue[] = $op;
        };

        $spy = new class implements SynchronousHandlerInterface {
            public bool $called = false;

            public function handle(\Throwable $throwable): void
            {
                $this->called = true;
            }
        };

        $handler = new ExceptionHandler(dispatcher: $dispatcher);
        $handler->add($spy);

        $handler->__invoke(new \RuntimeException('test'));

        $this->assertTrue($spy->called, 'Synchronous handler must run immediately');
        $this->assertCount(0, $queue);
    }

    public function testAddReturnsFluentSelf(): void
    {
        $handler = new ExceptionHandler();
        $result = $handler->add(new class implements ThrowableHandlerInterface {
            public function handle(\Throwable $throwable): void {}
        });

        $this->assertSame($handler, $result);
    }

    public function testRegisterReturnsNewHandlerWhenNoneRegistered(): void
    {
        $handler = new ExceptionHandler();
        $effective = ExceptionHandler::register($handler);

        $this->assertSame($handler, $effective);

        \restore_exception_handler();
    }

    public function testRegisterAmendsExistingHandlerOfSameTypeAndReturnsIt(): void
    {
        $first = new ExceptionHandler();
        ExceptionHandler::register($first);

        $spy = new class implements ThrowableHandlerInterface {
            public bool $called = false;

            public function handle(\Throwable $throwable): void
            {
                $this->called = true;
            }
        };

        $second = new ExceptionHandler()->add($spy);
        $effective = ExceptionHandler::register($second);

        $this->assertSame($first, $effective);

        $effective->__invoke(new \RuntimeException('test'));

        $this->assertTrue($spy->called, 'Spy from second handler should have been added to first');

        \restore_exception_handler();
    }

    public function testRegisterCreatesDefaultInstanceWhenNullPassed(): void
    {
        $effective = ExceptionHandler::register();

        $this->assertInstanceOf(ExceptionHandler::class, $effective);

        \restore_exception_handler();
    }

    public function testRegisterWithChainCurrentWrapsExistingCallable(): void
    {
        $previousCalled = false;
        $previous = static function (\Throwable $e) use (&$previousCalled): void {
            $previousCalled = true;
        };
        \set_exception_handler($previous);

        $handler = new ExceptionHandler(onFailure: new NoOpFailureHandler());
        ExceptionHandler::register($handler, chainCurrent: true);

        $handler->__invoke(new \RuntimeException('test'));

        $this->assertTrue($previousCalled, 'Chained previous handler should have been called');

        \restore_exception_handler(); // removes $handler registered by ExceptionHandler::register()
        \restore_exception_handler(); // removes $previous registered by set_exception_handler() above
    }
}
