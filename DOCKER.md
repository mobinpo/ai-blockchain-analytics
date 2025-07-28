# Docker Setup for AI Blockchain Analytics

This project uses Docker for both development and CI/CD environments.

## Quick Start

### Development Environment

```bash
# Start all services
docker compose up -d

# Or use setup script
./docker/scripts/dev-setup.sh

# Check logs
docker compose logs -f app

# Access app container
docker compose exec app bash
```

### Services

- **App**: Laravel 12 + Octane on port 8000
- **Horizon**: Queue worker
- **Vite**: Frontend dev server on port 5173
- **PostgreSQL**: Database on port 5432
- **Redis**: Cache/Queue on port 6379
- **MailHog**: Email testing on port 8025

## Development Commands

```bash
# Install dependencies
docker compose exec app composer install
docker compose exec vite npm install

# Run migrations
docker compose exec app php artisan migrate

# Generate key
docker compose exec app php artisan key:generate

# Run tests
docker compose exec app vendor/bin/phpunit

# Code style
docker compose exec app vendor/bin/pint

# Static analysis
docker compose exec app php psalm.phar
```

## Testing

```bash
# Run all tests using CI setup
./docker/scripts/run-tests.sh

# Or manually
docker compose -f docker-compose.ci.yml up -d
docker compose -f docker-compose.ci.yml run --rm app vendor/bin/phpunit
docker compose -f docker-compose.ci.yml down
```

## Docker Stages

- **base**: Core PHP + extensions
- **development**: Dev tools + Xdebug
- **production**: Optimized for deployment
- **ci**: Testing environment

## Environment Variables

Copy `.env.example` to `.env` and configure:

```env
DB_HOST=postgres
REDIS_HOST=redis
MAIL_HOST=mailhog
```

## Troubleshooting

### Permission Issues
```bash
# Fix permissions
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Clear Caches
```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan view:clear
```

### Rebuild Images
```bash
docker compose build --no-cache
docker compose up -d --force-recreate
```