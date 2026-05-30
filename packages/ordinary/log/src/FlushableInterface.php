<?php

declare(strict_types=1);

namespace Ordinary\Log;

/**
 * Marks a driver that accumulates state and can release it on demand.
 *
 * Register the driver with Logger normally; call Logger::flush() to propagate
 * to all registered drivers that implement this interface. Decorator drivers
 * must cascade flush() to their inner driver before returning.
 */
interface FlushableInterface
{
    /**
     * Releases buffered or pending state to the underlying destination.
     *
     * When wrapping another driver, implementations must cascade: call flush()
     * on the inner driver if it also implements FlushableInterface.
     */
    public function flush(): void;
}
