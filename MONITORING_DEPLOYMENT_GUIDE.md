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
