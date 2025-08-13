# Docker Development Guide

This guide explains how to use the containerized development environment for the AI Blockchain Analytics platform.

## Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+
- Git
- Make (optional, but recommended)

## Quick Start

### 1. Clone and Setup

```bash
git clone <repository-url>
cd ai_blockchain_analytics
cp .env.example .env
```

### 2. Start Development Environment

Using Make (recommended):
```bash
make up
make install
```

Using Docker Compose directly:
```bash
docker-compose -f docker-compose.dev.yml up -d
docker-compose -f docker-compose.dev.yml exec app composer install
docker-compose -f docker-compose.dev.yml exec app php artisan key:generate
docker-compose -f docker-compose.dev.yml exec app php artisan migrate
```

### 3. Access the Application

- **Application**: http://localhost:8003
- **Vite Dev Server**: http://localhost:5173
- **MailHog**: http://localhost:8025
- **Horizon**: http://localhost:8003/horizon

## Available Commands

Run `make help` to see all available commands:

### Development Environment
- `make up` - Start development environment
- `make down` - Stop development environment
- `make restart` - Restart services
- `make logs` - View all service logs
- `make shell` - Access app container shell

### Database Operations
- `make migrate` - Run migrations
- `make migrate-fresh` - Fresh migration (drops all tables)
- `make seed` - Run database seeders
- `make fresh` - Fresh install with seeding

### Testing
- `make test` - Run PHPUnit tests
- `make test-coverage` - Run tests with coverage
- `make test-ci` - Run tests in CI environment

### Code Quality
- `make pint` - Fix code style with Laravel Pint
- `make pint-test` - Check code style (dry run)
- `make psalm` - Run Psalm static analysis
- `make phpstan` - Run PHPStan static analysis
- `make quality` - Run all quality checks

### Assets
- `make assets` - Build production assets
- `make assets-watch` - Watch assets for changes

## Docker Architecture

### Services

#### 1. App Service
- **Image**: Custom PHP 8.3 Alpine with Laravel optimizations
- **Purpose**: Main Laravel application
- **Port**: 8003 (mapped to container port 8000)
- **Features**: 
  - Xdebug enabled for debugging
  - All required PHP extensions
  - Composer installed
  - Non-root user (www:1000)

#### 2. Horizon Service
- **Image**: Same as app service
- **Purpose**: Laravel Horizon queue worker
- **Command**: `php artisan horizon`
- **Dependencies**: App, Redis

#### 3. Scheduler Service
- **Image**: Same as app service
- **Purpose**: Laravel task scheduler
- **Command**: Runs `php artisan schedule:run` every minute
- **Dependencies**: App

#### 4. Vite Service
- **Image**: Node.js 20 Alpine
- **Purpose**: Frontend asset compilation and hot-reloading
- **Port**: 5173
- **Command**: `npm run dev`

#### 5. PostgreSQL Service
- **Image**: PostgreSQL 16 Alpine
- **Purpose**: Primary database
- **Port**: 5432
- **Database**: ai_blockchain_analytics
- **Credentials**: postgres/password

#### 6. PostgreSQL Test Service
- **Image**: PostgreSQL 16 Alpine
- **Purpose**: Testing database (isolated)
- **Port**: 5433
- **Database**: ai_blockchain_analytics_test

#### 7. Redis Service
- **Image**: Redis 7 Alpine
- **Purpose**: Caching, sessions, queues
- **Port**: 6379
- **Config**: Persistent storage, 512MB memory limit

#### 8. MailHog Service
- **Image**: MailHog latest
- **Purpose**: Email testing
- **Ports**: 1025 (SMTP), 8025 (Web UI)

### Docker Targets

The Dockerfile includes multiple build targets:

#### 1. Base Target
- Common base with PHP extensions and system dependencies
- Non-root user setup
- Composer installation

#### 2. Development Target
- Includes Xdebug for debugging
- All development dependencies
- Volume mounts for live code editing
- Laravel development server

#### 3. Testing Target
- Optimized for CI/CD
- Includes testing tools (PHPUnit, Pint, Psalm)
- No Xdebug overhead
- Fast startup time

#### 4. Production Target
- Minimal dependencies
- Nginx + PHP-FPM + Supervisor
- Optimized autoloader
- Cached configuration
- Security hardened

## Development Workflow

### 1. Daily Development

```bash
# Start environment
make up

# View logs
make logs

# Access container for debugging
make shell

# Run tests
make test

# Fix code style
make pint

# Stop environment
make down
```

### 2. Database Changes

```bash
# Create migration
make artisan cmd="make:migration create_example_table"

# Run migrations
make migrate

# Fresh install (WARNING: destroys data)
make fresh
```

### 3. Asset Development

The Vite service automatically watches for changes in:
- `resources/js/`
- `resources/css/`
- `resources/views/`

Hot module replacement is enabled for Vue.js components.

### 4. Queue Development

Horizon is automatically started and available at http://localhost:8003/horizon

To manually run queue workers:
```bash
make queue
```

### 5. Debugging

#### PHP Debugging (Xdebug)
- Xdebug is pre-configured for port 9003
- Set your IDE to listen on `host.docker.internal:9003`
- Set path mappings: `/var/www` → `./`

#### Database Debugging
```bash
# Access PostgreSQL
docker-compose -f docker-compose.dev.yml exec postgres psql -U postgres -d ai_blockchain_analytics
```

#### Redis Debugging
```bash
# Access Redis CLI
docker-compose -f docker-compose.dev.yml exec redis redis-cli
```

## Environment Configuration

### Development (.env)

Key development settings:
```env
APP_ENV=local
APP_DEBUG=true
DB_HOST=postgres
DB_DATABASE=ai_blockchain_analytics
REDIS_HOST=redis
VITE_HOST=0.0.0.0
```

### Testing (.env.testing)

Key testing settings:
```env
APP_ENV=testing
DB_DATABASE=ai_blockchain_analytics_test
QUEUE_CONNECTION=sync
CACHE_DRIVER=array
SESSION_DRIVER=array
```

## Performance Optimization

### Docker Performance

1. **Use delegated volume mounts** (already configured)
2. **Enable BuildKit**:
   ```bash
   export DOCKER_BUILDKIT=1
   ```
3. **Use multi-stage builds** (already implemented)

### Laravel Performance

1. **Optimize Composer autoloader**:
   ```bash
   make artisan cmd="optimize"
   ```

2. **Cache configuration**:
   ```bash
   make artisan cmd="config:cache"
   ```

3. **Enable OPcache** (enabled in production target)

## Troubleshooting

### Common Issues

#### 1. Port Conflicts
If ports 8003, 5173, 5432, 6379, or 8025 are in use:
```bash
# Check what's using the port
lsof -i :8003

# Stop conflicting services or modify ports in docker-compose.dev.yml
```

#### 2. Permission Issues
```bash
# Fix storage permissions
make shell-root
chown -R www:www storage bootstrap/cache
```

#### 3. Database Connection Issues
```bash
# Check PostgreSQL health
make artisan cmd="migrate:status"

# Reset database
make migrate-fresh
```

#### 4. Node Dependencies Issues
```bash
# Clear Node cache
docker-compose -f docker-compose.dev.yml exec vite npm cache clean --force
docker-compose -f docker-compose.dev.yml exec vite npm install
```

#### 5. Composer Issues
```bash
# Clear Composer cache
make shell
composer clear-cache
composer install
```

### Cleanup Commands

```bash
# Clean containers and volumes
make clean

# Nuclear option - removes everything
make clean-all

# Rebuild from scratch
make clean && make build && make up && make install
```

## Production Deployment

### Building Production Image

```bash
make prod-build
```

### Starting Production Environment

```bash
make prod-up
```

The production setup includes:
- Nginx reverse proxy
- PHP-FPM for better performance
- Supervisor for process management
- Optimized caching
- Security hardening

## CI/CD Integration

### GitHub Actions

The repository includes comprehensive GitHub Actions workflows:

1. **CI Workflow** (`.github/workflows/ci.yml`):
   - Multi-version PHP testing (8.2, 8.3)
   - Multi-version Node.js testing (18, 20)
   - Database migrations
   - PHPUnit tests with coverage
   - Asset building
   - Docker image building

2. **Code Quality Workflow** (`.github/workflows/code-quality.yml`):
   - Laravel Pint (code style)
   - Psalm (static analysis)
   - PHPStan (static analysis)
   - Rector (code modernization)

### Running CI Locally

```bash
# Run full CI test suite
make test-ci

# Run code quality checks
make quality
```

## Security Considerations

### Development Security

1. **Non-root containers**: All services run as non-root users
2. **Network isolation**: Services communicate through dedicated Docker network
3. **Environment separation**: Clear separation between dev/test/prod environments
4. **Secret management**: Sensitive data through environment variables

### Production Security

1. **Minimal attack surface**: Production image contains only essential components
2. **Security headers**: Nginx configured with security headers
3. **Process isolation**: Supervisor manages processes safely
4. **Read-only filesystem**: Where possible, filesystems are read-only

## Monitoring and Logging

### Development Logging

- All service logs are collected by Docker
- Application logs go to `storage/logs/`
- Horizon logs are managed by Supervisor

### Accessing Logs

```bash
# All services
make logs

# Specific service
make logs-app
make logs-vite

# Application logs
make shell
tail -f storage/logs/laravel.log
```

## Contributing

When contributing to the project:

1. **Run quality checks**:
   ```bash
   make quality
   make test
   ```

2. **Follow code style**:
   ```bash
   make pint
   ```

3. **Update tests**:
   ```bash
   make test-coverage
   ```

4. **Test in clean environment**:
   ```bash
   make clean && make up && make test
   ```

## Support

For Docker-related issues:
1. Check this guide
2. Review Docker logs: `make logs`
3. Try cleanup: `make clean && make up`
4. Check GitHub Actions for working examples