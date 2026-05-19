<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * Marker interface for drivers that must always execute synchronously.
 *
 * When a Logger has a dispatcher configured, it will bypass the dispatcher
 * for any driver implementing this interface and invoke it immediately instead.
 *
 * Implement this on drivers whose I/O must complete before the calling code
 * continues — for example, a driver writing to a local stream where the caller
 * expects the data to be flushed before returning.
 */
interface SynchronousDriverInterface extends LogDriverInterface {}
