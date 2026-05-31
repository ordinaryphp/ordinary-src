<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use DateTimeImmutable;
use Ordinary\Log\DateTimeFormatter;
use Ordinary\Log\Driver\CloudWatchDriver;
use Ordinary\Log\LevelFormatter;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogLevel;
use Ordinary\Log\TextFormatter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudWatchDriver::class)]
final class CloudWatchDriverTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\class_exists(CloudWatchLogsClient::class)) {
            $this->markTestSkipped('aws/aws-sdk-php is not installed');
        }
    }

    private function makeFormatter(): TextFormatter
    {
        return new TextFormatter(new DateTimeFormatter(), new LevelFormatter());
    }

    /** @return CloudWatchLogsClient&MockObject */
    private function makeClient(): CloudWatchLogsClient
    {
        return $this->getMockBuilder(CloudWatchLogsClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
    }

    #[Test]
    public function it_uses_json_log_formatter_by_default(): void
    {
        $client = $this->makeClient();

        $client->expects($this->once())
            ->method('__call')
            ->with('putLogEvents', $this->callback(function (array $argsList): bool {
                $args = $argsList[0];
                $this->assertIsArray($args);
                $logEvents = $args['logEvents'];
                $this->assertIsArray($logEvents);
                $event = $logEvents[0];
                $this->assertIsArray($event);
                $message = $event['message'];
                $this->assertIsString($message);
                $decoded = \json_decode($message, true);
                $this->assertIsArray($decoded);
                $this->assertArrayHasKey('level', $decoded);
                $this->assertArrayHasKey('message', $decoded);
                return true;
            }));

        $driver = new CloudWatchDriver($client, 'g', 's');
        $driver->handleLog(new LogEntry(LogLevel::Info, 'default formatter test', new DateTimeImmutable()));
    }

    #[Test]
    public function it_calls_put_log_events_with_correct_structure(): void
    {
        $client = $this->makeClient();

        $client->expects($this->once())
            ->method('__call')
            ->with('putLogEvents', $this->callback(function (array $argsList): bool {
                $args = $argsList[0];
                $this->assertIsArray($args);
                $this->assertSame('my-group', $args['logGroupName']);
                $this->assertSame('my-stream', $args['logStreamName']);
                $logEvents = $args['logEvents'];
                $this->assertIsArray($logEvents);
                $this->assertCount(1, $logEvents);
                $event = $logEvents[0];
                $this->assertIsArray($event);
                $this->assertIsInt($event['timestamp']);
                $this->assertIsString($event['message']);
                $this->assertStringContainsString('something failed', $event['message']);
                return true;
            }));

        $driver = new CloudWatchDriver($client, 'my-group', 'my-stream', $this->makeFormatter());
        $driver->handleLog(new LogEntry(LogLevel::Error, 'something failed', new DateTimeImmutable()));
    }

    #[Test]
    public function it_uses_millisecond_timestamps(): void
    {
        $client = $this->makeClient();

        $client->expects($this->once())
            ->method('__call')
            ->with('putLogEvents', $this->callback(function (array $argsList): bool {
                $args = $argsList[0];
                $this->assertIsArray($args);
                $logEvents = $args['logEvents'];
                $this->assertIsArray($logEvents);
                $event = $logEvents[0];
                $this->assertIsArray($event);
                $this->assertIsInt($event['timestamp']);
                $this->assertGreaterThan(\time(), $event['timestamp']);
                return true;
            }));

        $driver = new CloudWatchDriver($client, 'g', 's', $this->makeFormatter());
        $driver->handleLog(new LogEntry(LogLevel::Info, 'msg', new DateTimeImmutable()));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function it_propagates_exceptions_from_client(): void
    {
        $client = $this->makeClient();
        $client->method('__call')->willThrowException(new \RuntimeException('network error'));

        $driver = new CloudWatchDriver($client, 'g', 's', $this->makeFormatter());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('network error');
        $driver->handleLog(new LogEntry(LogLevel::Error, 'msg', new DateTimeImmutable()));
    }
}
