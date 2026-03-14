<?php

declare(strict_types=1);

namespace Ordinary\Uid;

/**
 * Interface for objects that have an OUID.
 *
 * The OUID can only be set once if it starts as nil.
 */
interface HasOuid
{
    /**
     * The OUID string value.
     */
    public string $uid { get; }

    /**
     * Get the OUID object.
     */
    public function getOuid(): Ouid;
}
