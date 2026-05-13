<?php

declare(strict_types=1);

namespace Ordinary\Uid\Tests;

use InvalidArgumentException;
use Ordinary\Uid\CrockfordBase32;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CrockfordBase32::class)]
final class CrockfordBase32Test extends TestCase
{
    #[Test]
    public function it_encodes_zero(): void
    {
        $result = CrockfordBase32::encode(0);
        $this->assertSame('0', $result);
    }

    #[Test]
    public function it_encodes_with_padding(): void
    {
        $result = CrockfordBase32::encode(5, 4);
        $this->assertSame('0005', $result);
    }

    #[Test]
    #[DataProvider('encodeDecodeProvider')]
    public function it_encodes_and_decodes_numbers(int $number, string $expected): void
    {
        $encoded = CrockfordBase32::encode($number);
        $this->assertSame($expected, $encoded);

        $decoded = CrockfordBase32::decode($encoded);
        $this->assertSame($number, $decoded);
    }

    /**
     * @return \Iterator<string, array{int, string}>
     */
    public static function encodeDecodeProvider(): \Iterator
    {
        yield 'one' => [1, '1'];
        yield 'ten' => [10, 'A'];
        yield 'thirty-two' => [32, '10'];
        yield 'thousand' => [1000, 'Z8'];
        yield 'million' => [1000000, 'YGJ0'];
        yield 'max-seconds-100-years' => [3155760000, '2Y1J4W0'];
    }

    #[Test]
    public function it_handles_case_insensitive_decoding(): void
    {
        $upper = CrockfordBase32::decode('ABC');
        $lower = CrockfordBase32::decode('abc');
        $mixed = CrockfordBase32::decode('AbC');

        $this->assertSame($upper, $lower);
        $this->assertSame($upper, $mixed);
    }

    #[Test]
    public function it_handles_symbol_equivalents(): void
    {
        // O and 0 are equivalent
        $this->assertSame(CrockfordBase32::decode('0'), CrockfordBase32::decode('O'));
        $this->assertSame(CrockfordBase32::decode('0'), CrockfordBase32::decode('o'));

        // I, L, and 1 are equivalent
        $this->assertSame(CrockfordBase32::decode('1'), CrockfordBase32::decode('I'));
        $this->assertSame(CrockfordBase32::decode('1'), CrockfordBase32::decode('i'));
        $this->assertSame(CrockfordBase32::decode('1'), CrockfordBase32::decode('L'));
        $this->assertSame(CrockfordBase32::decode('1'), CrockfordBase32::decode('l'));
    }

    #[Test]
    public function it_throws_on_negative_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Number must be non-negative');

        CrockfordBase32::encode(-1);
    }

    #[Test]
    public function it_throws_on_invalid_min_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum length must be positive');

        CrockfordBase32::encode(5, 0);
    }

    #[Test]
    public function it_throws_on_empty_string_decode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Encoded string cannot be empty');

        CrockfordBase32::decode('');
    }

    #[Test]
    public function it_throws_on_invalid_character(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid character');

        CrockfordBase32::decode('ABC#');
    }

    #[Test]
    public function it_encodes_bytes(): void
    {
        $bytes = "\x01\x02\x03\x04";
        $encoded = CrockfordBase32::encodeBytes($bytes);

        $this->assertNotEmpty($encoded);
    }

    #[Test]
    public function it_encodes_and_decodes_bytes(): void
    {
        $original = "\xAB\xCD\xEF\x12";
        $encoded = CrockfordBase32::encodeBytes($original);
        $decoded = CrockfordBase32::decodeBytes($encoded, 4);

        $this->assertSame($original, $decoded);
    }

    #[Test]
    public function it_throws_on_empty_bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bytes cannot be empty');

        CrockfordBase32::encodeBytes('');
    }

    #[Test]
    public function it_throws_on_invalid_byte_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Byte length must be positive');

        CrockfordBase32::decodeBytes('ABC', 0);
    }
}
