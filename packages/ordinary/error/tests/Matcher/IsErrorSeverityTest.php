<?php

declare(strict_types=1);

namespace Ordinary\Error\Tests\Matcher;

use Ordinary\Error\Matcher\IsErrorSeverity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsErrorSeverityTest extends TestCase
{
    public function testMatchesErrorExceptionWithMatchingSeverity(): void
    {
        $matcher = new IsErrorSeverity(E_WARNING);
        $exception = new \ErrorException('test', 0, E_WARNING);

        $this->assertTrue($matcher->matches($exception));
    }

    public function testDoesNotMatchErrorExceptionWithDifferentSeverity(): void
    {
        $matcher = new IsErrorSeverity(E_WARNING);
        $exception = new \ErrorException('test', 0, E_NOTICE);

        $this->assertFalse($matcher->matches($exception));
    }

    public function testMatchesWhenSeverityOverlapsAnyOfMultipleFlags(): void
    {
        $matcher = new IsErrorSeverity(E_WARNING, E_NOTICE);

        $this->assertTrue($matcher->matches(new \ErrorException('test', 0, E_WARNING)));
        $this->assertTrue($matcher->matches(new \ErrorException('test', 0, E_NOTICE)));
    }

    public function testDoesNotMatchNonErrorExceptionThrowable(): void
    {
        $matcher = new IsErrorSeverity(E_WARNING);

        $this->assertFalse($matcher->matches(new \RuntimeException('not an error')));
        $this->assertFalse($matcher->matches(new \LogicException('not an error')));
    }

    /** @return \Iterator<string, array{int, bool}> */
    public static function severityProvider(): \Iterator
    {
        yield 'E_ERROR matches E_ERROR mask' => [E_ERROR, true];
        yield 'E_WARNING matches E_WARNING mask' => [E_WARNING, true];
        yield 'E_NOTICE does not match E_WARNING mask' => [E_NOTICE, false];
        yield 'E_DEPRECATED does not match E_WARNING mask' => [E_DEPRECATED, false];
    }

    #[DataProvider('severityProvider')]
    public function testMatchesBasedOnSeverityBitmask(int $severity, bool $expected): void
    {
        $matcher = new IsErrorSeverity(E_WARNING, E_ERROR);
        $exception = new \ErrorException('test', 0, $severity);

        $this->assertSame($expected, $matcher->matches($exception));
    }
}
