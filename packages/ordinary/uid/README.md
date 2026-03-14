# Ordinary UID

Universal Unique Identifier (OUID) implementation for OrdinaryPHP.

## Installation

```bash
composer require ordinary/uid
```

## Requirements

- PHP 8.5 or higher

## Features

- **Time-based**: Includes timestamp with microsecond precision
- **Namespaced**: Support for custom namespaces
- **Sortable**: Chronologically sortable by design
- **Compact**: Uses Crockford Base32 encoding for efficiency
- **Type-safe**: Full PHP 8.5 type safety with property hooks

## OUID Format

```
<namespace>-<time-seconds><time-microsecond>-<random-bytes>
```

Example: `MYAPP-01J4K6M-8P0A-XYZW1234`

- **namespace**: Alphanumeric uppercase and underscore (e.g., `MYAPP`, `MY_APP_123`)
- **time-seconds**: Crockford Base32 encoded Unix timestamp (7 chars, supports 100+ years)
- **time-microsecond**: Crockford Base32 encoded microseconds (4 chars, 0-999999)
- **random-bytes**: Crockford Base32 encoded random bytes (4 bytes)

## Usage

### Creating OUIDs

```php
use Ordinary\Uid\Ouid;
use Ordinary\Uid\OuidGenerator;
use Psr\Clock\ClockInterface;

// Using the generator (recommended)
$clock = new YourClockImplementation(); // PSR-20 Clock
$generator = new OuidGenerator('MYAPP', $clock);
$ouid = $generator->generate();

// Manual creation
$ouid = Ouid::create(
    'MYAPP',
    new DateTimeImmutable('now', new DateTimeZone('UTC')),
    random_bytes(4)
);

// From string
$ouid = Ouid::fromString('MYAPP-01J4K6M-8P0A-XYZW1234');

// NIL OUID
$nil = Ouid::nil();
```

### Accessing OUID Properties

```php
$ouid = $generator->generate();

echo $ouid->value;        // Full OUID string
echo $ouid->namespace;    // "MYAPP"
echo $ouid->datetime->format('Y-m-d H:i:s.u'); // Timestamp with microseconds
echo bin2hex($ouid->randomBytes); // Random bytes as hex
```

### Using with Objects

OUIDs can be embedded in value objects using property hooks for clean, type-safe access:

```php
use Ordinary\Uid\HasOuid;
use Ordinary\Uid\HasOuidTrait;
use Ordinary\Uid\HasMutableOuid;
use Ordinary\Uid\HasMutableOuidTrait;

// Immutable OUID (can only be set once)
class User implements HasOuid
{
    use HasOuidTrait;

    public function __construct(Ouid $ouid)
    {
        $this->uid = $ouid->value;  // Set via property
    }
}

// Mutable OUID (can be changed anytime)
class DraftPost implements HasMutableOuid
{
    use HasMutableOuidTrait;
}

// Usage
$user = new User($generator->generate());
echo $user->uid;                // String value
echo $user->getOuid()->value;   // Ouid object

$draft = new DraftPost();
$draft->uid = $generator->generate()->value;
echo $draft->getOuid()->namespace;
```

### Deterministic Random (for testing)

```php
use Random\Engine\Xoshiro256StarStar;
use Psr\Clock\ClockInterface;
use DateTimeImmutable;

// Fixed clock for testing
$clock = new class implements ClockInterface {
    public function now(): DateTimeImmutable {
        return new DateTimeImmutable('2024-01-01 12:00:00');
    }
};

$engine = new Xoshiro256StarStar(12345); // Fixed seed
$generator = new OuidGenerator('TEST', $clock, $engine);

$ouid1 = $generator->generate();
$ouid2 = $generator->generate();
// Different random bytes but deterministic sequence
```

## Crockford Base32

This package uses Crockford Base32 encoding:

- Character set: `0-9`, `A-Z` (excluding `I`, `L`, `O`, `U`)
- Case-insensitive decoding
- Symbol equivalents: `O`/`o` → `0`, `I`/`i`/`L`/`l` → `1`

```php
use Ordinary\Uid\CrockfordBase32;

$encoded = CrockfordBase32::encode(1234567);
$decoded = CrockfordBase32::decode($encoded);

$bytesEncoded = CrockfordBase32::encodeBytes("\x01\x02\x03\x04");
$bytesDecoded = CrockfordBase32::decodeBytes($bytesEncoded, 4);
```

## Testing

```bash
vendor/bin/phpunit
```

## License

MIT
