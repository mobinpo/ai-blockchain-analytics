<?php

namespace App\Console\Commands;

use App\Services\LinkAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class LinkAuditCommand extends Command
{
    protected $signature = 'link:audit
                          {--base= : Base URL for testing (default: http://localhost:8000)}
                          {--apply-fixes : Automatically apply suggested fixes}
                          {--depth=2 : Maximum crawl depth}
                          {--include-auth : Include authentication and test protected pages}
                          {--exclude= : Comma-separated list of exclusion patterns}
                          {--write-redirects : Write redirect mappings for high-confidence matches}
                          {--login-email= : Email for authentication (if include-auth is used)}
                          {--login-password= : Password for authentication (if include-auth is used)}
                          {--timeout=30 : Timeout in seconds for each request}
                          {--dusk-only : Run only the browser audit (skip static analysis)}
                          {--static-only : Run only the static analysis (skip browser audit)}
                          {--report-only : Generate report from existing findings}';

    protected $description = 'Audit your Laravel application for broken links and suggest fixes';

    protected LinkAuditor $auditor;

    public function __construct(LinkAuditor $auditor)
    {
        parent::__construct();
        $this->auditor = $auditor;
    }

    public function handle(): int
    {
        $this->displayHeader();

        // Validate options
        if (!$this->validateOptions()) {
            return Command::FAILURE;
        }

        // Set up environment
        $this->setupEnvironment();

        try {
            if ($this->option('report-only')) {
                return $this->generateReportOnly();
            }

            if ($this->option('static-only')) {
                return $this->runStaticAnalysisOnly();
            }

            if ($this->option('dusk-only')) {
                return $this->runBrowserAuditOnly();
            }

            // Run complete audit
            return $this->runCompleteAudit();

        } catch (\Exception $e) {
            $this->error("❌ Audit failed: {$e->getMessage()}");
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    protected function displayHeader(): void
    {
        $this->info('
╔══════════════════════════════════════════════════════════════╗
║                    🔍 Link Auditor                          ║
║                                                              ║
║  Crawl • Analyze • Fix • Report                             ║
║  Find and fix broken links in your Laravel application      ║
╚══════════════════════════════════════════════════════════════╝
        ');
    }

    protected function validateOptions(): bool
    {
        // Check if Dusk is available for browser testing
        if (!$this->option('static-only') && !class_exists('Laravel\Dusk\DuskServiceProvider')) {
            $this->warn('⚠️  Laravel Dusk is not installed. Browser auditing will be skipped.');
            $this->line('   Install with: composer require --dev laravel/dusk');
            
            if (!$this->confirm('Continue with static analysis only?')) {
                return false;
            }
        }

        // Validate base URL
        $baseUrl = $this->getBaseUrl();
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->error("❌ Invalid base URL: {$baseUrl}");
            return false;
        }

        // Check if authentication is properly configured
        if ($this->option('include-auth')) {
            $email = $this->option('login-email') ?: config('link_audit.login.email');
            $password = $this->option('login-password') ?: config('link_audit.login.password');
            
            if (!$email || !$password) {
                $this->error('❌ Authentication credentials required when using --include-auth');
                $this->line('   Use --login-email and --login-password options');
                return false;
            }
        }

        return true;
    }

    protected function setupEnvironment(): void
    {
        // Update config with command options
        config([
            'link_audit.base_url' => $this->getBaseUrl(),
            'link_audit.crawl.max_depth' => $this->option('depth'),
            'link_audit.crawl.timeout_seconds' => $this->option('timeout'),
        ]);

        if ($this->option('include-auth')) {
            config([
                'link_audit.login.email' => $this->option('login-email') ?: config('link_audit.login.email'),
                'link_audit.login.password' => $this->option('login-password') ?: config('link_audit.login.password'),
            ]);
        }

        // Add custom exclusions
        if ($this->option('exclude')) {
            $customExclusions = explode(',', $this->option('exclude'));
            $currentExclusions = config('link_audit.exclusions.route_patterns', []);
            config(['link_audit.exclusions.route_patterns' => array_merge($currentExclusions, $customExclusions)]);
        }

        // Set environment variables for Dusk
        if (!$this->option('static-only')) {
            putenv('LINK_AUDIT_BASE_URL=' . $this->getBaseUrl());
            putenv('LINK_AUDIT_INCLUDE_AUTH=' . ($this->option('include-auth') ? 'true' : 'false'));
        }
    }

    protected function runCompleteAudit(): int
    {
        $this->line("🚀 Starting complete link audit...\n");

        $startTime = microtime(true);
        
        // Run the audit
        $results = $this->auditor->audit([
            'apply_fixes' => $this->option('apply-fixes'),
            'write_redirects' => $this->option('write-redirects'),
            'include_auth' => $this->option('include-auth'),
        ]);

        // Run browser audit with Dusk
        if (!$this->option('static-only')) {
            $this->info("🌐 Running browser audit...");
            $duskResult = $this->runDuskTest();
            
            if ($duskResult !== 0) {
                $this->warn("⚠️  Browser audit encountered issues (exit code: {$duskResult})");
            } else {
                $this->info("✅ Browser audit completed successfully");
            }
        }

        // Write redirects if requested
        if ($this->option('write-redirects')) {
            $this->writeRedirectMappings();
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        // Display results
        $this->displayResults($results, $duration);

        // Return appropriate exit code
        $totalIssues = $results['static_issues'] + $results['browser_issues'];
        return $totalIssues > 0 && !$this->option('apply-fixes') ? Command::FAILURE : Command::SUCCESS;
    }

    protected function runStaticAnalysisOnly(): int
    {
        $this->line("🔍 Running static analysis only...\n");

        $this->auditor->buildRouteIndex();
        $staticCount = $this->auditor->scanBladeTemplates();
        $this->auditor->generateSuggestions();

        if ($this->option('apply-fixes')) {
            $fixCount = $this->auditor->applyFixes();
            $this->info("🔧 Applied {$fixCount} fixes");
        }

        $reportPath = $this->auditor->generateReport();
        
        $this->info("📊 Analysis complete!");
        $this->line("   Static issues found: {$staticCount}");
        $this->line("   Report: {$reportPath}");

        return $staticCount > 0 && !$this->option('apply-fixes') ? Command::FAILURE : Command::SUCCESS;
    }

    protected function runBrowserAuditOnly(): int
    {
        $this->line("🌐 Running browser audit only...\n");

        $this->auditor->buildRouteIndex();
        $duskResult = $this->runDuskTest();
        $this->auditor->generateSuggestions();

        if ($this->option('apply-fixes')) {
            $fixCount = $this->auditor->applyFixes();
            $this->info("🔧 Applied {$fixCount} fixes");
        }

        $reportPath = $this->auditor->generateReport();
        
        $this->info("📊 Browser audit complete!");
        $this->line("   Report: {$reportPath}");

        return $duskResult;
    }

    protected function generateReportOnly(): int
    {
        $this->line("📊 Generating report from existing findings...\n");

        $reportPath = $this->auditor->generateReport();
        
        $this->info("✅ Report generated: {$reportPath}");

        return Command::SUCCESS;
    }

    protected function runDuskTest(): int
    {
        $this->line("   Running Dusk test suite...");

        // Check if Dusk is properly configured
        if (!file_exists(base_path('tests/Browser/LinkAuditTest.php'))) {
            $this->warn("   ⚠️  LinkAuditTest.php not found. Browser audit will be skipped.");
            return Command::SUCCESS;
        }

        // Run the Dusk test
        $result = Artisan::call('dusk', [
            '--filter' => 'LinkAuditTest',
            '--stop-on-failure' => false,
        ]);

        if ($result === 0) {
            $this->line("   ✅ Browser audit completed");
        } else {
            $this->line("   ⚠️  Browser audit completed with issues");
        }

        return $result;
    }

    protected function writeRedirectMappings(): void
    {
        $suggestions = $this->auditor->getSuggestions();
        $redirects = [];

        foreach ($suggestions as $suggestion) {
            if ($suggestion['confidence'] >= config('link_audit.redirects.confidence_threshold', 0.9)) {
                if (isset($suggestion['route_suggestion'])) {
                    $path = parse_url($suggestion['link_href'] ?? '', PHP_URL_PATH);
                    if ($path) {
                        $redirects[$path] = $suggestion['route_suggestion']['name'];
                    }
                }
            }
        }

        if (!empty($redirects)) {
            $configPath = config('link_audit.redirects.config_file');
            $currentRedirects = include $configPath;
            $mergedRedirects = array_merge($currentRedirects, $redirects);

            $content = "<?php\n\nreturn " . var_export($mergedRedirects, true) . ";\n";
            file_put_contents($configPath, $content);

            $this->info("📝 Wrote " . count($redirects) . " redirect mappings to {$configPath}");
        }
    }

    protected function displayResults(array $results, float $duration): void
    {
        $this->newLine();
        $this->info("📊 Audit Results");
        $this->line("═══════════════════════════════════════════════════════");
        $this->line("   Duration: {$duration}s");
        $this->line("   Static issues: {$results['static_issues']}");
        $this->line("   Browser issues: {$results['browser_issues']}");
        $this->line("   Suggestions: {$results['suggestions']}");
        $this->line("   Report: {$results['report_path']}");
        $this->newLine();

        if ($results['static_issues'] + $results['browser_issues'] === 0) {
            $this->info("🎉 No issues found! Your application links are healthy.");
        } else {
            $this->warn("⚠️  Issues found. Review the report for details.");
            
            if (!$this->option('apply-fixes')) {
                $this->line("   Run with --apply-fixes to automatically fix issues");
            }
        }

        $this->newLine();
        $this->line("🔗 Open report: file://{$results['report_path']}");
    }

    protected function getBaseUrl(): string
    {
        return $this->option('base') ?: config('link_audit.base_url', 'http://localhost:8000');
    }
}