# ===================================
# Multi-stage Dockerfile for Symfony + AssetMapper + Caddy
# Produces two runtime images:
#   - php_runtime: PHP-FPM application
#   - caddy_runtime: Caddy with baked-in public/ assets
# ===================================

# ===================================
# Multi-stage Dockerfile for Symfony + AssetMapper + Caddy
# Targets:
#   - dev: Development with bind mounts
#   - php_runtime: Production PHP-FPM application
#   - caddy_runtime: Production Caddy with baked-in assets
# ===================================

# ===================================
# Base Stage - Shared PHP Dependencies
# ===================================
FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libpng \
    libjpeg-turbo \
    freetype \
    oniguruma \
    libzip \
    icu-libs \
    sqlite-libs \
    fcgi

# Install PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libzip-dev \
    icu-dev \
    sqlite-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        mbstring \
        exif \
        gd \
        zip \
        intl \
        opcache \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv/app

# ===================================
# Development Stage
# ===================================
FROM base AS dev

# Install dev dependencies
RUN apk add --no-cache \
    git \
    zip \
    unzip

# Copy custom PHP configuration (development)
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Configure PHP-FPM
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 9000/g' /usr/local/etc/php-fpm.d/www.conf

# Create directories
RUN mkdir -p var/data var/cache var/log public/uploads && \
    chown -R www-data:www-data /srv/app

EXPOSE 9000
CMD ["php-fpm"]

# ===================================
# Builder Stage - Install Dependencies & Compile Assets
# ===================================
FROM base AS builder

# Copy composer files first (layer caching)
COPY composer.json composer.lock symfony.lock ./

# Install production dependencies (no dev packages)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

# Copy application code
COPY . .

# Regenerate autoloader with application code present
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# Set production environment for asset compilation
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Create required directories
RUN mkdir -p var/cache var/log var/data public/assets assets/vendor

# Install vendor JavaScript assets (AssetMapper importmap)
# Downloads @hotwired/stimulus, @hotwired/turbo, etc. to assets/vendor/
# Note: This requires network access during build, but versions are pinned in importmap.php
# so builds are deterministic. This is the standard Symfony AssetMapper workflow.
RUN php bin/console importmap:install

# Build Tailwind CSS (if TailwindBundle is present)
# Compiles to var/tailwind/app.built.css
RUN php bin/console tailwind:build

# Compile AssetMapper assets into public/assets/
# Creates versioned files + manifest.json + importmap.json
RUN php bin/console asset-map:compile

# Clear cache (warmup happens at runtime when secrets/env are available)
RUN php bin/console cache:clear --env=prod

# ===================================
# PHP Runtime - Production PHP-FPM
# ===================================
FROM base AS php_runtime

# Copy production PHP configuration
COPY docker/php/prod.ini /usr/local/etc/php/conf.d/prod.ini

# Configure PHP-FPM for production
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 9000/g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/pm.max_children = 5/pm.max_children = 10/g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/;pm.max_requests = 500/pm.max_requests = 500/g' /usr/local/etc/php-fpm.d/www.conf

# Copy application from builder (includes vendor, compiled assets, warmed cache)
# Use 1000:1000 to match runtime user
COPY --from=builder --chown=1000:1000 /srv/app /srv/app

# Create runtime directories with correct ownership
# var/data and var/sessions persist via volume mounts
# var/cache and var/log are ephemeral (rebuilt from image on restart)
RUN mkdir -p var/data var/sessions var/cache var/log public/uploads && \
    chown -R 1000:1000 var public/uploads && \
    chmod -R 775 var public/uploads

# Health check script
RUN echo '#!/bin/sh' > /usr/local/bin/php-fpm-healthcheck && \
    echo 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000' >> /usr/local/bin/php-fpm-healthcheck && \
    chmod +x /usr/local/bin/php-fpm-healthcheck

# Security: Remove unnecessary packages
RUN apk del apk-tools

# Switch to non-root user
USER 1000:1000

EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

CMD ["php-fpm"]

# ===================================
# Caddy Runtime - Static File Server
# ===================================
FROM caddy:2-alpine AS caddy_runtime

# Copy ONLY the public directory from php_runtime
# This includes:
#   - public/index.php (Symfony front controller)
#   - public/assets/ (compiled AssetMapper assets with manifest)
#   - public/bundles/ (public bundle assets)
#   - public/fonts/, public/css/ (any static assets)
# Excludes:
#   - public/uploads/ (mounted as volume in production)
COPY --from=php_runtime /srv/app/public /srv/app/public

# Caddy runs as root by default, which is fine for read-only serving
# Public directory is immutable in production

EXPOSE 80 443 443/udp

# Caddy health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:80/health || exit 1
