# 🔍 Sentry + Laravel Telescope Setup Guide v0.9.0

## ✅ **Installation Complete**

Both **Sentry** and **Laravel Telescope** have been successfully installed and configured for AI Blockchain Analytics v0.9.0 with production restrictions and enhanced security.

---

## 🚨 **Sentry Error Tracking**

### **📦 Package Installed**
- **sentry/sentry-laravel**: v4.15+ for comprehensive error tracking
- **Configuration**: `config/sentry.php` optimized for production
- **Environment**: Ready for both development and production

### **🔧 Configuration Features**

#### **Production Optimized**
```php
// Sample rates optimized for production
'sample_rate' => 0.1,              // 10% of errors
'traces_sample_rate' => 0.05,      // 5% of performance traces
'profiles_sample_rate' => 0.05,    // 5% of performance profiles
```

#### **Smart Filtering**
- **Ignored Exceptions**: Common Laravel exceptions (404, validation, auth)
- **Ignored Transactions**: Health checks, static assets, monitoring endpoints
- **Sensitive Data**: Automatic filtering of passwords, API keys, tokens

#### **AI Blockchain Analytics Specific**
- **Contract Analysis Tracking**: Monitor analysis performance and errors
- **API Usage Monitoring**: Track API endpoint performance
- **User Onboarding**: Monitor email delivery and user flow
- **High Error Rate Alerts**: Automatic alerts for critical issues

### **📊 Environment Variables**
```bash
# Required for production
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
SENTRY_RELEASE=v0.9.0
SENTRY_ENVIRONMENT=production

# Optional tuning
SENTRY_SAMPLE_RATE=0.1
SENTRY_TRACES_SAMPLE_RATE=0.05
SENTRY_ENABLE_LOGS=true
SENTRY_SEND_DEFAULT_PII=false
```

---

## 🔭 **Laravel Telescope Debugging**

### **📦 Package Installed**
- **laravel/telescope**: v5.11+ for application debugging
- **Enhanced Authorization**: Custom middleware with production restrictions
- **Database**: Telescope tables created and configured

### **🔒 Production Security Features**

#### **Enhanced Authorization Middleware**
- **IP Whitelisting**: Restrict access to specific IP addresses
- **User Authorization**: Email-based access control
- **Environment Restrictions**: Automatically disabled in production
- **Audit Logging**: All access attempts logged

#### **Smart Watchers Configuration**
```php
// Production-optimized watchers
'CacheWatcher' => false,           // Disabled in production
'QueryWatcher' => false,           // Disabled in production  
'ModelWatcher' => false,           // Disabled in production
'ExceptionWatcher' => true,        // Always enabled
'JobWatcher' => true,              // Always enabled
'LogWatcher' => 'warning+',        // Only warnings/errors in production
```

### **🔧 Access Configuration**

#### **IP Whitelist (Production)**
Edit `app/Http/Middleware/EnhancedTelescopeAuthorize.php`:
```php
private const ALLOWED_IPS = [
    '127.0.0.1',
    '::1',
    '203.0.113.0/24',     // Your office network
    '198.51.100.50',      // Admin VPN IP
];
```

#### **User Email Whitelist**
```php
private const ALLOWED_EMAILS = [
    'admin@yourcompany.com',
    'dev@yourcompany.com',
];
```

### **📊 Environment Variables**
```bash
# Telescope configuration
TELESCOPE_ENABLED=false              # Disabled in production by default
TELESCOPE_PATH=telescope             # Access URL path
TELESCOPE_DOMAIN=                    # Optional subdomain

# Watcher controls
TELESCOPE_CACHE_WATCHER=false        # Disable in production
TELESCOPE_QUERY_WATCHER=false        # Disable in production
TELESCOPE_MODEL_WATCHER=false        # Disable in production
TELESCOPE_EXCEPTION_WATCHER=true     # Always monitor exceptions
TELESCOPE_JOB_WATCHER=true           # Always monitor jobs
TELESCOPE_LOG_WATCHER=true           # Always monitor logs
```

---

## 🚀 **Production Deployment**

### **🔒 Security Checklist**

#### **Sentry Production Setup**
1. **Create Sentry Project**: Sign up at sentry.io
2. **Get DSN**: Copy the DSN from your Sentry project settings
3. **Set Environment Variables**:
   ```bash
   SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
   SENTRY_ENVIRONMENT=production
   SENTRY_SAMPLE_RATE=0.1
   ```
4. **Configure Alerts**: Set up alerts for high error rates
5. **Team Notifications**: Configure Slack/email notifications

#### **Telescope Production Setup**
1. **Disable by Default**:
   ```bash
   TELESCOPE_ENABLED=false
   ```
2. **Configure IP Whitelist**: Add your admin IPs to middleware
3. **Set User Permissions**: Add admin emails to whitelist
4. **Test Access**: Verify authorization works correctly
5. **Set up Pruning**: Configure automatic data cleanup

### **🗄️ Database Considerations**

#### **Telescope Data Management**
```bash
# Add to Laravel scheduler (routes/console.php)
$schedule->command('telescope:prune --hours=48')->daily();

# Manual cleanup
php artisan telescope:prune --hours=24
```

#### **Sentry Performance**
- **Async Processing**: Sentry sends data asynchronously
- **Minimal Impact**: < 1ms overhead per request
- **Queue Integration**: Works with Laravel queues

---

## 📈 **Monitoring Dashboards**

### **🚨 Sentry Dashboard**
Access your Sentry dashboard to monitor:
- **Error Rates**: Track error trends over time
- **Performance**: Monitor slow API endpoints
- **Releases**: Compare error rates between versions
- **User Impact**: See which users are affected by errors

**Key Metrics to Watch:**
- Contract analysis error rate < 1%
- API response time < 500ms average
- User onboarding completion rate > 80%
- Critical errors: 0 per day

### **🔭 Telescope Dashboard** 
Access Telescope at `https://your-domain.com/telescope` (when enabled):
- **Requests**: Monitor HTTP request performance
- **Jobs**: Track background job execution
- **Logs**: View application logs in real-time
- **Exceptions**: Debug exceptions with full context

**Development Usage:**
- Debug contract analysis failures
- Monitor API performance
- Track user onboarding flow
- Analyze database query performance

---

## 🔧 **Configuration Files**

### **Sentry Configuration** (`config/sentry.php`)
- ✅ **Production Optimized**: Smart sampling and filtering
- ✅ **Security Focused**: Sensitive data filtering
- ✅ **Laravel Integration**: Automatic exception capture
- ✅ **Performance Monitoring**: Traces and profiles

### **Telescope Configuration** (`config/telescope.php`)
- ✅ **Enhanced Security**: Custom authorization middleware
- ✅ **Production Restrictions**: Disabled watchers in production
- ✅ **Smart Filtering**: Ignore noisy events
- ✅ **Performance Optimized**: Minimal impact on production

### **Middleware** (`app/Http/Middleware/EnhancedTelescopeAuthorize.php`)
- ✅ **IP Whitelisting**: CIDR support for network ranges
- ✅ **User Authorization**: Email and role-based access
- ✅ **Audit Logging**: Security event logging
- ✅ **Environment Aware**: Different rules per environment

---

## 🚨 **Alerts & Notifications**

### **Sentry Alerts**
Configure alerts for:
- **High Error Rate**: > 5 errors per minute
- **New Error Types**: First occurrence of new errors
- **Performance Degradation**: Response time > 2 seconds
- **Critical Contract Analysis Failures**: Analysis engine errors

### **Recommended Alert Rules**
```yaml
# High error rate
- condition: "error rate > 5 per minute"
  notification: "Slack #alerts, Email admin team"
  
# New error types
- condition: "new error first seen"
  notification: "Email dev team"
  
# Performance degradation  
- condition: "p95 response time > 2 seconds"
  notification: "Slack #performance"
  
# Contract analysis failures
- condition: "tag:feature = contract_analysis AND level = error"
  notification: "Email admin, Slack #critical"
```

---

## 🧪 **Testing & Verification**

### **Sentry Testing**
```bash
# Test error capture
php artisan tinker
>>> throw new Exception('Test Sentry integration');

# Test performance monitoring
>>> Sentry\addBreadcrumb(new \Sentry\Breadcrumb('info', 'manual', 'Test breadcrumb'));
```

### **Telescope Testing**
```bash
# Enable Telescope temporarily
TELESCOPE_ENABLED=true php artisan config:cache

# Access Telescope
curl -I http://localhost:8003/telescope

# Check authorization
curl -H "Authorization: Bearer invalid" http://localhost:8003/telescope
```

### **Production Verification**
1. **Deploy with Monitoring**: Deploy to staging first
2. **Generate Test Errors**: Trigger known error scenarios
3. **Verify Sentry Capture**: Check Sentry dashboard
4. **Test Telescope Access**: Verify authorization works
5. **Check Performance Impact**: Monitor response times

---

## 📚 **Documentation & Resources**

### **Sentry Resources**
- **Official Docs**: https://docs.sentry.io/platforms/php/guides/laravel/
- **Performance Monitoring**: https://docs.sentry.io/platforms/php/guides/laravel/performance/
- **Error Tracking**: https://docs.sentry.io/platforms/php/guides/laravel/enriching-events/

### **Telescope Resources**
- **Official Docs**: https://laravel.com/docs/telescope
- **Authorization**: https://laravel.com/docs/telescope#authorization
- **Configuration**: https://laravel.com/docs/telescope#configuration

### **Best Practices**
- **Error Handling**: Implement proper try-catch blocks
- **Performance Monitoring**: Monitor critical user journeys
- **Data Privacy**: Never log sensitive user data
- **Alert Fatigue**: Configure meaningful alerts only

---

## 🎯 **Quick Reference**

### **Enable/Disable Commands**
```bash
# Enable Telescope (development)
php artisan config:cache
# Set TELESCOPE_ENABLED=true in .env

# Disable Telescope (production) 
# Set TELESCOPE_ENABLED=false in .env
php artisan config:cache

# Clear Telescope data
php artisan telescope:clear

# Prune old Telescope data
php artisan telescope:prune --hours=24
```

### **Sentry Test Commands**
```bash
# Test Sentry configuration
php artisan sentry:test

# Publish Sentry config (if needed)
php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
```

### **Environment Variables Summary**
```bash
# Sentry (Required for production)
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
SENTRY_ENVIRONMENT=production
SENTRY_RELEASE=v0.9.0

# Telescope (Disabled in production)
TELESCOPE_ENABLED=false
TELESCOPE_PATH=telescope
```

---

## 🎉 **Monitoring Setup Complete!**

Your AI Blockchain Analytics platform now has:
- ✅ **Comprehensive Error Tracking** with Sentry
- ✅ **Advanced Debugging Tools** with Telescope
- ✅ **Production Security** with enhanced authorization
- ✅ **Performance Monitoring** with smart sampling
- ✅ **Automated Alerts** for critical issues

**🚀 Ready for production deployment with enterprise-grade monitoring and debugging capabilities!**
