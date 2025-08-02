# Docker Setup Guide

This document explains the Docker configuration for the AI Blockchain Analytics application.

## 🏗️ Docker Architecture

### Multi-stage Dockerfile

Our `Dockerfile` uses a multi-stage build approach with four stages:

1. **Base Stage** - Common PHP 8.3 setup with extensions
2. **Development Stage** - For local development with debugging tools
3. **CI Stage** - Optimized for continuous integration testing
4. **Production Stage** - Minimal, security-hardened for deployment

### Services

#### Application Services
- **app** - Main Laravel application with PHP-FPM + Nginx
- **horizon** - Laravel Horizon for queue management
- **vite** - Node.js 20 development server for hot reloading

#### Infrastructure Services
- **postgres** - PostgreSQL 16 database
- **redis** - Redis 7 for caching and queues
- **mailhog** - Local email testing

## 🚀 Quick Start

### Development Environment

```bash
# Clone and setup
git clone <repository>
cd ai-blockchain-analytics

# Run setup script (recommended)
./docker/scripts/dev-setup.sh

# Or manually:
cp .env.example .env
docker-compose up -d
```

### Access Points

- **Application**: http://localhost:8000
- **MailHog UI**: http://localhost:8025
- **Vite Dev Server**: http://localhost:5173
- **PostgreSQL**: localhost:5432
- **Redis**: localhost:6379

## 🧪 Testing Environment

### CI Pipeline

```bash
# Run full test suite
./docker/scripts/run-tests.sh

# Or individual components:
docker-compose -f docker-compose.ci.yml run --rm app vendor/bin/pint --test
docker-compose -f docker-compose.ci.yml run --rm app vendor/bin/psalm
docker-compose -f docker-compose.ci.yml run --rm app vendor/bin/phpunit
```

### Test Services

The CI environment uses optimized containers:
- Faster startup times
- In-memory configurations where possible
- Minimal resource usage

## 📋 Common Commands

### Development

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f app

# Access application container
docker-compose exec app bash

# Run Artisan commands
docker-compose exec app php artisan migrate
docker-compose exec app php artisan tinker

# Install dependencies
docker-compose exec app composer install
docker-compose exec vite npm install

# Stop services
docker-compose down

# Clean up (removes volumes)
docker-compose down -v
```

### Database Operations

```bash
# Run migrations
docker-compose exec app php artisan migrate

# Seed database
docker-compose exec app php artisan db:seed

# Access PostgreSQL
docker-compose exec postgres psql -U postgres -d ai_blockchain_analytics

# Reset database
docker-compose exec app php artisan migrate:refresh --seed
```

### Code Quality

```bash
# Run code style checks
docker-compose exec app vendor/bin/pint --test

# Fix code style
docker-compose exec app vendor/bin/pint

# Run static analysis
docker-compose exec app vendor/bin/psalm

# Run tests
docker-compose exec app vendor/bin/phpunit
```

## 🔧 Configuration

### Environment Variables

Key environment variables for Docker setup:

```env
# Application
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ai_blockchain_analytics
DB_USERNAME=postgres
DB_PASSWORD=password

# Redis
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Cache
CACHE_DRIVER=redis

# Session
SESSION_DRIVER=redis

# Mail (Development)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
```

### Volume Mounts

- **Source Code**: `./:/var/www` (development only)
- **Composer Cache**: `composer_cache:/root/.composer`
- **Node Modules**: `node_modules:/var/www/node_modules`
- **PostgreSQL Data**: `postgres_data:/var/lib/postgresql/data`
- **Redis Data**: `redis_data:/data`
- **Storage**: `storage:/var/www/storage`

## 🐛 Troubleshooting

### Common Issues

1. **Permission Issues**
   ```bash
   # Fix storage permissions
   docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
   ```

2. **Database Connection Issues**
   ```bash
   # Check if PostgreSQL is ready
   docker-compose exec postgres pg_isready -U postgres
   
   # View database logs
   docker-compose logs postgres
   ```

3. **Redis Connection Issues**
   ```bash
   # Test Redis connection
   docker-compose exec redis redis-cli ping
   
   # View Redis logs
   docker-compose logs redis
   ```

4. **Frontend Build Issues**
   ```bash
   # Clear Node modules and reinstall
   docker-compose exec vite rm -rf node_modules package-lock.json
   docker-compose exec vite npm install
   ```

5. **Clear Laravel Caches**
   ```bash
   docker-compose exec app php artisan cache:clear
   docker-compose exec app php artisan config:clear
   docker-compose exec app php artisan view:clear
   docker-compose exec app php artisan route:clear
   ```

### Performance Optimization

1. **Development Performance**
   ```bash
   # Use Docker BuildKit for faster builds
   export DOCKER_BUILDKIT=1
   
   # Rebuild without cache
   docker-compose build --no-cache
   ```

2. **CI Performance**
   ```bash
   # Use multi-stage builds with caching
   docker build --target ci --cache-from ai-blockchain-analytics:ci .
   ```

## 🚢 Deployment

### Building Production Images

```bash
# Build production image
docker build --target production -t ai-blockchain-analytics:latest .

# Test production image locally
docker run -p 8000:8000 ai-blockchain-analytics:latest
```

### Health Checks

All services include health checks:
- **App**: HTTP health endpoint at `/health`
- **PostgreSQL**: `pg_isready` check
- **Redis**: `redis-cli ping` check

## 🔒 Security Considerations

### Development vs Production

**Development**:
- Debug mode enabled
- Permissive error reporting
- Volume mounts for live reloading
- Additional debugging tools

**Production**:
- Minimal attack surface
- Security headers configured
- Optimized PHP settings
- No development tools

For more detailed information, see [MONOREPO.md](MONOREPO.md).