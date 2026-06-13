<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests;

use Ordinary\Error\ErrorException;
use PHPUnit\Framework\TestCase;

final class ErrorExceptionTest extends TestCase
{
    public function testFromPhpErrorCreatesInstanceWithCorrectProperties(): void
    {
        $exception = ErrorException::fromPhpError(E_WARNING, 'Undefined variable', '/app/src/Foo.php', 42);

        $this->assertSame(E_WARNING, $exception->getSeverity());
        $this->assertSame('Undefined variable', $exception->getMessage());
        $this->assertSame('/app/src/Foo.php', $exception->getFile());
        $this->assertSame(42, $exception->getLine());
        $this->assertSame(0, $exception->getCode());
        $this->assertNotInstanceOf(\Throwable::class, $exception->getPrevious());
    }

    public function testFromPhpErrorUsesDefaultsForOptionalArguments(): void
    {
        $exception = ErrorException::fromPhpError(E_NOTICE, 'Some notice');

        $this->assertSame(E_NOTICE, $exception->getSeverity());
        $this->assertSame('Some notice', $exception->getMessage());
        $this->assertSame('', $exception->getFile());
        $this->assertSame(0, $exception->getLine());
    }
}
