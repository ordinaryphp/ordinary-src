<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use DateTimeImmutable;
use DateTimeZone;
use Ordinary\Log\Driver\RotatingStreamDriver;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogFormatterInterface;
use Ordinary\Log\LogLevel;
use Ordinary\Log\SynchronousDriverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RotatingStreamDriver::class)]
final class RotatingStreamDriverTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/ordinary_log_test_' . \uniqid('', true);
        \mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = \glob($this->tmpDir . '/*');
        foreach ($files !== false ? $files : [] as $file) {
            \unlink($file);
        }

        \rmdir($this->tmpDir);
    }

    private function pattern(string $name = 'app-{date}.log'): string
    {
        return $this->tmpDir . '/' . $name;
    }

    private function item(string $date, string $message = 'msg'): LogEntry
    {
        return new LogEntry(
            LogLevel::Info,
            $message,
            new DateTimeImmutable($date, new DateTimeZone('UTC')),
        );
    }

    #[Test]
    public function it_writes_to_date_stamped_file(): void
    {
        $driver = new RotatingStreamDriver($this->pattern());
        $driver->handleLog($this->item('2024-06-01'));

        $this->assertFileExists($this->tmpDir . '/app-2024-06-01.log');
    }

    #[Test]
    public function it_rotates_to_new_file_on_date_change(): void
    {
        $driver = new RotatingStreamDriver($this->pattern());
        $driver->handleLog($this->item('2024-06-01', 'day-1'));
        $driver->handleLog($this->item('2024-06-02', 'day-2'));

        $this->assertFileExists($this->tmpDir . '/app-2024-06-01.log');
        $this->assertFileExists($this->tmpDir . '/app-2024-06-02.log');

        $content1 = \file_get_contents($this->tmpDir . '/app-2024-06-01.log');
        $content2 = \file_get_contents($this->tmpDir . '/app-2024-06-02.log');
        $this->assertIsString($content1);
        $this->assertIsString($content2);
        $this->assertStringContainsString('day-1', $content1);
        $this->assertStringContainsString('day-2', $content2);
    }

    #[Test]
    public function same_date_items_go_to_same_file(): void
    {
        $driver = new RotatingStreamDriver($this->pattern());
        $driver->handleLog($this->item('2024-06-01', 'first'));
        $driver->handleLog($this->item('2024-06-01', 'second'));

        $content = \file_get_contents($this->tmpDir . '/app-2024-06-01.log');
        $this->assertIsString($content);
        $this->assertStringContainsString('first', $content);
        $this->assertStringContainsString('second', $content);
    }

    #[Test]
    public function each_line_ends_with_newline(): void
    {
        $formatter = new class implements LogFormatterInterface {
            public function formatLog(LogEntryInterface $logItem): string
            {
                return $logItem->message;
            }
        };

        $driver = new RotatingStreamDriver($this->pattern(), formatter: $formatter);
        $driver->handleLog($this->item('2024-06-01', 'line-a'));
        $driver->handleLog($this->item('2024-06-01', 'line-b'));

        $content = \file_get_contents($this->tmpDir . '/app-2024-06-01.log');
        $this->assertSame("line-a\nline-b\n", $content);
    }

    #[Test]
    public function custom_date_format_controls_rotation_granularity(): void
    {
        $driver = new RotatingStreamDriver($this->pattern('app-{date}.log'), dateFormat: 'Y-m');
        $driver->handleLog($this->item('2024-06-01'));
        $driver->handleLog($this->item('2024-06-30'));
        $driver->handleLog($this->item('2024-07-01'));

        $this->assertFileExists($this->tmpDir . '/app-2024-06.log');
        $this->assertFileExists($this->tmpDir . '/app-2024-07.log');
    }

    #[Test]
    public function throws_on_unwritable_path(): void
    {
        $driver = new RotatingStreamDriver('/nonexistent/path/{date}.log');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot open log file');
        $driver->handleLog($this->item('2024-06-01'));
    }

    #[Test]
    public function it_implements_synchronous_driver_interface(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(
            SynchronousDriverInterface::class,
            new RotatingStreamDriver($this->pattern()),
        );
    }
}
