# Base PHP image with extensions
FROM php:8.4-fpm-alpine AS base

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
    supervisor \
    nginx \
    redis \
    $PHPIZE_DEPS && \
    docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    intl \
    pcntl \
    zip \
    bcmath \
    sockets \
    opcache && \
    # Install Redis extension
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Development stage
FROM base AS development

# Install development dependencies including Node.js 20
RUN apk add --no-cache nodejs npm

# Copy PHP configuration
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-app.ini

# Copy nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create application directory and set permissions
RUN mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/run/php \
    /var/log/supervisor \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

WORKDIR /var/www

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-scripts --no-autoloader --no-dev

# Copy package.json for Node dependencies
COPY package.json package-lock.json ./

# Install Node dependencies
RUN npm ci

# Copy application files
COPY . .

# Set ownership and generate autoloader
RUN chown -R www-data:www-data /var/www \
    && composer dump-autoload --optimize

# Create Laravel caches and set permissions
RUN php artisan config:cache || true \
    && php artisan route:cache || true \
    && php artisan view:cache || true \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 8000

# Switch back to root for supervisor, which manages processes as different users
USER root

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

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

# Install additional tools for CI including Node.js 20
RUN apk add --no-cache nodejs npm git

WORKDIR /var/www

# Create storage directories
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies including dev dependencies
RUN composer install --no-scripts --no-autoloader

# Copy package.json for Node dependencies
COPY package.json package-lock.json ./

# Install Node dependencies
RUN npm ci

# Copy application files
COPY . .

# Generate autoloader and build frontend
RUN composer dump-autoload --optimize \
    && npm run build

# Set permissions
RUN chmod -R 755 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Create .env for testing
RUN cp .env.example .env || echo "APP_KEY=" > .env

USER www-data

# Default stage is development
FROM development 