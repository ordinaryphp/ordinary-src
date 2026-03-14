<?php

declare(strict_types=1);

namespace Ordinary\Uid;

use InvalidArgumentException;

/**
 * Crockford Base32 encoder/decoder implementation.
 *
 * @see https://www.crockford.com/base32.html
 */
final class CrockfordBase32
{
    /**
     * Character set for Crockford Base32 encoding (excludes I, L, O, U).
     */
    private const string CHARSET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * Decode map for case-insensitive decoding with equivalents.
     *
     * @var array<string, int>
     */
    private const array DECODE_MAP = [
        '0' => 0, 'O' => 0, 'o' => 0,
        '1' => 1, 'I' => 1, 'i' => 1, 'L' => 1, 'l' => 1,
        '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 10, 'a' => 10, 'B' => 11, 'b' => 11, 'C' => 12, 'c' => 12, 'D' => 13, 'd' => 13,
        'E' => 14, 'e' => 14, 'F' => 15, 'f' => 15, 'G' => 16, 'g' => 16, 'H' => 17, 'h' => 17,
        'J' => 18, 'j' => 18, 'K' => 19, 'k' => 19, 'M' => 20, 'm' => 20, 'N' => 21, 'n' => 21,
        'P' => 22, 'p' => 22, 'Q' => 23, 'q' => 23, 'R' => 24, 'r' => 24, 'S' => 25, 's' => 25,
        'T' => 26, 't' => 26, 'V' => 27, 'v' => 27, 'W' => 28, 'w' => 28, 'X' => 29, 'x' => 29,
        'Y' => 30, 'y' => 30, 'Z' => 31, 'z' => 31,
    ];

    /**
     * Encode an integer to Crockford Base32.
     */
    public static function encode(int $number, int $minLength = 1): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Number must be non-negative');
        }

        if ($minLength < 1) {
            throw new InvalidArgumentException('Minimum length must be positive');
        }

        if ($number === 0) {
            return \str_pad('', $minLength, '0', \STR_PAD_LEFT);
        }

        $encoded = '';
        while ($number > 0) {
            $encoded = self::CHARSET[$number % 32] . $encoded;
            $number = (int) ($number / 32);
        }

        return \str_pad($encoded, $minLength, '0', \STR_PAD_LEFT);
    }

    /**
     * Decode a Crockford Base32 string to an integer.
     */
    public static function decode(string $encoded): int
    {
        if ($encoded === '') {
            throw new InvalidArgumentException('Encoded string cannot be empty');
        }

        $decoded = 0;
        $length = \strlen($encoded);

        for ($i = 0; $i < $length; $i++) {
            $char = $encoded[$i];

            if (!\array_key_exists($char, self::DECODE_MAP)) {
                throw new InvalidArgumentException(\sprintf('Invalid character "%s" in Base32 string', $char));
            }

            $decoded = ($decoded * 32) + self::DECODE_MAP[$char];
        }

        return $decoded;
    }

    /**
     * Encode bytes to Crockford Base32.
     */
    public static function encodeBytes(string $bytes): string
    {
        if ($bytes === '') {
            throw new InvalidArgumentException('Bytes cannot be empty');
        }

        $number = 0;
        $length = \strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $number = ($number << 8) | \ord($bytes[$i]);
        }

        return self::encode($number);
    }

    /**
     * Decode Crockford Base32 to bytes.
     */
    public static function decodeBytes(string $encoded, int $byteLength): string
    {
        if ($byteLength < 1) {
            throw new InvalidArgumentException('Byte length must be positive');
        }

        $number = self::decode($encoded);
        $bytes = '';

        for ($i = $byteLength - 1; $i >= 0; $i--) {
            $bytes .= \chr(($number >> ($i * 8)) & 0xFF);
        }

        return $bytes;
    }
}
