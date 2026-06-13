<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests;

use Ordinary\Error\ErrorException;
use Ordinary\Error\ErrorHandler;
use Ordinary\Error\FailureHandler\NoOpFailureHandler;
use Ordinary\Error\HandlerFailureHandlerInterface;
use Ordinary\Error\SynchronousHandlerInterface;
use Ordinary\Error\ThrowableHandlerInterface;
use Ordinary\Error\ThrowableMatcherInterface;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerTest extends TestCase
{
    private int $prevErrorReporting;

    protected function setUp(): void
    {
        // PHPUnit reduces error_reporting() to only fatal levels so its own handler
        // can intercept warnings/notices. Restore E_ALL to simulate the production
        // environment in which ErrorHandler.__invoke operates.
        $this->prevErrorReporting = \error_reporting(\E_ALL);
    }

    protected function tearDown(): void
    {
        \error_reporting($this->prevErrorReporting);
    }

    public function testInvokeConvertsErrorToErrorExceptionAndCallsHandler(): void
    {
        $spy = new class implements ThrowableHandlerInterface {
            public ?\Throwable $received = null;

            public function handle(\Throwable $throwable): void
            {
                $this->received = $throwable;
            }
        };

        $handler = new ErrorHandler();
        $handler->add($spy);

        $result = $handler->__invoke(E_WARNING, 'Undefined variable', '/app/Foo.php', 10);

        $this->assertTrue($result);
        $this->assertInstanceOf(ErrorException::class, $spy->received);
        $this->assertSame(E_WARNING, $spy->received->getSeverity());
        $this->assertSame('Undefined variable', $spy->received->getMessage());
        $this->assertSame('/app/Foo.php', $spy->received->getFile());
        $this->assertSame(10, $spy->received->getLine());
    }

    public function testInvokeReturnsFalseForSuppressedErrors(): void
    {
        $spy = new class implements ThrowableHandlerInterface {
            public bool $called = false;

            public function handle(\Throwable $throwable): void
            {
                $this->called = true;
            }
        };

        $handler = new ErrorHandler();
        $handler->add($spy);

        $prev = \error_reporting(0);

        try {
            $result = $handler->__invoke(E_WARNING, 'suppressed', '', 0);
        } finally {
            \error_reporting($prev);
        }

        $this->assertFalse($result);
        $this->assertFalse($spy->called);
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

        $handler = new ErrorHandler();

        $neverMatcher = new class implements ThrowableMatcherInterface {
            public function matches(\Throwable $throwable): bool
            {
                return false;
            }
        };

        $handler->add($spy, $neverMatcher);

        $handler->__invoke(E_WARNING, 'test', '', 0);

        $this->assertFalse($spy->called);
    }

    public function testInvokeCallsHandlerWhenMatcherPasses(): void
    {
        $spy = new class implements ThrowableHandlerInterface {
            public bool $called = false;

            public function handle(\Throwable $throwable): void
            {
                $this->called = true;
            }
        };

        $handler = new ErrorHandler();

        $alwaysMatcher = new class implements ThrowableMatcherInterface {
            public function matches(\Throwable $throwable): bool
            {
                return true;
            }
        };

        $handler->add($spy, $alwaysMatcher);

        $handler->__invoke(E_WARNING, 'test', '', 0);

        $this->assertTrue($spy->called);
    }

    public function testInvokeCallsMultipleHandlersInOrder(): void
    {
        /** @var \ArrayObject<int, string> $collector */
        $collector = new \ArrayObject();
        $handler = new ErrorHandler();

        foreach (['first', 'second', 'third'] as $name) {
            $handler->add(
                new readonly class ($collector, $name) implements ThrowableHandlerInterface {
                    /** @param \ArrayObject<int, string> $collector */
                    public function __construct(
                        private \ArrayObject $collector,
                        private string $name,
                    ) {}

                    public function handle(\Throwable $throwable): void
                    {
                        $this->collector->append($this->name);
                    }
                },
            );
        }

        $handler->__invoke(E_NOTICE, 'test', '', 0);

        $this->assertSame(['first', 'second', 'third'], $collector->getArrayCopy());
    }

    public function testInvokePropagatesExceptionFromSynchronousHandler(): void
    {
        $handler = new ErrorHandler();
        $handler->add(new class implements SynchronousHandlerInterface {
            public function handle(\Throwable $throwable): void
            {
                throw new \LogicException('sync handler threw');
            }
        });

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('sync handler threw');

        $handler->__invoke(E_WARNING, 'test', '', 0);
    }

    public function testInvokePropagatesExceptionFromHandlerWithNoDispatcher(): void
    {
        $handler = new ErrorHandler();
        $handler->add(new class implements ThrowableHandlerInterface {
            public function handle(\Throwable $throwable): void
            {
                throw new \RuntimeException('handler threw');
            }
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handler threw');

        $handler->__invoke(E_WARNING, 'test', '', 0);
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

        $handler = new ErrorHandler(dispatcher: $dispatcher);
        $handler->add($spy);

        $handler->__invoke(E_WARNING, 'test', '', 0);

        $this->assertFalse($spy->called, 'Handler should not have been called synchronously');
        $this->assertCount(1, $queue);

        ($queue[0])();

        $this->assertTrue($spy->called, 'Handler should have been called after running queued operation');
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

        $handler = new ErrorHandler(dispatcher: $dispatcher);
        $handler->add($spy);

        $handler->__invoke(E_WARNING, 'test', '', 0);

        $this->assertTrue($spy->called, 'Synchronous handler must run immediately');
        $this->assertCount(0, $queue);
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

        $handler = new ErrorHandler(dispatcher: $dispatcher, onFailure: $onFailureSpy);
        $handler->add(new class implements ThrowableHandlerInterface {
            public function handle(\Throwable $throwable): void
            {
                throw new \RuntimeException('async handler failed');
            }
        });

        $handler->__invoke(E_WARNING, 'test', '', 0);
        ($queue[0])();

        $this->assertTrue($onFailureSpy->failureHandled);
    }

    public function testAddReturnsFluentSelf(): void
    {
        $handler = new ErrorHandler();
        $result = $handler->add(new class implements ThrowableHandlerInterface {
            public function handle(\Throwable $throwable): void {}
        });

        $this->assertSame($handler, $result);
    }

    public function testRegisterReturnsNewHandlerWhenNoneRegistered(): void
    {
        $handler = new ErrorHandler();
        $effective = ErrorHandler::register($handler);

        $this->assertSame($handler, $effective);

        \restore_error_handler();
    }

    public function testRegisterAmendsExistingHandlerOfSameTypeAndReturnsIt(): void
    {
        $first = new ErrorHandler();
        ErrorHandler::register($first);

        $spy = new class implements ThrowableHandlerInterface {
            public bool $called = false;

            public function handle(\Throwable $throwable): void
            {
                $this->called = true;
            }
        };

        $second = new ErrorHandler()->add($spy);
        $effective = ErrorHandler::register($second);

        $this->assertSame($first, $effective);

        $effective->__invoke(E_WARNING, 'test', '', 0);

        $this->assertTrue($spy->called, 'Spy from second handler should have been added to first');

        \restore_error_handler();
    }

    public function testRegisterCreatesDefaultInstanceWhenNullPassed(): void
    {
        $effective = ErrorHandler::register();

        $this->assertInstanceOf(ErrorHandler::class, $effective);

        \restore_error_handler();
    }

    public function testRegisterWithChainCurrentWrapsExistingCallable(): void
    {
        $previousCalled = false;
        $previous = static function (int $errno, string $errstr, string $errfile, int $errline) use (&$previousCalled): bool {
            $previousCalled = true;

            return true;
        };
        \set_error_handler($previous);

        $handler = new ErrorHandler(onFailure: new NoOpFailureHandler());
        ErrorHandler::register($handler, chainCurrent: true);

        $handler->__invoke(E_WARNING, 'test', '', 0);

        $this->assertTrue($previousCalled, 'Chained previous handler should have been called');

        \restore_error_handler(); // removes $handler registered by ErrorHandler::register()
        \restore_error_handler(); // removes $previous registered by set_error_handler() above
    }
}
