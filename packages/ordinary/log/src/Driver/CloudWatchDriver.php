<?php

declare(strict_types=1);

namespace Ordinary\Log\Driver;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Ordinary\Log\JsonFormatter;
use Ordinary\Log\LogDriverInterface;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogFormatterInterface;

/**
 * Sends formatted log events to AWS CloudWatch Logs.
 *
 * Defaults to {@see JsonFormatter} so CloudWatch Insights can query structured
 * fields out of the box. Pass a custom formatter to override.
 *
 * Requires the aws/aws-sdk-php package:
 *
 * ```bash
 * composer require aws/aws-sdk-php
 * ```
 */
final readonly class CloudWatchDriver implements LogDriverInterface
{
    public function __construct(
        private CloudWatchLogsClient $client,
        private string $logGroupName,
        private string $logStreamName,
        private LogFormatterInterface $formatter = new JsonFormatter(),
    ) {}

    public function handleLog(LogEntryInterface $logItem): void
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
