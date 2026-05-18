<?php

declare(strict_types=1);

namespace Ordinary\Log\Tests\Driver;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use DateTimeImmutable;
use Ordinary\Log\Driver\CloudWatchDriver;
use Ordinary\Log\GenericDateTimeFormatter;
use Ordinary\Log\GenericLevelFormatter;
use Ordinary\Log\GenericLogFormatter;
use Ordinary\Log\GenericLogItem;
use Ordinary\Log\LogLevel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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

    private function makeFormatter(): GenericLogFormatter
    {
        return new GenericLogFormatter(new GenericDateTimeFormatter(), new GenericLevelFormatter());
    }

    /** @return CloudWatchLogsClient&\PHPUnit\Framework\MockObject\MockObject */
    private function makeClient(): CloudWatchLogsClient
    {
        return $this->getMockBuilder(CloudWatchLogsClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
    }

    #[Test]
    public function it_calls_put_log_events_with_correct_structure(): void
    {
        $client = $this->makeClient();

        $client->expects($this->once())
            ->method('__call')
            ->with('putLogEvents', $this->callback(function (array $argsList): bool {
                $args = $argsList[0];
                $this->assertSame('my-group', $args['logGroupName']);
                $this->assertSame('my-stream', $args['logStreamName']);
                $this->assertIsArray($args['logEvents']);
                $this->assertCount(1, $args['logEvents']);
                $event = $args['logEvents'][0];
                $this->assertIsInt($event['timestamp']);
                $this->assertIsString($event['message']);
                $this->assertStringContainsString('something failed', $event['message']);
                return true;
            }));

        $driver = new CloudWatchDriver($client, 'my-group', 'my-stream', $this->makeFormatter());
        $driver->handleLog(new GenericLogItem(LogLevel::Error, 'something failed', new DateTimeImmutable()));
    }

    #[Test]
    public function it_uses_millisecond_timestamps(): void
    {
        $client = $this->makeClient();

        $client->expects($this->once())
            ->method('__call')
            ->with('putLogEvents', $this->callback(function (array $argsList): bool {
                $args = $argsList[0];
                $event = $args['logEvents'][0];
                $this->assertIsInt($event['timestamp']);
                $this->assertGreaterThan(\time(), (int) $event['timestamp']);
                return true;
            }));

        $driver = new CloudWatchDriver($client, 'g', 's', $this->makeFormatter());
        $driver->handleLog(new GenericLogItem(LogLevel::Info, 'msg', new DateTimeImmutable()));
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
        $driver->handleLog(new GenericLogItem(LogLevel::Error, 'msg', new DateTimeImmutable()));
    }
}
