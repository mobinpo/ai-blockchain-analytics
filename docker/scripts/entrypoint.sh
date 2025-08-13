#!/bin/sh

# Enhanced RoadRunner Entrypoint Script
# Handles different container roles and initialization

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# Wait for services to be ready
wait_for_services() {
    log "Waiting for required services..."
    
    # Wait for PostgreSQL
    if [ -n "${DB_HOST:-}" ]; then
        info "Waiting for PostgreSQL at $DB_HOST:${DB_PORT:-5432}..."
        while ! pg_isready -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-postgres}" >/dev/null 2>&1; do
            sleep 2
        done
        log "PostgreSQL is ready"
    fi
    
    # Wait for Redis
    if [ -n "${REDIS_HOST:-}" ]; then
        info "Waiting for Redis at $REDIS_HOST:${REDIS_PORT:-6379}..."
        while ! redis-cli -h "$REDIS_HOST" -p "${REDIS_PORT:-6379}" ${REDIS_PASSWORD:+-a "$REDIS_PASSWORD"} ping >/dev/null 2>&1; do
            sleep 2
        done
        log "Redis is ready"
    fi
}

# Initialize Laravel application
init_laravel() {
    log "Initializing Laravel application..."
    
    # Generate app key if not set
    if [ -z "${APP_KEY:-}" ] || [ "$APP_KEY" = "base64:" ]; then
        warning "APP_KEY not set, generating new key..."
        php artisan key:generate --force
    fi
    
    # Clear and cache configuration
    php artisan config:clear
    php artisan config:cache
    
    # Clear and cache routes
    php artisan route:clear
    php artisan route:cache
    
    # Clear and cache views
    php artisan view:clear
    php artisan view:cache
    
    # Optimize autoloader
    composer dump-autoload --optimize --no-dev --classmap-authoritative
    
    log "Laravel initialization completed"
}

# Run database migrations
run_migrations() {
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        log "Running database migrations..."
        php artisan migrate --force
        log "Database migrations completed"
    else
        info "Skipping database migrations (RUN_MIGRATIONS=false)"
    fi
}

# Seed database
seed_database() {
    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        log "Running database seeders..."
        php artisan db:seed --force
        log "Database seeding completed"
    else
        info "Skipping database seeding (RUN_SEEDERS=false)"
    fi
}

# Start application based on container role
start_application() {
    local role="${CONTAINER_ROLE:-app}"
    
    case "$role" in
        "app")
            log "Starting RoadRunner application server..."
            exec rr serve -c .rr.yaml
            ;;
        "worker")
            log "Starting Horizon worker..."
            exec php artisan horizon
            ;;
        "scheduler")
            log "Starting Laravel scheduler..."
            while true; do
                php artisan schedule:run --verbose --no-interaction
                sleep 60
            done
            ;;
        "queue")
            log "Starting queue worker..."
            exec php artisan queue:work --verbose --tries=3 --timeout=90 --memory=512
            ;;
        "migrate")
            log "Running migrations only..."
            run_migrations
            exit 0
            ;;
        "seed")
            log "Running seeders only..."
            seed_database
            exit 0
            ;;
        "console")
            log "Starting interactive console..."
            exec /bin/sh
            ;;
        *)
            error "Unknown container role: $role"
            error "Available roles: app, worker, scheduler, queue, migrate, seed, console"
            exit 1
            ;;
    esac
}

# Main execution
main() {
    log "Starting container with role: ${CONTAINER_ROLE:-app}"
    
    # Set proper file permissions
    if [ "$(id -u)" = "0" ]; then
        # Running as root, fix permissions and switch to www user
        chown -R www:www /var/www/storage /var/www/bootstrap/cache
        exec su-exec www "$0" "$@"
    fi
    
    # Wait for external services
    wait_for_services
    
    # Initialize Laravel (except for migration-only containers)
    if [ "${CONTAINER_ROLE:-app}" != "migrate" ] && [ "${CONTAINER_ROLE:-app}" != "seed" ]; then
        init_laravel
    fi
    
    # Run migrations for app containers
    if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
        run_migrations
        seed_database
    fi
    
    # Start the appropriate service
    start_application "$@"
}

# Handle signals gracefully
trap 'log "Received termination signal, shutting down gracefully..."; exit 0' TERM INT

# Execute main function
main "$@"