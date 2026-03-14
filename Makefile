.PHONY: help install test cs-fix cs-check stan psalm rector phpmd qa fix clean docker-shell

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install dependencies
	composer install

test: ## Run tests
	vendor/bin/phpunit --testdox --coverage-text --coverage-html .coverage

cs-fix: ## Fix code style issues
	vendor/bin/php-cs-fixer fix

cs-check: ## Check code style
	vendor/bin/php-cs-fixer check

stan: ## Run PHPStan static analysis
	vendor/bin/phpstan analyse

psalm: ## Run Psalm static analysis
	vendor/bin/psalm

rector: ## Run Rector refactoring
	vendor/bin/rector process

rector-check: ## Check Rector suggestions (dry run)
	vendor/bin/rector process --dry-run

phpmd: ## Run PHP Mess Detector
	vendor/bin/phpmd packages text phpmd.xml

qa: cs-check stan psalm phpmd test ## Run all quality checks

fix: cs-fix rector ## Auto-fix code issues

clean: ## Clean build artifacts
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf packages/*/vendor
	find . -name ".phpunit.result.cache" -delete

docker-shell: ## Start a shell in Docker PHP environment
	docker compose run --rm php sh
