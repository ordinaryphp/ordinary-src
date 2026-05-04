<?php

declare(strict_types=1);

namespace Ordinary\Uid;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Immutable implementation of Ordinary Universal Identifier (OUID).
 *
 * Format: <namespace>-<time-seconds><time-microsecond>-<random-bytes>
 * Example: MYAPP-01J4K6M8P0-ABC123DEF456-XYZW
 */
final class Ouid implements OuidInterface
{
    /**
     * Padding length for seconds (supports 100+ years from Unix epoch).
     * Max seconds in ~100 years: ~3,155,760,000 -> Base32: ~6 chars
     * Using 7 chars for safety.
     */
    private const int SECONDS_LENGTH = 7;

    /**
     * Padding length for microseconds.
     * Max microseconds: 999,999 -> Base32 requires 4 chars.
     */
    private const int MICROSECONDS_LENGTH = 4;

    /**
     * Length of random bytes section (4 bytes).
     */
    private const int RANDOM_BYTES_LENGTH = 4;

    /**
     * Regex pattern for validating OUID format.
     */
    private const string PATTERN = '/^([A-Z0-9_]+)-([0-9A-Z]{7})-([0-9A-Z]{4})-([0-9A-Z]+)$/';

    /**
     * The full OUID value string.
     *
     * @var non-empty-string
     */
    public string $value {
        get => $this->valueString;
    }

    /**
     * The namespace portion.
     *
     * @var non-empty-string
     */
    public string $namespace {
        get {
            if (!isset($this->cachedNamespace)) {
                $this->parseOuid();
            }

            return $this->cachedNamespace ?? throw new \RuntimeException('Namespace not set');
        }
    }

    /**
     * The UTC datetime.
     */
    public DateTimeImmutable $datetime {
        get {
            if (!isset($this->cachedDatetime)) {
                $this->parseOuid();
            }

            return $this->cachedDatetime ?? throw new \RuntimeException('Datetime not set');
        }
    }

    /**
     * The raw random bytes.
     */
    public string $randomBytes {
        get {
            if (!isset($this->cachedRandomBytes)) {
                $this->parseOuid();
            }

            return $this->cachedRandomBytes ?? throw new \RuntimeException('Random bytes not set');
        }
    }

    /** @var non-empty-string|null */
    private ?string $cachedNamespace = null;
    private ?DateTimeImmutable $cachedDatetime = null;
    /** @var non-empty-string|null */
    private ?string $cachedRandomBytes = null;

    /**
     * @param non-empty-string $valueString
     */
    private function __construct(
        private readonly string $valueString,
    ) {
        $this->validate($valueString);
    }

    /**
     * Create OUID from string.
     *
     * @param non-empty-string $value
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Create new OUID.
     *
     * @param non-empty-string $namespace Alphanumeric uppercase and underscore only
     * @param non-empty-string $randomBytes Exactly 4 bytes
     */
    public static function create(
        string $namespace,
        DateTimeImmutable $datetime,
        string $randomBytes,
    ): self {
        self::validateNamespace($namespace);
        self::validateRandomBytes($randomBytes);

        $seconds = (int) $datetime->format('U');
        $microseconds = (int) $datetime->format('u');

        $secondsEncoded = CrockfordBase32::encode($seconds, self::SECONDS_LENGTH);
        $microsecondsEncoded = CrockfordBase32::encode($microseconds, self::MICROSECONDS_LENGTH);
        $randomEncoded = CrockfordBase32::encodeBytes($randomBytes);

        $value = \sprintf(
            '%s-%s-%s-%s',
            $namespace,
            $secondsEncoded,
            $microsecondsEncoded,
            $randomEncoded,
        );

        return new self($value);
    }

    /**
     * Create a NIL OUID.
     */
    public static function nil(): self
    {
        return new self(
            \sprintf(
                'NIL-%s-%s-%s',
                \str_pad('', self::SECONDS_LENGTH, '0'),
                \str_pad('', self::MICROSECONDS_LENGTH, '0'),
                \str_pad('', self::RANDOM_BYTES_LENGTH, '0'),
            ),
        );
    }

    /**
     * Validate OUID format.
     *
     * @param non-empty-string $value
     *
     * @return non-empty-string[]
     */
    private function validate(string $value): array
    {
        if (\preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw new InvalidArgumentException(
                \sprintf('Invalid OUID format: %s', $value),
            );
        }

        return $matches;
    }

    /**
     * Validate namespace format.
     *
     * @param non-empty-string $namespace
     */
    private static function validateNamespace(string $namespace): void
    {
        if (\preg_match('/^[A-Z0-9_]+$/', $namespace) !== 1) {
            throw new InvalidArgumentException(
                'Namespace must contain only uppercase alphanumeric characters and underscores',
            );
        }
    }

    /**
     * Validate random bytes length.
     *
     * @param non-empty-string $randomBytes
     */
    private static function validateRandomBytes(string $randomBytes): void
    {
        if (\strlen($randomBytes) !== self::RANDOM_BYTES_LENGTH) {
            throw new InvalidArgumentException(
                \sprintf(
                    'Random bytes must be exactly %d bytes, got %d',
                    self::RANDOM_BYTES_LENGTH,
                    \strlen($randomBytes),
                ),
            );
        }
    }

    /**
     * Parse OUID and cache components.
     */
    private function parseOuid(): void
    {
        [, $namespace, $secondsEncoded, $microsecondsEncoded, $randomEncoded] = $this->validate($this->valueString);

        $this->cachedNamespace = $namespace;

        $seconds = CrockfordBase32::decode($secondsEncoded);
        $microseconds = CrockfordBase32::decode($microsecondsEncoded);

        $this->cachedDatetime = new DateTimeImmutable('@' . $seconds, new DateTimeZone('UTC'))
            ->modify(\sprintf('+%d microseconds', $microseconds));

        $this->cachedRandomBytes = CrockfordBase32::decodeBytes($randomEncoded, self::RANDOM_BYTES_LENGTH);
    }
}
