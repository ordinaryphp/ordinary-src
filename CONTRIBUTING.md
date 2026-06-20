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
./dev.sh "make test"
```

All PHP commands run inside Docker via `./dev.sh`. See the root `Dockerfile` and `docker-compose.yml` for the container definition.

## Coding Standards

This project follows strict coding standards:

- **PER Coding Style**: We use the latest PHP Evolving Recommendation (PER) standard (supersedes PSR-12)
- **Strict Types**: All PHP files must declare `declare(strict_types=1);`
- **Type Safety**: Full type hints, return types, and PHPStan strict annotations on everything
- **PHP 8.5+**: Leverage modern PHP features including `readonly` properties, property hooks, asymmetric visibility, `match` expressions, enums, first-class callables, and `clone with`

### Running Code Quality Tools

Before submitting a PR, ensure your code passes all checks:

```bash
# Auto-fix code style (php-cs-fixer)
./dev.sh "make cs-fix"

# Auto-apply Rector modernizations, then re-fix style
./dev.sh "make rector"
./dev.sh "make cs-fix"

# Validate code style (must exit 0)
./dev.sh "make cs-check"

# Validate Rector has no pending changes (must exit 0)
./dev.sh "make rector-check"

# Static analysis — PHPStan level 9
./dev.sh "make stan"

# Run all tests with coverage
./dev.sh "make test"

# Run a single package's tests in isolation
./dev.sh "cd packages/ordinary/<name> && XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text"
```

Or run the full quality suite in one command:
```bash
./dev.sh "make qa"
```

## Pull Request Process

1. **Create a Feature Branch**:
```bash
git checkout -b feature/my-new-feature
```

2. **Make Your Changes**:
   - Write tests for all new behaviors — aim for 100% coverage on new code
   - Ensure all tests pass at both root and per-package level
   - Update the affected package's `README.md` if the public API changes

3. **Run the Quality Checklist**:
   Work through in order; fix failures before advancing:
   - Lint: `find src tests -name '*.php' | xargs php -l`
   - Auto-fix: `make rector` then `make cs-fix`
   - Validate: `make cs-check` and `make rector-check` (both must exit 0)
   - Static analysis: `make stan` (both root and per-package)
   - Tests: `make test` (both root and per-package)

4. **Commit Your Changes**

5. **Create Pull Request**:
   - Add a clear title and description
   - Add exactly one version label: `version:major`, `version:minor`, or `version:patch`
   - Reference any related issues

### Version Labels

**Required**: Every PR must have exactly one version label:

- `version:major` — Breaking changes (removed or changed public API)
- `version:minor` — New features (backward compatible additions)
- `version:patch` — Bug fixes, documentation, internal refactoring

These labels drive automated version bumping and monorepo splitting on merge. PRs without a version label will fail the CI label check.

## Automated CI Checks

All PRs run the following checks automatically:

- **Code Style** — php-cs-fixer dry-run (must be clean)
- **Static Analysis** — PHPStan at level 9
- **Rector** — dry-run (must be clean; no pending modernizations)
- **Tests** — PHPUnit at root level (all packages)
- **Package Tests** — PHPUnit per-package in isolation (all 7 packages)
- **Version Label** — exactly one `version:major/minor/patch` label required

## Package Structure

When adding a new package to `packages/ordinary/`:

1. Create the package structure:
```
packages/ordinary/your-package/
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── README.md
├── src/
└── tests/
```

2. Add the package to root `composer.json` repositories and `require` sections
3. Add the package to the `package-tests` matrix in `.github/workflows/ci.yml`
4. Add the package to the matrix in `.github/workflows/split-monorepo.yml`
5. Create the sub-repository under `ordinaryphp/<name>` on GitHub

## Testing

- Write tests for all new **behaviors** — not for every method or class
- Tests are written against the public API only; internal refactoring must not break tests
- Never mock a class you own — use the real object
- Data providers must use `yield` (return `\Iterator`), not `return []`
- Aim for 100% coverage on new code; never regress existing coverage

## Documentation

- Update the affected package's `README.md` whenever the public API changes
- Keep README code examples accurate and runnable — read the source before writing
- Add PHPDoc blocks to every public class, interface, enum, method, and function

## Questions?

Feel free to open an issue for any questions about contributing!
