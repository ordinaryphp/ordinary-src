<?php

declare(strict_types=1);

namespace Ordinary\Uid;

/**
 * Trait for objects that have a mutable OUID.
 *
 * The OUID can be changed at any time.
 */
trait HasMutableOuidTrait
{
    /** @var non-empty-string|null */
    private ?string $uidValue = null;

    /**
     * The OUID string value.
     * @var non-empty-string
     */
    public string $uid {
        get => $this->uidValue ??= Ouid::nil()->value;

        set {
            Ouid::fromString($value);
            $this->uidValue = $value;
        }
    }

    /**
     * Get the OUID object.
     */
    public function getOuid(): Ouid
    {
        return Ouid::fromString($this->uid);
    }
}
