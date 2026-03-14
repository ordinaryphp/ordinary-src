<?php

declare(strict_types=1);

namespace Ordinary\Uid;

use LogicException;

/**
 * Trait for objects that have an OUID.
 *
 * The OUID can only be set once if it starts as nil.
 */
trait HasOuidTrait
{
    private string $uidValue;

    /**
     * The OUID string value.
     */
    public string $uid {
        get => $this->uidValue ??= Ouid::nil()->value;

        set {
            $current = $this->uidValue ?? null;
            $nilValue = Ouid::nil()->value;

            if ($current !== null && $current !== $nilValue) {
                throw new LogicException('OUID can only be set once and is already set to a non-nil value');
            }

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
