.PHONY: up down shell install migrate tailwind build-prod clean restart logs

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
