<?php

declare(strict_types=1);

namespace Ordinary\Uid\Tests;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Ordinary\Uid\HasOuidTrait;
use Ordinary\Uid\Ouid;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversTrait(HasOuidTrait::class)]
final class HasOuidTraitTest extends TestCase
{
    #[Test]
    public function it_returns_nil_by_default(): void
    {
        $object = new class {
            use HasOuidTrait;
        };

        self::assertSame(Ouid::nil()->value, $object->uid);
        self::assertSame('NIL', $object->getOuid()->namespace);
    }

    #[Test]
    public function it_allows_setting_ouid_when_nil(): void
    {
        $object = new class {
            use HasOuidTrait;
        };

        $ouid = Ouid::create('TEST', new DateTimeImmutable('now', new DateTimeZone('UTC')), "\x01\x02\x03\x04");
        $object->uid = $ouid->value;

        self::assertSame($ouid->value, $object->uid);
        self::assertSame($ouid->value, $object->getOuid()->value);
    }

    #[Test]
    public function it_prevents_changing_non_nil_ouid(): void
    {
        $object = new class {
            use HasOuidTrait;
        };

        $ouid1 = Ouid::create('TEST1', new DateTimeImmutable('now', new DateTimeZone('UTC')), "\x01\x02\x03\x04");
        $ouid2 = Ouid::create('TEST2', new DateTimeImmutable('now', new DateTimeZone('UTC')), "\x05\x06\x07\x08");

        $object->uid = $ouid1->value;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('can only be set once');

        $object->uid = $ouid2->value;
    }

    #[Test]
    public function it_allows_initializing_ouid(): void
    {
        $ouid = Ouid::create('TEST', new DateTimeImmutable('now', new DateTimeZone('UTC')), "\x01\x02\x03\x04");

        $object = new class ($ouid) {
            use HasOuidTrait;

            public function __construct(Ouid $ouid)
            {
                $this->uid = $ouid->value;
            }
        };

        self::assertSame($ouid->value, $object->uid);
        self::assertSame($ouid->value, $object->getOuid()->value);
    }
}
