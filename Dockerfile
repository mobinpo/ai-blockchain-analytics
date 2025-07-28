# Base PHP image with extensions
FROM php:8.2-cli-alpine AS base

# Install system dependencies and PHP extensions in one layer
RUN apk add --no-cache \
    git \
    curl \
    unzip \
    icu-dev \
    postgresql-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    bash \
    $PHPIZE_DEPS && \
    docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    intl \
    pcntl \
    zip \
    bcmath \
    sockets \
    opcache && \
    apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Development stage
FROM base AS development

# Install development dependencies
RUN apk add --no-cache nodejs npm

# Copy PHP configuration
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-app.ini

# Create user to avoid permission issues
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

# Install dependencies as www user
USER www
RUN composer install --no-scripts --no-autoloader

# Copy application files
COPY --chown=1000:1000 . .

# Generate autoloader
RUN composer dump-autoload --optimize

# Production stage
FROM base AS production

# Copy PHP configuration for production
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-app.ini

# Copy application files
COPY --chown=www-data:www-data . /var/www

# Install PHP dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
COPY --from=node:18-alpine /usr/lib /usr/lib
COPY --from=node:18-alpine /usr/local/lib /usr/local/lib
COPY --from=node:18-alpine /usr/local/include /usr/local/include
COPY --from=node:18-alpine /usr/local/bin /usr/local/bin

RUN npm ci --only=production && npm run build && rm -rf node_modules

# Set correct permissions
RUN chown -R www-data:www-data /var/www

USER www-data

# CI stage for testing
FROM base AS ci

# Install additional tools for CI
RUN apk add --no-cache nodejs npm

# Create CI user
RUN addgroup -g 1001 -S ci && \
    adduser -u 1001 -S ci -G ci

USER ci

# Install dependencies first (better caching)
RUN composer install --no-scripts --no-autoloader

# Copy application files
COPY --chown=1001:1001 . .

# Complete composer setup and install Node deps
RUN composer dump-autoload --optimize && \
    npm ci

# Default stage is development
FROM development 