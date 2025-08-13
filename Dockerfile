# Base image with PHP extensions
FROM php:8.3-cli-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    curl \
    unzip \
    zip \
    git \
    sqlite \
    postgresql-dev \
    linux-headers \
    $PHPIZE_DEPS \
    bash \
    nginx \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install \
    bcmath \
    pcntl \
    sockets \
    pdo_pgsql \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create non-root user
RUN addgroup -g 1000 www && \
    adduser -u 1000 -G www -s /bin/sh -D www

WORKDIR /var/www

# Development target
FROM base AS development

# Enable Xdebug for development
RUN docker-php-ext-enable xdebug
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Copy application files
COPY --chown=www:www . .

# Install dependencies with dev packages
RUN composer install --optimize-autoloader \
    && chmod -R 777 storage bootstrap/cache 2>/dev/null || true

USER www
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# Testing target
FROM base AS testing

# Copy application files
COPY --chown=www:www . .

# Install dependencies including dev packages for testing
RUN composer install --optimize-autoloader \
    && chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Install testing tools
RUN composer require --dev \
    phpunit/phpunit \
    laravel/pint \
    vimeo/psalm \
    --no-scripts

USER www

# Production target
FROM base AS production

# Remove development packages
RUN apk del $PHPIZE_DEPS

# Copy application files
COPY --chown=www:www . .

# Install production dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize \
    && chmod -R 755 storage bootstrap/cache 2>/dev/null || true \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Configure nginx
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Configure supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

USER www
EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]