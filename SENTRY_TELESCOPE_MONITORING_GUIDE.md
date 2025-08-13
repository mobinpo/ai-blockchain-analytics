# = Sentry + Laravel Telescope Monitoring Setup Guide

Complete monitoring setup for AI Blockchain Analytics with production-safe configurations.

## =Ê Overview

This setup provides comprehensive monitoring with:
- **Sentry** for error tracking, performance monitoring, and alerting
- **Laravel Telescope** for local debugging with production restrictions
- **Production-safe configurations** that automatically adjust based on environment
- **Security-first approach** with access controls and data sampling

---

## =€ Quick Setup

### 1. Environment Configuration

Add to your `.env` file:

```env
# --- Sentry Configuration ---
SENTRY_LARAVEL_DSN=https://your-dsn@o123456.ingest.sentry.io/123456
SENTRY_ENVIRONMENT=production
SENTRY_RELEASE=v1.0.0
SENTRY_TRACES_SAMPLE_RATE=0.05
SENTRY_PROFILES_SAMPLE_RATE=0.01
SENTRY_SEND_DEFAULT_PII=false

# --- Telescope Configuration ---
TELESCOPE_ENABLED=false
TELESCOPE_FORCE_ENABLE=false
TELESCOPE_ALLOWED_EMAILS=admin@example.com,dev@example.com
```

### 2. Run Database Migrations

```bash
# Install Telescope database tables
php artisan telescope:install
php artisan migrate

# Clean up any existing data
php artisan telescope:clear
```

### 3. Test the Setup

```bash
# Check Telescope status
php artisan telescope:manage status

# Test Sentry integration (will send test error)
php artisan sentry:test

# View current monitoring configuration
php artisan config:show telescope
php artisan config:show sentry
```

---

## =' Detailed Configuration

### Sentry Setup

#### Production Configuration
- **Sample Rate**: 10% of errors captured to reduce noise
- **Traces Sample Rate**: 5% of performance traces
- **Profiles Sample Rate**: 1% for profiling data
- **PII Protection**: No personal data sent to Sentry
- **Transaction Filtering**: Health checks and static assets ignored

#### Key Features
```php
// Automatic environment-based sampling
'sample_rate' => app()->isProduction() ? 0.1 : 1.0,

// Performance monitoring with reasonable limits
'traces_sample_rate' => app()->isProduction() ? 0.05 : 0.2,

// Filtered transactions to reduce noise
'ignore_transactions' => [
    '/up', '/telescope*', '/api/health', '/metrics',
    '*.css', '*.js', '*.ico', '*.png', // Static assets
],
```

### Telescope Setup

#### Production Safety Features
1. **Auto-disabled in production** unless explicitly enabled
2. **Custom authorization middleware** with admin-only access
3. **Limited data collection** in production (exceptions, failed jobs, slow queries only)
4. **Temporary enable/disable** via artisan commands
5. **Data filtering and tagging** for security

#### Authorization System
```php
// Custom middleware: app/Http/Middleware/TelescopeAuthorize.php
// - Blocks access in production by default
// - Allows admin users only when force-enabled
// - Supports email whitelist for authorized access
```

#### Production Data Filtering
```php
// Only critical data recorded in production
Telescope::filter(function ($entry) {
    return in_array($entry->type, [
        'exception',      // Errors and exceptions
        'failed_job',     // Failed background jobs
        'slow_query',     // Performance issues
    ]);
});
```

---

## =à Management Commands

### Telescope Management

```bash
# Check current status
php artisan telescope:manage status

# Temporarily enable in production (1 hour)
php artisan telescope:manage enable --force

# Disable telescope
php artisan telescope:manage disable

# Clean old data (keep last 24 hours)
php artisan telescope:manage clean --hours=24

# Show usage statistics
php artisan telescope:manage stats
```

### Standard Laravel Telescope Commands

```bash
# Install/reinstall telescope
php artisan telescope:install

# Clear all telescope data
php artisan telescope:clear

# Prune old entries (7 days default)
php artisan telescope:prune --hours=168

# Pause recording
php artisan telescope:pause

# Resume recording
php artisan telescope:resume
```

---

## < Environment-Specific Behavior

### Development Environment
- **Telescope**:  Fully enabled with all watchers
- **Sentry**:  100% error capture, 20% trace sampling
- **Access**: = Available to all authenticated users
- **Data Retention**: =Ú Full data collection

### Staging Environment
- **Telescope**:   Enabled with restricted access
- **Sentry**:  50% error capture, 10% trace sampling
- **Access**: = Admin users only
- **Data Retention**: =Ê Limited data collection

### Production Environment
- **Telescope**: = Disabled by default
- **Sentry**:  10% error capture, 5% trace sampling
- **Access**: =« Force-enable required
- **Data Retention**: <¯ Critical data only

---

## =È Monitoring Dashboard Access

### Sentry Dashboard
- **URL**: `https://sentry.io/organizations/your-org/projects/`
- **Features**: Error tracking, performance monitoring, alerts, releases
- **Team Access**: Configure team access in Sentry settings

### Telescope Dashboard
- **Local Development**: `http://localhost:8000/telescope`
- **Production**: `https://yourdomain.com/telescope` (restricted)
- **Access Control**: Email whitelist or admin role required

---

## = Security Features

### Production Security
1. **Telescope Access Control**:
   - Disabled by default in production
   - Custom middleware with email whitelist
   - Temporary enable functionality (auto-expires)
   - Admin role verification

2. **Sentry Data Protection**:
   - PII filtering enabled
   - Transaction sampling to reduce data volume
   - Static asset filtering
   - Environment-specific configuration

3. **Data Retention**:
   - Automatic pruning of old Telescope data
   - Production data filtering (critical events only)
   - Configurable retention periods

### Access Control Examples

```bash
# Add authorized emails to environment
TELESCOPE_ALLOWED_EMAILS=admin@company.com,dev@company.com,security@company.com

# Enable for specific session (1 hour expiry)
php artisan telescope:manage enable --force

# Check who has access
php artisan telescope:manage status
```

---

## =¨ Alerting & Notifications

### Sentry Alerts

Configure alerts in Sentry dashboard for:
- **High error rate** (>5 errors/minute)
- **New error types** (first occurrence)
- **Performance degradation** (response time >2s)
- **Failed deployments** (errors after release)

### Telescope Monitoring

```bash
# Set up cron job for automatic cleanup
# Add to your crontab:
0 2 * * * php /path/to/artisan telescope:prune --hours=168

# Weekly statistics report
0 9 * * 1 php /path/to/artisan telescope:manage stats | mail -s "Weekly Telescope Report" admin@company.com
```

---

## =Ê Performance Optimization

### Telescope Performance Tips

1. **Production Filtering**:
   ```php
   // Only record what you need
   'watchers' => [
       Watchers\ExceptionWatcher::class => true,
       Watchers\JobWatcher::class => true,
       Watchers\QueryWatcher::class => env('APP_DEBUG', false),
       Watchers\RequestWatcher::class => !app()->isProduction(),
   ],
   ```

2. **Data Retention**:
   ```bash
   # Daily cleanup (keep 24 hours in production)
   php artisan telescope:prune --hours=24
   
   # Weekly cleanup (keep 7 days in staging)
   php artisan telescope:prune --hours=168
   ```

3. **Queue Processing**:
   ```env
   # Process telescope data in background
   TELESCOPE_QUEUE_CONNECTION=redis
   TELESCOPE_QUEUE=telescope
   ```

### Sentry Performance Tips

1. **Sample Rate Optimization**:
   - Production: 5-10% to balance coverage vs volume
   - Staging: 20-50% for comprehensive testing
   - Development: 100% for full debugging

2. **Release Tracking**:
   ```env
   # Track deployments for better error attribution
   SENTRY_RELEASE=${CI_COMMIT_SHA:-manual-deploy}
   ```

---

## = Troubleshooting

### Common Issues

#### Telescope Not Loading
```bash
# Check configuration
php artisan telescope:manage status

# Verify database tables
php artisan migrate --path=database/migrations/telescope

# Clear cache and config
php artisan config:clear
php artisan cache:clear
```

#### Sentry Not Receiving Events
```bash
# Test Sentry connection
php artisan sentry:test

# Check DSN configuration
php artisan config:show sentry.dsn

# Verify network connectivity
curl -I https://sentry.io
```

#### Production Access Issues
```bash
# Check authorization middleware
php artisan route:list | grep telescope

# Verify environment variables
printenv | grep TELESCOPE

# Check logs for authorization failures
tail -f storage/logs/laravel.log | grep telescope
```

### Debug Commands

```bash
# Show current telescope configuration
php artisan tinker
>>> config('telescope')

# Test telescope recording
>>> Telescope::recordException(new \Exception('Test exception'))

# Check sentry configuration
>>> config('sentry')
```

---

## =€ Deployment Integration

### Kubernetes Configuration
- Telescope disabled by default in production manifests
- Sentry DSN configured via secrets
- Environment-specific sampling rates

### ECS Configuration
- Task definitions include monitoring environment variables
- Secrets Manager integration for sensitive keys
- Production-safe defaults

### CI/CD Integration

```yaml
# GitHub Actions example
- name: Setup Monitoring
  env:
    SENTRY_DSN: ${{ secrets.SENTRY_DSN }}
    TELESCOPE_ENABLED: false
  run: |
    php artisan config:cache
    php artisan telescope:manage status
```

---

## =Ë Checklist

### Initial Setup
- [ ] Install Sentry and Telescope packages
- [ ] Configure environment variables
- [ ] Run database migrations
- [ ] Set up authorization middleware
- [ ] Test both services

### Production Deployment
- [ ] Verify Telescope is disabled by default
- [ ] Configure Sentry sampling rates
- [ ] Set up authorized user emails
- [ ] Test production access restrictions
- [ ] Configure alerting rules

### Ongoing Maintenance
- [ ] Set up automated data pruning
- [ ] Monitor disk usage
- [ ] Review access logs
- [ ] Update authorized user lists
- [ ] Review and adjust sampling rates

---

## =¡ Best Practices

### Development
- Use full Telescope access for debugging
- Enable all watchers for comprehensive insights
- Regular data cleanup to maintain performance

### Staging
- Mirror production restrictions
- Use for testing monitoring configurations
- Validate alert thresholds

### Production
- Minimal Telescope usage (emergency debugging only)
- Focus on error tracking and performance monitoring
- Regular security audits of access logs
- Automated alerting for critical issues

This monitoring setup provides comprehensive observability while maintaining security and performance in production environments. =€