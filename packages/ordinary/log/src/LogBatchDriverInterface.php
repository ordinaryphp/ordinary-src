<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * Optional extension for drivers that can ingest multiple log items in a single call.
 *
 * Implement this alongside LogDriverInterface when your backend supports bulk writes
 * (e.g. CloudWatch PutLogEvents, a database multi-row INSERT, Elasticsearch bulk API).
 * BufferingDriver checks for this interface during flush and calls handleLogBatch
 * instead of issuing individual handleLog calls.
 */
interface LogBatchDriverInterface extends LogDriverInterface
{
    /**
     * Writes all items in the batch to the destination in a single operation.
     *
     * Called by BufferingDriver::flush() when the inner driver supports bulk writes.
     * Implementations are responsible for handling partial failures internally.
     */
    public function handleLogBatch(LogBatch $batch): void;
}
