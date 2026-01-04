# ===================================
# Base Stage - Shared Dependencies
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

WORKDIR /var/www/html

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
    chown -R www-data:www-data /var/www/html

EXPOSE 9000
CMD ["php-fpm"]

# ===================================
# Builder Stage - Install Dependencies
# ===================================
FROM base AS builder

# Copy composer files
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

# Regenerate autoloader now that application code is present
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# Install vendor JavaScript assets (importmap)
# Downloads @hotwired/stimulus, @hotwired/turbo, three.js to assets/vendor/
RUN mkdir -p assets/vendor && \
    APP_ENV=prod php bin/console importmap:install

# Note: Tailwind CSS is built in the running container using TailwindBundle
# See deployment workflow: docker compose exec app php bin/console tailwind:build

# ===================================
# Production Stage - Minimal, Secure
# ===================================
FROM base AS prod

# Production PHP configuration
COPY docker/php/prod.ini /usr/local/etc/php/conf.d/prod.ini

# Configure PHP-FPM for production
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 9000/g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/pm.max_children = 5/pm.max_children = 10/g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/;pm.max_requests = 500/pm.max_requests = 500/g' /usr/local/etc/php-fpm.d/www.conf

# Copy application from builder
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Create runtime directories
RUN mkdir -p var/data var/cache var/log public/uploads && \
    chown -R www-data:www-data var public/uploads && \
    chmod -R 775 var public/uploads

# Health check script
RUN echo '#!/bin/sh' > /usr/local/bin/php-fpm-healthcheck && \
    echo 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000' >> /usr/local/bin/php-fpm-healthcheck && \
    chmod +x /usr/local/bin/php-fpm-healthcheck

# Security: Remove unnecessary packages
RUN apk del apk-tools

# Switch to non-root user
USER www-data

EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

CMD ["php-fpm"]
