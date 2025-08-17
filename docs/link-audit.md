# Link Auditor & Auto-Fixer

A comprehensive Laravel package that crawls your application in a real browser, identifies broken links and navigation issues, and automatically suggests or applies fixes using intelligent route mapping.

## Features

🔍 **Complete Link Discovery**
- Static analysis of Blade templates
- Real browser crawling with Laravel Dusk
- JavaScript-rendered content support
- Protected page testing with authentication

🧠 **Intelligent Fix Suggestions**
- Fuzzy matching using Jaro-Winkler algorithm
- Route similarity scoring
- Context-aware suggestions based on link text
- Confidence scoring for automated fixes

🔧 **Automated Repairs**
- Safe file backup before modifications
- Blade template patching
- Legacy URL redirect generation
- Non-breaking fallback middleware

📊 **Rich Reporting**
- Interactive HTML reports
- CSV export capabilities
- Screenshot capture for broken pages
- Confidence distribution analysis

## Installation

### 1. Install Laravel Dusk (if not already installed)

```bash
composer require --dev laravel/dusk
php artisan dusk:install
```

### 2. Configure Environment

Add these variables to your `.env` file:

```env
# Link Auditor Configuration
LINK_AUDIT_BASE_URL=http://localhost:8000
LINK_AUDIT_MAX_DEPTH=2
LINK_AUDIT_TIMEOUT=30

# Optional: Authentication for protected pages
LINK_AUDIT_LOGIN_EMAIL=admin@example.com
LINK_AUDIT_LOGIN_PASSWORD=password
```

### 3. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=link-audit-config
```

## Quick Start

### Basic Audit

Run a basic link audit of your application:

```bash
php artisan link:audit
```

### Complete Audit with Auto-Fix

Audit and automatically apply high-confidence fixes:

```bash
php artisan link:audit --apply-fixes
```

### Authenticated Page Testing

Test protected pages by logging in:

```bash
php artisan link:audit --include-auth --login-email=admin@example.com --login-password=secret
```

### Custom Base URL

Test against a specific environment:

```bash
php artisan link:audit --base=https://staging.yourdomain.com
```

## Command Options

| Option | Description | Default |
|--------|-------------|---------|
| `--base=URL` | Base URL for testing | `http://localhost:8000` |
| `--apply-fixes` | Automatically apply suggested fixes | `false` |
| `--depth=N` | Maximum crawl depth | `2` |
| `--include-auth` | Test protected pages | `false` |
| `--exclude=patterns` | Comma-separated exclusion patterns | - |
| `--write-redirects` | Generate redirect mappings | `false` |
| `--timeout=N` | Request timeout in seconds | `30` |
| `--static-only` | Run only static analysis | `false` |
| `--dusk-only` | Run only browser audit | `false` |
| `--report-only` | Generate report from existing data | `false` |

## Advanced Usage

### Custom Exclusions

Exclude specific routes or patterns:

```bash
php artisan link:audit --exclude="admin/*,api/*,debug/*"
```

### Generate Redirects

Create redirect mappings for high-confidence matches:

```bash
php artisan link:audit --write-redirects --base=https://production.com
```

### Development Workflow

1. **Initial Audit**: `php artisan link:audit --static-only`
2. **Browser Testing**: `php artisan link:audit --dusk-only --include-auth`
3. **Auto-Fix**: `php artisan link:audit --apply-fixes`
4. **Verification**: `php artisan link:audit --report-only`

## Configuration

### Link Audit Configuration

Edit `config/link_audit.php`:

```php
return [
    'base_url' => env('LINK_AUDIT_BASE_URL', 'http://localhost:8000'),
    
    'exclusions' => [
        'route_patterns' => [
            'api/*',
            'horizon/*',
            'admin/delete/*',
        ],
        'external_domains' => true,
        'routes_with_params' => true,
    ],
    
    'suggestions' => [
        'minimum_score' => 0.82,
        'weights' => [
            'name_similarity' => 0.6,
            'uri_similarity' => 0.3,
            'link_text_similarity' => 0.1,
        ],
    ],
];
```

### Redirect Configuration

Manage legacy URL redirects in `config/redirects.php`:

```php
return [
    '/old-contact' => 'contact.index',
    '/about-us' => 'about',
    '/pricing-info' => 'pricing',
    '/blog/*' => 'posts.index',
];
```

## How It Works

### 1. Route Index Building

The system builds a comprehensive index of your application's routes:

- Filters GET/HEAD routes only
- Excludes API endpoints and parameterized routes
- Respects exclusion patterns
- Saves to `storage/app/link-audit/routes.json`

### 2. Static Analysis

Scans Blade templates for common issues:

- Empty or problematic `href` attributes (`#`, `javascript:void(0)`)
- Invalid `route()` function calls
- Missing route names
- Data attributes without handlers

### 3. Browser Crawling

Uses Laravel Dusk to:

- Navigate pages in a real browser
- Execute JavaScript and wait for completion
- Extract all navigational elements
- Test link destinations
- Capture screenshots of failures

### 4. Intelligent Matching

Applies fuzzy string matching to suggest fixes:

- **Jaro-Winkler similarity** for route names
- **URI pattern matching** for path similarities
- **Link text analysis** for contextual clues
- **Combined scoring** with configurable weights

### 5. Safe Auto-Fixing

When applying fixes:

- Creates timestamped backups in `storage/app/link-audit/backups/`
- Only modifies high-confidence matches (≥82% by default)
- Preserves file formatting and attributes
- Generates detailed diff logs

## Report Analysis

### Confidence Levels

- **High (≥90%)**: Safe for automatic application
- **Medium (70-89%)**: Review recommended
- **Low (<70%)**: Manual verification required

### Issue Types

- **Static Issues**: Found in Blade templates
- **Browser Issues**: Discovered during navigation
- **Suggestions**: Proposed fixes with confidence scores

### Interactive Features

- **Copy Patch**: One-click code fix copying
- **Screenshot Links**: Visual failure documentation
- **Confidence Filtering**: Focus on actionable items
- **Route Details**: Complete fix context

## Troubleshooting

### Common Issues

**Dusk not installed:**
```bash
composer require --dev laravel/dusk
php artisan dusk:install
```

**ChromeDriver issues:**
```bash
# Update ChromeDriver
php artisan dusk:chrome-driver

# Check Chrome version compatibility
google-chrome --version
```

**Authentication failures:**
- Verify login credentials in `.env`
- Check login URL configuration
- Ensure test user exists in database

**Memory issues with large applications:**
- Reduce crawl depth: `--depth=1`
- Use exclusion patterns: `--exclude="admin/*,api/*"`
- Run static analysis only: `--static-only`

### Debug Mode

Enable detailed logging:

```php
// config/link_audit.php
'debug' => true,
'verbose_logging' => true,
```

### Performance Tuning

For large applications:

```php
// config/link_audit.php
'crawl' => [
    'max_depth' => 1,
    'timeout_seconds' => 15,
    'wait_after_click' => 1000,
],

'exclusions' => [
    'route_patterns' => [
        'admin/*',
        'api/*',
        'horizon/*',
        'telescope/*',
    ],
],
```

## Integration

### CI/CD Pipeline

Add to your GitHub Actions workflow:

```yaml
- name: Run Link Audit
  run: |
    php artisan link:audit --base=http://localhost:8000
    if [ $? -ne 0 ]; then
      echo "Link audit failed. Check the report for broken links."
      exit 1
    fi
```

### Monitoring

Set up automated audits:

```bash
# Add to crontab for weekly audits
0 2 * * 0 cd /path/to/app && php artisan link:audit --apply-fixes > /var/log/link-audit.log 2>&1
```

### Custom Extensions

Extend the auditor with custom rules:

```php
// app/Services/CustomLinkAuditor.php
class CustomLinkAuditor extends LinkAuditor
{
    protected function shouldTestLink(string $href): bool
    {
        // Add custom link filtering logic
        return parent::shouldTestLink($href) && !str_contains($href, 'external.com');
    }
}
```

## API Reference

### LinkAuditor Service

```php
use App\Services\LinkAuditor;

$auditor = app(LinkAuditor::class);

// Build route index
$auditor->buildRouteIndex();

// Scan templates
$issues = $auditor->scanBladeTemplates();

// Generate suggestions
$auditor->generateSuggestions();

// Apply fixes
$fixCount = $auditor->applyFixes();

// Get results
$routes = $auditor->getRoutes();
$findings = $auditor->getStaticFindings();
$suggestions = $auditor->getSuggestions();
```

### Fuzzy Matching

```php
use App\Support\Fuzzy;

// Calculate similarity
$score = Fuzzy::jaroWinkler('pricing', 'price');

// Find best route match
$bestMatch = Fuzzy::findBestRoute('/old-path', $routes, 'Pricing Link');

// Combined route similarity
$score = Fuzzy::routeSimilarity(
    'old.name', '/old/path',
    'new.name', '/new/path',
    'Link Text'
);
```

### Legacy Redirects Middleware

```php
use App\Http\Middleware\LegacyRedirects;

// Add redirect programmatically
LegacyRedirects::addRedirect('/old-path', 'new.route');

// Remove redirect
LegacyRedirects::removeRedirect('/old-path');

// Get all redirects
$redirects = LegacyRedirects::getAllRedirects();
```

## Security Considerations

- **Backup Safety**: All files are backed up before modification
- **Scope Limiting**: Excludes destructive routes and external domains
- **Authentication**: Optional login testing with proper credential handling
- **Validation**: Route existence verification before suggestions
- **Logging**: Comprehensive audit trails for all changes

## Contributing

When extending the Link Auditor:

1. **Follow Laravel conventions**
2. **Add comprehensive tests**
3. **Update documentation**
4. **Consider backward compatibility**
5. **Test with various Laravel versions**

## License

This Link Auditor system is part of the Sentiment Shield project and follows the same licensing terms.