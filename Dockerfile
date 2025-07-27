FROM php:8.2-cli-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache git bash libzip-dev icu-dev oniguruma-dev postgresql-dev \
    && docker-php-ext-install pdo_pgsql intl pcntl zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy application (except node_modules / vendor for build caching handled by volumes at runtime)
COPY . /var/www

# Ensure RR binary will be downloaded at runtime by Octane

CMD ["php", "--version"] 