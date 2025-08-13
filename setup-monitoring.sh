#!/bin/bash

# AI Blockchain Analytics - Monitoring Setup Script
# Sets up Sentry error tracking and Laravel Telescope debugging with production restrictions

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/monitoring-setup.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# Check if Laravel app is working
check_laravel() {
    log "Checking Laravel application status..."
    
    if ! php artisan --version >/dev/null 2>&1; then
        log_error "Laravel application has bootstrap issues. Please fix before continuing."
        log_error "Try: composer dump-autoload && php artisan optimize:clear"
        return 1
    fi
    
    log_success "Laravel application is working"
    return 0
}

# Setup Sentry
setup_sentry() {
    log "Setting up Sentry error tracking..."
    
    # Check if Sentry is already installed
    if ! composer show sentry/sentry-laravel >/dev/null 2>&1; then
        log "Installing Sentry Laravel package..."
        composer require sentry/sentry-laravel
    else
        log_success "Sentry Laravel package already installed"
    fi
    
    # Publish Sentry config if needed
    if [ ! -f "config/sentry.php" ]; then
        log "Publishing Sentry configuration..."
        php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
    else
        log_success "Sentry configuration already exists"
    fi
    
    log_success "Sentry setup completed"
}

# Setup Telescope
setup_telescope() {
    log "Setting up Laravel Telescope..."
    
    # Check if Telescope is already installed
    if ! composer show laravel/telescope >/dev/null 2>&1; then
        log "Installing Laravel Telescope package..."
        composer require laravel/telescope --dev
    else
        log_success "Laravel Telescope package already installed"
    fi
    
    # Try to install Telescope
    if php artisan --version >/dev/null 2>&1; then
        log "Installing Telescope assets and migrations..."
        php artisan telescope:install 2>/dev/null || log_warning "Telescope installation had issues, continuing..."
        
        # Run migrations if database is available
        if php artisan migrate:status >/dev/null 2>&1; then
            log "Running Telescope migrations..."
            php artisan migrate
        else
            log_warning "Database not available, skipping migrations"
        fi
    else
        log_warning "Laravel has bootstrap issues, skipping automatic Telescope setup"
    fi
    
    log_success "Telescope setup completed"
}

# Create environment configuration
create_env_config() {
    log "Creating environment configuration examples..."
    
    cat > .env.monitoring.example << 'EOF'
# =============================================================================
# AI Blockchain Analytics - Monitoring Configuration
# =============================================================================

# -----------------------------------------------------------------------------
# Sentry Error Tracking Configuration
# -----------------------------------------------------------------------------
# Get your DSN from https://sentry.io/settings/projects/your-project/keys/
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
SENTRY_ENVIRONMENT=${APP_ENV}
SENTRY_RELEASE=v1.0.0

# Performance monitoring sample rates (0.0 to 1.0)
# Lower values in production to reduce overhead
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_PROFILES_SAMPLE_RATE=0.1

# Privacy settings
SENTRY_SEND_DEFAULT_PII=false
SENTRY_ENABLE_LOCAL=false

# Breadcrumbs and tracing
SENTRY_BREADCRUMBS_LOGS=true
SENTRY_BREADCRUMBS_SQL_QUERIES=false
SENTRY_BREADCRUMBS_SQL_BINDINGS=false
SENTRY_BREADCRUMBS_QUEUE_INFO=true
SENTRY_BREADCRUMBS_COMMAND_INFO=true

SENTRY_TRACE_QUEUE_ENABLED=true
SENTRY_TRACE_SQL_QUERIES=false
SENTRY_TRACE_SQL_ORIGIN=false
SENTRY_TRACE_VIEWS=true
SENTRY_TRACE_LIVEWIRE=false
SENTRY_TRACE_HTTP_CLIENT_REQUESTS=true
SENTRY_TRACE_REDIS_COMMANDS=false
SENTRY_TRACE_MISSING_ROUTES=true
SENTRY_TRACE_CONTINUE_AFTER_RESPONSE=false

# -----------------------------------------------------------------------------
# Laravel Telescope Configuration
# -----------------------------------------------------------------------------
# Enable/disable Telescope (disabled in production by default)
TELESCOPE_ENABLED=true
TELESCOPE_DOMAIN=
TELESCOPE_PATH=telescope
TELESCOPE_DRIVER=database

# Production settings (TELESCOPE_ENABLED must be true for these to work)
TELESCOPE_PRODUCTION_ENABLED=false

# Performance settings
TELESCOPE_SAMPLING_RATE=0.1

# Data retention (production only)
TELESCOPE_RETENTION_HOURS=24
TELESCOPE_RETENTION_LIMIT=1000

# Security restrictions for production
TELESCOPE_ALLOWED_IPS="192.168.1.100,10.0.0.5"
TELESCOPE_ALLOWED_USERS="admin@yourcompany.com,developer@yourcompany.com"
TELESCOPE_REQUIRED_PERMISSION=view-telescope
TELESCOPE_AUTO_DISABLE_HOURS=8

# Telescope Watchers (enable/disable specific watchers)
TELESCOPE_CACHE_WATCHER=true
TELESCOPE_COMMAND_WATCHER=true
TELESCOPE_DUMP_WATCHER=true
TELESCOPE_EVENT_WATCHER=true
TELESCOPE_EXCEPTION_WATCHER=true
TELESCOPE_JOB_WATCHER=true
TELESCOPE_LOG_WATCHER=true
TELESCOPE_MAIL_WATCHER=true
TELESCOPE_MODEL_WATCHER=true
TELESCOPE_NOTIFICATION_WATCHER=true
TELESCOPE_QUERY_WATCHER=true
TELESCOPE_REDIS_WATCHER=true
TELESCOPE_REQUEST_WATCHER=true
TELESCOPE_RESPONSE_SIZE_LIMIT=64
TELESCOPE_GATE_WATCHER=true
TELESCOPE_SCHEDULE_WATCHER=true
TELESCOPE_VIEW_WATCHER=true
TELESCOPE_CLIENT_REQUEST_WATCHER=true
EOF

    log_success "Environment configuration examples created in .env.monitoring.example"
}

# Create production deployment guide
create_deployment_guide() {
    log "Creating deployment guide..."
    
    cat > MONITORING_DEPLOYMENT_GUIDE.md << 'EOF'
# AI Blockchain Analytics - Monitoring Deployment Guide

## Overview
This guide covers deploying Sentry error tracking and Laravel Telescope debugging with production-safe configurations.

## Sentry Setup

### 1. Create Sentry Project
1. Sign up at https://sentry.io
2. Create a new Laravel project
3. Copy your DSN from Project Settings > Client Keys

### 2. Environment Configuration
Add to your production `.env`:
```bash
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
SENTRY_ENVIRONMENT=production
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_PROFILES_SAMPLE_RATE=0.1
SENTRY_SEND_DEFAULT_PII=false
```

### 3. Production Considerations
- Sample rates are set to 10% to reduce performance impact
- PII (Personally Identifiable Information) is disabled
- SQL queries and bindings are filtered for security

## Telescope Setup

### 1. Development Environment
Telescope is enabled by default in non-production environments:
```bash
TELESCOPE_ENABLED=true
TELESCOPE_PATH=telescope
```

### 2. Production Environment (RESTRICTED)
Telescope is **disabled by default** in production for security. To enable:

```bash
# Basic settings
TELESCOPE_ENABLED=true
TELESCOPE_PRODUCTION_ENABLED=true
TELESCOPE_PATH=debug-console  # Obscured path
TELESCOPE_SAMPLING_RATE=0.1   # Only 10% of requests

# Security restrictions
TELESCOPE_ALLOWED_IPS="192.168.1.100,10.0.0.5"
TELESCOPE_ALLOWED_USERS="admin@yourcompany.com"
TELESCOPE_REQUIRED_PERMISSION=view-telescope
TELESCOPE_AUTO_DISABLE_HOURS=8  # Auto-disable after 8 hours

# Data retention
TELESCOPE_RETENTION_HOURS=24
TELESCOPE_RETENTION_LIMIT=1000
```

### 3. Database Migration
Run migrations to create Telescope tables:
```bash
php artisan migrate
```

## Security Features

### Sentry Security
- Filters sensitive blockchain data (private keys, API keys)
- Removes PII from error reports
- Environment-based configuration
- Configurable error filtering

### Telescope Security
- Disabled in production by default
- IP address restrictions
- User email restrictions
- Permission-based access control
- Auto-disable functionality
- Data sampling and retention limits
- Sensitive data filtering

### Blockchain-Specific Filtering
Both tools filter:
- Private keys
- Contract addresses (in sensitive contexts)
- API keys and tokens
- Wallet mnemonics
- Secret keys

## Production Authorization

### Telescope Gate
The system uses Laravel Gate to control access:
```php
Gate::define('viewTelescope', function ($user = null) {
    // Production: strict checks
    // Development: authenticated users
    // Local: all users
});
```

### Custom Authorization
Update `app/Providers/TelescopeServiceProvider.php` to modify authorization logic.

## Monitoring Commands

### Sentry Test
```bash
# Test Sentry integration
php artisan tinker
>>> throw new Exception('Test Sentry integration');
```

### Telescope Commands
```bash
# Check Telescope status
php artisan telescope:status

# Clear Telescope data
php artisan telescope:clear

# Prune old Telescope data
php artisan telescope:prune --hours=24
```

## Performance Impact

### Sentry
- Minimal impact with 10% sampling
- Asynchronous error reporting
- Configurable breadcrumbs

### Telescope
- Significant impact - use sparingly in production
- 10% sampling rate reduces overhead
- Auto-disable prevents long-term performance issues

## Alerts and Notifications

### Sentry Alerts
Configure alerts in Sentry dashboard for:
- Error rate increases
- New error types
- Performance degradation

### Telescope Monitoring
- Monitor data retention and cleanup
- Watch for unauthorized access attempts
- Track performance impact

## Best Practices

1. **Never enable Telescope in production without restrictions**
2. **Use IP restrictions and strong authentication**
3. **Monitor performance impact regularly**
4. **Set up automated data cleanup**
5. **Regularly review access logs**
6. **Keep sample rates low in production**
7. **Test error tracking in staging first**

## Troubleshooting

### Common Issues
1. **Telescope not accessible**: Check authorization and IP restrictions
2. **High memory usage**: Reduce sample rates or disable watchers
3. **Database growth**: Implement data retention policies
4. **Missing errors in Sentry**: Check DSN and network connectivity

### Support
- Sentry Documentation: https://docs.sentry.io/platforms/php/guides/laravel/
- Telescope Documentation: https://laravel.com/docs/telescope
EOF

    log_success "Deployment guide created: MONITORING_DEPLOYMENT_GUIDE.md"
}

# Main setup function
main() {
    log "Starting AI Blockchain Analytics monitoring setup..."
    
    # Create log file
    touch "$LOG_FILE"
    
    # Check Laravel first
    if check_laravel; then
        setup_sentry
        setup_telescope
    else
        log_warning "Laravel has issues, setting up configurations only"
        setup_sentry
    fi
    
    create_env_config
    create_deployment_guide
    
    log_success "Monitoring setup completed!"
    echo
    log "Next steps:"
    echo "1. Copy configuration from .env.monitoring.example to your .env file"
    echo "2. Set up your Sentry DSN at https://sentry.io"
    echo "3. Configure production restrictions in .env"
    echo "4. Run database migrations: php artisan migrate"
    echo "5. Review MONITORING_DEPLOYMENT_GUIDE.md"
    echo
    log "Setup log: $LOG_FILE"
}

# Run main function
main "$@"