<?php

declare(strict_types=1);

namespace Ordinary\Uid\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Ordinary\Uid\HasMutableOuidTrait;
use Ordinary\Uid\Ouid;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversTrait(HasMutableOuidTrait::class)]
final class HasMutableOuidTraitTest extends TestCase
{
    #[Test]
    public function it_returns_nil_by_default(): void
    {
        $object = new class {
            use HasMutableOuidTrait;
        };

        self::assertSame(Ouid::nil()->value, $object->uid);
        self::assertSame('NIL', $object->getOuid()->namespace);
    }

    #[Test]
    public function it_allows_setting_ouid(): void
    {
        $object = new class {
            use HasMutableOuidTrait;
        };

        $ouid = Ouid::create('TEST', new DateTimeImmutable('now', new DateTimeZone('UTC')), "\x01\x02\x03\x04");
        $object->uid = $ouid->value;

        self::assertSame($ouid->value, $object->uid);
        self::assertSame($ouid->value, $object->getOuid()->value);
    }

    #[Test]
    public function it_allows_changing_ouid_multiple_times(): void
    {
        $object = new class {
            use HasMutableOuidTrait;
        };

        $ouid1 = Ouid::create('TEST1', new DateTimeImmutable('now', new DateTimeZone('UTC')), "\x01\x02\x03\x04");
        $ouid2 = Ouid::create('TEST2', new DateTimeImmutable('now', new DateTimeZone('UTC')), "\x05\x06\x07\x08");

        $object->uid = $ouid1->value;
        self::assertSame($ouid1->value, $object->uid);
        self::assertSame($ouid1->value, $object->getOuid()->value);

        $object->uid = $ouid2->value;
        self::assertSame($ouid2->value, $object->uid);
        self::assertSame($ouid2->value, $object->getOuid()->value);
    }

    #[Test]
    public function it_allows_setting_back_to_nil(): void
    {
        $object = new class {
            use HasMutableOuidTrait;
        };

        $ouid = Ouid::create('TEST', new DateTimeImmutable('now', new DateTimeZone('UTC')), "\x01\x02\x03\x04");
        $object->uid = $ouid->value;

        $nil = Ouid::nil();
        $object->uid = $nil->value;

        self::assertSame($nil->value, $object->uid);
        self::assertSame($nil->value, $object->getOuid()->value);
    }
}
