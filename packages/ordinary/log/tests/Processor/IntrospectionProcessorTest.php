<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Processor;

use DateTimeImmutable;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\Logger;
use Ordinary\Log\LogLevel;
use Ordinary\Log\Processor\IntrospectionProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntrospectionProcessor::class)]
final class IntrospectionProcessorTest extends TestCase
{
    #[Test]
    public function it_adds_log_file_and_line_to_context(): void
    {
        $processor = new IntrospectionProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertArrayHasKey('log.file', $result->context);
        $this->assertArrayHasKey('log.line', $result->context);
        $this->assertIsString($result->context['log.file']);
        $this->assertIsInt($result->context['log.line']);
    }

    #[Test]
    public function it_records_this_test_file_as_call_site(): void
    {
        $processor = new IntrospectionProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $file = $result->context['log.file'];
        $this->assertIsString($file);
        $this->assertStringContainsString('IntrospectionProcessorTest', $file);
    }

    #[Test]
    public function it_adds_log_class_and_function_when_called_from_method(): void
    {
        $processor = new IntrospectionProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        $this->assertArrayHasKey('log.class', $result->context);
        $this->assertArrayHasKey('log.function', $result->context);
        $this->assertSame(self::class, $result->context['log.class']);
    }

    #[Test]
    public function it_does_not_mutate_original_item(): void
    {
        $processor = new IntrospectionProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $processor->process($item);

        $this->assertArrayNotHasKey('log.file', $item->context);
    }

    #[Test]
    public function it_preserves_existing_context(): void
    {
        $processor = new IntrospectionProcessor();
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable(), ['user_id' => 7]);

        $result = $processor->process($item);

        $this->assertSame(7, $result->context['user_id']);
        $this->assertArrayHasKey('log.file', $result->context);
    }

    #[Test]
    public function it_records_correct_call_site_when_called_from_logger(): void
    {
        $driver = new class implements LogDriverInterface {
            public ?LogEntryInterface $lastItem = null;

            public function handleLog(LogEntryInterface $logItem): void
            {
                $this->lastItem = $logItem;
            }
        };

        $logger = new Logger();
        $logger->addProcessor(new IntrospectionProcessor());
        $logger->add($driver);

        $logger->info('test'); // call site is THIS line

        $captured = $driver->lastItem;
        $this->assertInstanceOf(LogEntryInterface::class, $captured);
        $this->assertArrayHasKey('log.file', $captured->context);
        $file = $captured->context['log.file'];
        $this->assertIsString($file);
        $this->assertStringContainsString('IntrospectionProcessorTest', $file);
    }

    #[Test]
    public function it_skips_additional_namespaces(): void
    {
        $processor = new IntrospectionProcessor(skipNamespaces: [self::class]);
        $item = new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable());

        $result = $processor->process($item);

        // With self::class skipped, the call site reported must not be this test class
        $this->assertArrayHasKey('log.file', $result->context);
        $this->assertNotSame(self::class, $result->context['log.class'] ?? null);
    }
}
