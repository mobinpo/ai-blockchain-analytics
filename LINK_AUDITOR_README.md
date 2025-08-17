# Link Auditor & Auto-Fixer 🔍

> **Complete Link Auditing System for Laravel Applications**
>
> Crawl your Laravel app in a real browser, find every broken/non-working link or button, map each to the closest valid route, propose code fixes, and re-test with Dusk.

## ✨ Features

- 🔍 **Real Browser Crawling** - Uses Laravel Dusk to test in actual Chrome browser
- 🧠 **Intelligent Fix Suggestions** - AI-powered route matching with confidence scoring
- 🔧 **Auto-Apply Fixes** - Safely patch Blade templates with backup
- 📊 **Rich HTML Reports** - Beautiful interactive reports with screenshots
- 🛡️ **Legacy Redirects** - Non-breaking fallback middleware for old URLs
- 🔐 **Auth Support** - Test protected pages with login simulation

## 🚀 Quick Start

### 1. Install Dependencies
```bash
# Laravel Dusk is already installed and configured!
composer install
```

### 2. Basic Usage

```bash
# Quick audit of your application
php artisan link:audit

# Audit and auto-fix high-confidence issues
php artisan link:audit --apply-fixes

# Test with authentication
php artisan link:audit --include-auth --login-email=admin@example.com --login-password=secret

# Custom base URL (staging/production)
php artisan link:audit --base=https://staging.example.com
```

### 3. View Results
Open the generated HTML report at `storage/app/link-audit/report.html`

## 📋 Command Examples

```bash
# Complete audit with all features
php artisan link:audit --base=http://localhost:8000 --apply-fixes

# Authentication testing
php artisan link:audit --include-auth --depth=3

# Custom exclusions
php artisan link:audit --exclude="horizon/*,api/*,admin/delete/*"

# Generate redirects for legacy URLs
php artisan link:audit --write-redirects

# Static analysis only (fast)
php artisan link:audit --static-only

# Browser testing only
php artisan link:audit --dusk-only

# Generate report from existing data
php artisan link:audit --report-only
```

## ⚙️ Configuration

### Environment Variables
```env
# .env
LINK_AUDIT_BASE_URL=http://localhost:8000
LINK_AUDIT_MAX_DEPTH=2
LINK_AUDIT_TIMEOUT=30

# Optional: For testing protected pages
LINK_AUDIT_LOGIN_EMAIL=admin@example.com
LINK_AUDIT_LOGIN_PASSWORD=password
```

### Advanced Configuration
Edit `config/link_audit.php` for fine-tuning:
- Exclusion patterns
- Confidence thresholds  
- Fuzzy matching weights
- Storage locations

## 🛠️ How It Works

1. **Route Discovery** - Builds index of all valid GET routes
2. **Static Analysis** - Scans Blade templates for obvious issues
3. **Browser Crawling** - Navigates pages with real Chrome browser
4. **Intelligent Matching** - Uses Jaro-Winkler fuzzy matching for route suggestions
5. **Safe Auto-Fixing** - Creates backups before applying patches
6. **Rich Reporting** - Generates interactive HTML reports with confidence scoring

## 🎯 What Gets Detected

### Static Issues
- `href="#"` or empty href attributes
- `href="javascript:void(0)"` placeholders
- Invalid `route('name')` calls
- Missing route definitions

### Browser Issues  
- 404 Not Found pages
- 500 Server Errors
- JavaScript navigation failures
- Broken AJAX endpoints
- Redirect loops

### Smart Suggestions
- Route name similarities (`pricing` → `pricing.index`)
- URI path matching (`/old-about` → `/about`)
- Link text analysis (`"Contact Us"` → `contact.create`)

## 📊 Report Features

- **Confidence Scoring** - High/Medium/Low fix reliability
- **Interactive Tabs** - Static, Browser, and Suggestions views
- **Copy Patches** - One-click code fix copying
- **Screenshots** - Visual documentation of failures
- **Diff Views** - Before/after code comparisons

## 🔧 Integration

### CI/CD Pipeline
```yaml
# .github/workflows/test.yml
- name: Link Audit
  run: php artisan link:audit --base=http://localhost:8000
```

### Cron Jobs
```bash
# Weekly automated audits
0 2 * * 0 cd /path/to/app && php artisan link:audit --apply-fixes
```

## 🚨 Safety Features

- **Backup System** - All files backed up before modification
- **Confidence Thresholds** - Only high-confidence fixes applied automatically
- **Rollback Support** - Restore from timestamped backups
- **Legacy Redirects** - Non-breaking fallback for old URLs
- **Scope Limiting** - Excludes destructive and external routes

## 📁 File Structure

```
├── app/
│   ├── Console/Commands/LinkAuditCommand.php
│   ├── Services/LinkAuditor.php
│   ├── Support/Fuzzy.php
│   └── Http/Middleware/LegacyRedirects.php
├── config/
│   ├── link_audit.php
│   └── redirects.php
├── tests/Browser/LinkAuditTest.php
├── resources/views/vendor/link-audit/report.blade.php
├── storage/app/link-audit/
│   ├── routes.json
│   ├── static-findings.json
│   ├── browser-findings/
│   ├── screenshots/
│   ├── backups/
│   └── report.html
└── docs/link-audit.md
```

## 🎉 Ready to Use!

The Link Auditor is now fully installed and configured. Start with a basic audit:

```bash
php artisan link:audit
```

Then open `storage/app/link-audit/report.html` to see your results!

---

For detailed documentation, see [`docs/link-audit.md`](docs/link-audit.md)