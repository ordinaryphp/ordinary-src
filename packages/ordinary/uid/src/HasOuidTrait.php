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
    /** @var non-empty-string|null */
    private ?string $uidValue = null;

    /**
     * The OUID string value.
     * @var non-empty-string
     */
    public string $uid {
        get => $this->uidValue ??= Ouid::nil()->value;

        set {
            $current = $this->uidValue;
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
