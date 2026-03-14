<?php

declare(strict_types=1);

namespace Ordinary\Uid\Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Ordinary\Uid\Ouid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ouid::class)]
final class OuidTest extends TestCase
{
    #[Test]
    public function it_creates_ouid_from_components(): void
    {
        $namespace = 'TESTAPP';
        $datetime = new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('UTC'));
        $randomBytes = "\x01\x02\x03\x04";

        $ouid = Ouid::create($namespace, $datetime, $randomBytes);

        self::assertSame($namespace, $ouid->namespace);
        self::assertSame($datetime->format('U'), $ouid->datetime->format('U'));
        self::assertSame($datetime->format('u'), $ouid->datetime->format('u'));
        self::assertSame($randomBytes, $ouid->randomBytes);
    }

    #[Test]
    public function it_creates_ouid_from_string(): void
    {
        $value = 'MYAPP-0000001-0000-01020304';
        $ouid = Ouid::fromString($value);

        self::assertSame($value, $ouid->value);
    }

    #[Test]
    public function it_creates_nil_ouid(): void
    {
        $nil = Ouid::nil();

        self::assertSame('NIL', $nil->namespace);
        self::assertStringContainsString('NIL-', $nil->value);
    }

    #[Test]
    public function it_validates_namespace_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('uppercase alphanumeric');

        $datetime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $randomBytes = "\x01\x02\x03\x04";

        Ouid::create('invalid-namespace', $datetime, $randomBytes);
    }

    #[Test]
    public function it_allows_uppercase_and_underscore_in_namespace(): void
    {
        $datetime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $randomBytes = "\x01\x02\x03\x04";

        $ouid = Ouid::create('MY_APP_123', $datetime, $randomBytes);

        self::assertSame('MY_APP_123', $ouid->namespace);
    }

    #[Test]
    public function it_validates_random_bytes_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly 4 bytes');

        $datetime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $randomBytes = "\x01\x02"; // Only 2 bytes

        Ouid::create('TESTAPP', $datetime, $randomBytes);
    }

    #[Test]
    public function it_validates_ouid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid OUID format');

        Ouid::fromString('invalid-format');
    }

    #[Test]
    public function it_parses_components_lazily(): void
    {
        $namespace = 'TESTAPP';
        $datetime = new DateTimeImmutable('2024-01-01 12:00:00.500000', new DateTimeZone('UTC'));
        $randomBytes = "\xAB\xCD\xEF\x12";

        $ouid = Ouid::create($namespace, $datetime, $randomBytes);
        $value = $ouid->value;

        // Re-create from string to test lazy parsing
        $parsed = Ouid::fromString($value);

        self::assertSame($namespace, $parsed->namespace);
        self::assertSame($datetime->format('U'), $parsed->datetime->format('U'));
        self::assertSame($datetime->format('u'), $parsed->datetime->format('u'));
        self::assertSame($randomBytes, $parsed->randomBytes);
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $ouid = Ouid::create('TEST', new DateTimeImmutable('now', new DateTimeZone('UTC')), "\x01\x02\x03\x04");

        // Access properties multiple times to ensure consistency
        $value1 = $ouid->value;
        $value2 = $ouid->value;

        self::assertSame($value1, $value2);
    }

    #[Test]
    public function it_handles_max_microseconds(): void
    {
        $datetime = new DateTimeImmutable('2024-01-01 12:00:00.999999', new DateTimeZone('UTC'));
        $randomBytes = "\x01\x02\x03\x04";

        $ouid = Ouid::create('TEST', $datetime, $randomBytes);

        self::assertSame('999999', $ouid->datetime->format('u'));
    }

    #[Test]
    public function it_handles_zero_microseconds(): void
    {
        $datetime = new DateTimeImmutable('2024-01-01 12:00:00.000000', new DateTimeZone('UTC'));
        $randomBytes = "\x01\x02\x03\x04";

        $ouid = Ouid::create('TEST', $datetime, $randomBytes);

        self::assertSame('000000', $ouid->datetime->format('u'));
    }
}
