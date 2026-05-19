<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Ordinary\Log\JsonLogFormatter;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogFormatterInterface;
use Ordinary\Log\LogItemInterface;

/**
 * Sends formatted log events to AWS CloudWatch Logs.
 *
 * Defaults to {@see JsonLogFormatter} so CloudWatch Insights can query structured
 * fields out of the box. Pass a custom formatter to override.
 *
 * Requires the aws/aws-sdk-php package:
 *
 * ```bash
 * composer require aws/aws-sdk-php
 * ```
 */
final class CloudWatchDriver implements LogDriverInterface
{
    public function __construct(
        private readonly CloudWatchLogsClient $client,
        private readonly string $logGroupName,
        private readonly string $logStreamName,
        private readonly LogFormatterInterface $formatter = new JsonLogFormatter(),
    ) {}

    public function handleLog(LogItemInterface $logItem): void
    {
        $this->client->putLogEvents([
            'logGroupName' => $this->logGroupName,
            'logStreamName' => $this->logStreamName,
            'logEvents' => [
                [
                    'timestamp' => $logItem->dateTime->getTimestamp() * 1000,
                    'message' => $this->formatter->formatLog($logItem),
                ],
            ],
        ]);
    }
}
