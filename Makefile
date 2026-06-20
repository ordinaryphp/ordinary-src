.PHONY: help install test test-fast cs-fix cs-check stan rector rector-check qa fix clean docker-shell

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install dependencies
	composer install

test: ## Run tests with coverage report
	vendor/bin/phpunit --testdox --coverage-text --coverage-html .coverage

test-fast: ## Run tests without coverage (faster — great for TDD cycles)
	XDEBUG_MODE=off vendor/bin/phpunit --testdox

cs-fix: ## Auto-fix code style (php-cs-fixer)
	vendor/bin/php-cs-fixer fix

cs-check: ## Check code style — exits non-zero if fixes are needed
	vendor/bin/php-cs-fixer check

stan: ## Run PHPStan static analysis (level max)
	vendor/bin/phpstan analyse --memory-limit=-1

rector: ## Apply Rector modernizations
	vendor/bin/rector process

rector-check: ## Rector dry-run — exits non-zero if changes are needed
	vendor/bin/rector process --dry-run

qa: cs-check rector-check stan test ## Run full quality gate (style + rector + analysis + tests)

fix: rector cs-fix ## Auto-fix: apply Rector then normalize code style

clean: ## Remove all vendor dirs and caches
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf .php-cs-fixer.cache
	rm -rf .coverage
	rm -rf packages/ordinary/*/vendor
	rm -rf packages/ordinary/*/.phpunit.cache
	find . -path ./vendor -prune -o -name ".phpunit.result.cache" -print -delete

docker-shell: ## Open an interactive shell in the Docker container
	./dev.sh
