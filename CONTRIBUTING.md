# Contributing to OrdinaryPHP

Thank you for your interest in contributing to OrdinaryPHP!

## Development Setup

1. Clone the repository:
```bash
git clone https://github.com/ordinaryphp/ordinary-src.git
cd ordinary-src
```

2. Install dependencies:
```bash
composer install
```

3. Run tests to ensure everything is working:
```bash
vendor/bin/phpunit
```

## Coding Standards

This project follows strict coding standards:

- **PER Coding Style**: We use the latest PHP Evolving Recommendation (PER) standards
- **Strict Types**: All PHP files must declare `declare(strict_types=1);`
- **Type Safety**: Use type hints, return types, and PHPStan/Psalm annotations
- **PHP 8.5+**: Leverage modern PHP features including property hooks

### Running Code Quality Tools

Before submitting a PR, ensure your code passes all checks:

```bash
# Auto-fix formatting issues
vendor/bin/php-cs-fixer fix

# Check code style
vendor/bin/phpcs

# Static analysis
vendor/bin/phpstan analyse
vendor/bin/psalm

# Auto-refactor code
vendor/bin/rector process

# Check for code smells
vendor/bin/phpmd packages text phpmd.xml

# Run all tests
vendor/bin/phpunit
```

## Pull Request Process

1. **Create a Feature Branch**:
```bash
git checkout -b feature/my-new-feature
```

2. **Make Your Changes**:
   - Write tests for new functionality
   - Ensure all tests pass
   - Update documentation if needed

3. **Commit Your Changes**:
```bash
git add .
git commit -m "Add feature: description of changes"
```

4. **Push to GitHub**:
```bash
git push origin feature/my-new-feature
```

5. **Create Pull Request**:
   - Add a clear title and description
   - Add a version label: `version:major`, `version:minor`, or `version:patch`
   - Reference any related issues

### Version Labels

**Required**: Every PR must have a version label:

- `version:major` - Breaking changes (e.g., API changes, removed features)
- `version:minor` - New features (backward compatible)
- `version:patch` - Bug fixes, documentation, refactoring

These labels determine version bumping when your PR is merged.

## Automated Checks

All PRs will automatically run:

- Code formatting (PHP-CS-Fixer, PHP_CodeSniffer)
- Static analysis (PHPStan, Psalm)
- Rector refactoring checks
- PHP Mess Detector
- Unit tests
- Security vulnerability checks

Auto-fixes for formatting and Rector will be automatically committed to your PR.

## Package Structure

When adding new packages to `packages/ordinary/`:

1. Create the package structure:
```
packages/ordinary/your-package/
├── src/
├── tests/
├── composer.json
└── README.md
```

2. Update root `composer.json` autoload sections

3. Add package to split configuration in `.github/workflows/split-monorepo.yml`

## Testing

- Write comprehensive tests for all new features
- Maintain or improve code coverage
- Use descriptive test method names
- Use PHPUnit attributes (`#[Test]`, `#[DataProvider]`, etc.)

## Documentation

- Update README files for affected packages
- Add inline documentation for complex logic
- Include usage examples for new features
- Use PHPDoc blocks with proper type annotations

## Questions?

Feel free to open an issue for any questions about contributing!
