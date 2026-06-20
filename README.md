# OrdinaryPHP Monorepo

[![CI](https://github.com/ordinaryphp/ordinary-src/workflows/CI/badge.svg)](https://github.com/ordinaryphp/ordinary-src/actions)

This is the main monorepo for OrdinaryPHP, a modern PHP micro-framework.

## Requirements

- PHP 8.5 or higher

## Packages

This monorepo contains multiple packages that are published to Packagist:

| Package | Description |
|---------|-------------|
| [ordinary/cli](packages/ordinary/cli) | CLI argument parser and subcommand router with typed parsed results and auto-generated help text |
| [ordinary/config](packages/ordinary/config) | Flat key/value configuration management with layered and pipeline resolution |
| [ordinary/container](packages/ordinary/container) | Dependency injection container with native lazy objects, autowiring, contextual bindings, and PSR-11 compliance |
| [ordinary/error](packages/ordinary/error) | PHP error and exception handler management |
| [ordinary/log](packages/ordinary/log) | Structured PSR-3 compatible logging with pluggable drivers and formatters |
| [ordinary/router](packages/ordinary/router) | HTTP router with a global parameter registry, named routes, URL generation, and attribute-based route discovery |
| [ordinary/uid](packages/ordinary/uid) | Time-based, namespaced, sortable unique identifier (OUID) implementation |

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
```

### Docker Environment

Shell into a PHP 8.5 environment with Composer and Xdebug pre-installed:

```bash
# Build the image (first time only)
docker-compose build

# Start a shell
docker-compose run --rm php sh

# Inside the container, you can use composer
composer install
composer test

# Xdebug is already enabled for debugging and coverage
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
