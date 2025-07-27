FROM php:8.2-cli-alpine 

# Install system dependencies and PHP extensions
RUN apk add --no-cache git bash curl libzip-dev icu-dev oniguruma-dev postgresql-dev \
    && docker-php-ext-install pdo_pgsql intl pcntl zip bcmath sockets \
    # Install Composer manually to avoid pulling separate composer image
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


WORKDIR /var/www

# Copy application (except node_modules / vendor for build caching handled by volumes at runtime)
COPY . /var/www

# Default command just prints PHP version; actual runtime cmd set in docker-compose
CMD ["php", "--version"] 