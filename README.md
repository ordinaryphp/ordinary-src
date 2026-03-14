# OrdinaryPHP Monorepo

[![CI](https://github.com/ordinaryphp/ordinary-src/workflows/CI/badge.svg)](https://github.com/ordinaryphp/ordinary-src/actions)

This is the main monorepo for OrdinaryPHP, a modern PHP micro-framework.

## Requirements

- PHP 8.5 or higher

## Packages

This monorepo contains multiple packages that are published to Packagist:

- [ordinary/uid](packages/ordinary/uid) - Universal Unique Identifier (OUID) implementation

## Development

### Installation

```bash
composer install
```

### Running Tests

```bash
vendor/bin/phpunit
```

### Code Quality

```bash
# PHP-CS-Fixer
vendor/bin/php-cs-fixer fix

# PHP_CodeSniffer
vendor/bin/phpcs

# PHPStan
vendor/bin/phpstan analyse

# Psalm
vendor/bin/psalm

# Rector
vendor/bin/rector process

# PHP Mess Detector
vendor/bin/phpmd packages text phpmd.xml
```

### Docker Environment

Shell into a PHP 8.5 environment for testing:

```bash
docker-compose run --rm php sh
```

## Contributing

### Version Bumping

When creating pull requests, add one of the following labels to indicate the version bump:

- `version:major` - Breaking changes
- `version:minor` - New features (backward compatible)
- `version:patch` - Bug fixes and small improvements

The label will be used to automatically version the packages when the PR is merged.

## License

MIT
