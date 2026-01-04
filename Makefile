.PHONY: up down shell install migrate tailwind build-prod clean restart logs fixtures test-db-reset test-fixtures test-phpunit test test-coverage

# Start containers
up:
	docker-compose up -d
	@echo "Site available at http://localhost:8000"

# Stop containers
down:
	docker-compose down

# Shell into app container
shell:
	docker-compose exec app sh

# Install Composer dependencies
install:
	docker-compose exec app composer install

# Run database migrations
migrate:
	docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Watch Tailwind CSS (development)
tailwind:
	@echo "Watching Tailwind CSS for changes..."
	@cd tailwind && ./tailwindcss -i input.css -o ../public/css/app.css --watch

# Build Tailwind CSS (production)
build-prod:
	@echo "Building production CSS..."
	@cd tailwind && ./tailwindcss -i input.css -o ../public/css/app.css --minify

# Download Tailwind standalone CLI
tailwind-install:
	@mkdir -p tailwind
	@echo "Downloading Tailwind CSS standalone CLI..."
	@curl -sLO https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-macos-arm64
	@mv tailwindcss-macos-arm64 tailwind/tailwindcss
	@chmod +x tailwind/tailwindcss
	@echo "Tailwind CLI installed to tailwind/tailwindcss"

# Clean cache and logs
clean:
	docker-compose exec app php bin/console cache:clear
	docker-compose exec app rm -rf var/cache/* var/log/*

# Restart containers
restart: down up

# View logs
logs:
	docker-compose logs -f

# Build and start fresh
build:
	docker-compose build --no-cache
	docker-compose up -d

# Create database
db-create:
	docker-compose exec app php bin/console doctrine:database:create --if-not-exists

# Run migrations and load fixtures (if any)
db-setup: db-create migrate
	@echo "Database setup complete"

# Load fixtures (development)
fixtures:
	php bin/console doctrine:fixtures:load --no-interaction
	@echo "Fixtures loaded successfully"

# ========================================
# Test Database Commands
# ========================================

# Drop and recreate test database (SQLite)
test-db-reset:
	@echo "Resetting test database..."
	@mkdir -p var/data
	APP_ENV=test php bin/console doctrine:schema:drop --force --full-database 2>/dev/null || true
	APP_ENV=test php bin/console doctrine:schema:create
	@echo "Test database reset complete"

# Load fixtures into test database
test-fixtures:
	@echo "Loading test fixtures..."
	APP_ENV=test php bin/console doctrine:fixtures:load --no-interaction
	@echo "Test fixtures loaded successfully"

# Run PHPUnit tests with fresh database and fixtures
test-phpunit: test-db-reset test-fixtures
	@echo "Running PHPUnit test suite..."
	php bin/phpunit
	@echo "All tests complete!"

# Convenience target for running all tests
test: test-phpunit

# Run tests with coverage (requires xdebug)
test-coverage:
	@echo "Running tests with coverage..."
	XDEBUG_MODE=coverage php bin/phpunit --coverage-html var/coverage
	@echo "Coverage report generated in var/coverage/"
