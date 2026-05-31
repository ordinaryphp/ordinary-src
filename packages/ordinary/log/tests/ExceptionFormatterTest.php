<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests;

use Ordinary\Log\ExceptionFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExceptionFormatter::class)]
final class ExceptionFormatterTest extends TestCase
{
    #[Test]
    public function it_includes_class_message_code_and_location(): void
    {
        $formatter = new ExceptionFormatter(includeTrace: false);
        $e = new \RuntimeException('oops', 42);

        $result = $formatter->formatException($e);

        $this->assertStringContainsString('RuntimeException', $result);
        $this->assertStringContainsString('oops', $result);
        $this->assertStringContainsString('code 42', $result);
        $this->assertStringContainsString(__FILE__, $result);
    }

    #[Test]
    public function it_includes_stack_trace_by_default(): void
    {
        $formatter = new ExceptionFormatter();
        $e = new \RuntimeException('trace test');

        $result = $formatter->formatException($e);

        $this->assertStringContainsString('#0', $result);
    }

    #[Test]
    public function it_omits_trace_when_disabled(): void
    {
        $formatter = new ExceptionFormatter(includeTrace: false);
        $e = new \RuntimeException('no trace');

        $result = $formatter->formatException($e);

        $this->assertStringNotContainsString('#0', $result);
    }

    #[Test]
    public function it_includes_previous_exception_chain(): void
    {
        $formatter = new ExceptionFormatter(includeTrace: false);
        $previous = new \InvalidArgumentException('root cause');
        $e = new \RuntimeException('outer', 0, $previous);

        $result = $formatter->formatException($e);

        $this->assertStringContainsString('RuntimeException', $result);
        $this->assertStringContainsString('InvalidArgumentException', $result);
        $this->assertStringContainsString('Caused by:', $result);
        $this->assertStringContainsString('root cause', $result);
    }

    #[Test]
    public function it_omits_previous_exception_when_disabled(): void
    {
        $formatter = new ExceptionFormatter(includeTrace: false, includePrevious: false);
        $previous = new \InvalidArgumentException('hidden cause');
        $e = new \RuntimeException('outer', 0, $previous);

        $result = $formatter->formatException($e);

        $this->assertStringNotContainsString('InvalidArgumentException', $result);
        $this->assertStringNotContainsString('Caused by:', $result);
    }

    #[Test]
    public function it_limits_trace_depth(): void
    {
        $formatter = new ExceptionFormatter(traceDepth: 2);

        $e = new \RuntimeException('deep');

        $result = $formatter->formatException($e);

        $this->assertStringContainsString('#0', $result);
        $this->assertStringContainsString('#1', $result);
        $this->assertStringNotContainsString('#2', $result);
        $this->assertStringContainsString('more frame(s)', $result);
    }
}
