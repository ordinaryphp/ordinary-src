<?php

declare(strict_types=1);

namespace Ordinary\Uid;

/**
 * Interface for objects that have a mutable OUID.
 *
 * The OUID can be changed at any time.
 */
interface HasMutableOuid extends HasOuid
{
    /**
     * The OUID string value.
     */
    public string $uid { get; set; }
}
