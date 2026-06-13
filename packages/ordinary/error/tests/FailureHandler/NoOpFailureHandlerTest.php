<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests\FailureHandler;

use Ordinary\Error\FailureHandler\NoOpFailureHandler;
use Ordinary\Error\ThrowableHandlerInterface;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

final class NoOpFailureHandlerTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testHandleFailureDoesNotThrow(): void
    {
        $handler = new NoOpFailureHandler();

        $fakeHandler = new class implements ThrowableHandlerInterface {
            public function handle(\Throwable $throwable): void {}
        };

        $handler->handleFailure(
            $fakeHandler,
            new \RuntimeException('failure'),
            new \LogicException('original'),
        );
    }
}
