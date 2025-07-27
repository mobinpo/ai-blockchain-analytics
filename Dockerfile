FROM php:8.2-cli

# Install system dependencies and PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends git curl unzip libzip-dev libicu-dev libonig-dev libpq-dev \
    && docker-php-ext-install pdo_pgsql intl pcntl zip bcmath sockets \
    # Install Composer manually to avoid pulling separate composer image
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*


WORKDIR /var/www

# Copy application (except node_modules / vendor for build caching handled by volumes at runtime)
COPY . /var/www

# Default command just prints PHP version; actual runtime cmd set in docker-compose
CMD ["php", "--version"] 