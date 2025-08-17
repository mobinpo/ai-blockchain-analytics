<?php

namespace App\Services;

use App\Support\Fuzzy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LinkAuditor
{
    protected array $config;
    protected string $basePath;
    protected Collection $routes;
    protected array $staticFindings = [];
    protected array $browserFindings = [];
    protected array $suggestions = [];

    public function __construct()
    {
        $this->config = config('link_audit');
        $this->basePath = $this->config['storage']['base_path'];
        $this->ensureDirectoriesExist();
    }

    /**
     * Run complete link audit process
     */
    public function audit(array $options = []): array
    {
        $this->output("🔍 Starting Link Audit...\n");

        // Step 1: Build route index
        $this->output("📋 Building route index...");
        $this->buildRouteIndex();
        $this->output(" ✅ Found {$this->routes->count()} valid routes\n");

        // Step 2: Static Blade scan
        $this->output("🔍 Scanning Blade templates...");
        $staticCount = $this->scanBladeTemplates();
        $this->output(" ✅ Found {$staticCount} potential issues\n");

        // Step 3: Browser audit will be handled by Dusk test
        $this->output("🌐 Browser audit will be performed by Dusk test\n");

        // Step 4: Generate suggestions
        $this->output("💡 Generating fix suggestions...");
        $this->generateSuggestions();
        $this->output(" ✅ Generated suggestions\n");

        // Step 5: Apply fixes if requested
        if ($options['apply_fixes'] ?? false) {
            $this->output("🔧 Applying fixes...");
            $fixCount = $this->applyFixes();
            $this->output(" ✅ Applied {$fixCount} fixes\n");
        }

        // Step 6: Generate report
        $this->output("📊 Generating report...");
        $reportPath = $this->generateReport();
        $this->output(" ✅ Report saved to {$reportPath}\n");

        return [
            'static_issues' => count($this->staticFindings),
            'browser_issues' => count($this->browserFindings),
            'suggestions' => count($this->suggestions),
            'report_path' => $reportPath,
        ];
    }

    /**
     * Build index of valid routes
     */
    public function buildRouteIndex(): void
    {
        $this->routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'action' => $route->getActionName(),
                'middleware' => $route->gatherMiddleware(),
                'wantsJson' => str_starts_with($route->uri(), 'api/'),
                'hasParams' => str_contains($route->uri(), '{'),
            ];
        })->filter(function ($route) {
            // Keep only GET|HEAD routes
            if (!in_array('GET', $route['methods']) && !in_array('HEAD', $route['methods'])) {
                return false;
            }

            // Skip API routes if configured
            if ($route['wantsJson'] && $this->config['exclusions']['routes_with_params']) {
                return false;
            }

            // Skip routes with parameters if configured
            if ($route['hasParams'] && $this->config['exclusions']['routes_with_params']) {
                return false;
            }

            // Skip excluded patterns
            $uri = $route['uri'];
            foreach ($this->config['exclusions']['route_patterns'] as $pattern) {
                if (Str::is($pattern, $uri)) {
                    return false;
                }
            }

            return true;
        })->values();

        // Save to storage
        $this->saveJson('routes.json', $this->routes->toArray());
    }

    /**
     * Scan Blade templates for obvious issues
     */
    public function scanBladeTemplates(): int
    {
        $this->staticFindings = [];
        $viewsPath = resource_path('views');
        
        if (!is_dir($viewsPath)) {
            return 0;
        }

        $bladeFiles = File::allFiles($viewsPath);
        
        foreach ($bladeFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $this->scanBladeFile($file->getPathname());
        }

        $this->saveJson('static-findings.json', $this->staticFindings);
        
        return count($this->staticFindings);
    }

    /**
     * Scan individual Blade file
     */
    protected function scanBladeFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            // Check for problematic href values
            $this->checkProblematicHrefs($filePath, $lineNumber + 1, $line);
            
            // Check for invalid route() calls
            $this->checkInvalidRoutes($filePath, $lineNumber + 1, $line);
        }
    }

    /**
     * Check for problematic href attributes
     */
    protected function checkProblematicHrefs(string $file, int $lineNumber, string $line): void
    {
        foreach ($this->config['patterns']['problematic_hrefs'] as $problematicHref) {
            $pattern = '/href=["\']' . preg_quote($problematicHref, '/') . '["\']|href=["\']["\']|href=[\'"]\s*[\'"]|data-href=["\']' . preg_quote($problematicHref, '/') . '["\']/';
            
            if (preg_match($pattern, $line, $matches)) {
                $linkText = $this->extractLinkText($line);
                $suggestion = $this->suggestRouteForText($linkText);
                
                $this->staticFindings[] = [
                    'type' => 'problematic_href',
                    'file' => $file,
                    'line' => $lineNumber,
                    'content' => trim($line),
                    'issue' => "Problematic href: {$matches[0]}",
                    'link_text' => $linkText,
                    'suggestion' => $suggestion,
                ];
            }
        }
    }

    /**
     * Check for invalid route() calls
     */
    protected function checkInvalidRoutes(string $file, int $lineNumber, string $line): void
    {
        if (preg_match_all('/route\([\'"]([^\'"]+)[\'"]\)/', $line, $matches)) {
            foreach ($matches[1] as $routeName) {
                if (!Route::has($routeName)) {
                    $suggestion = $this->findSimilarRoute($routeName);
                    
                    $this->staticFindings[] = [
                        'type' => 'invalid_route',
                        'file' => $file,
                        'line' => $lineNumber,
                        'content' => trim($line),
                        'issue' => "Invalid route: {$routeName}",
                        'route_name' => $routeName,
                        'suggestion' => $suggestion,
                    ];
                }
            }
        }
    }

    /**
     * Process browser findings from Dusk test
     */
    public function processBrowserFindings(array $findings): void
    {
        $this->browserFindings = $findings;
        
        // Save browser findings
        foreach ($findings as $pageUrl => $pageFindings) {
            $filename = 'browser-findings/' . md5($pageUrl) . '.json';
            $this->saveJson($filename, $pageFindings);
        }
    }

    /**
     * Generate fix suggestions
     */
    public function generateSuggestions(): void
    {
        $this->suggestions = [];
        
        // Process static findings
        foreach ($this->staticFindings as $finding) {
            if ($finding['suggestion']) {
                $this->suggestions[] = $this->createSuggestion($finding);
            }
        }
        
        // Process browser findings
        foreach ($this->browserFindings as $pageUrl => $pageFindings) {
            foreach ($pageFindings as $finding) {
                if (isset($finding['broken_links'])) {
                    foreach ($finding['broken_links'] as $brokenLink) {
                        $suggestion = $this->suggestFixForBrokenLink($brokenLink, $pageUrl);
                        if ($suggestion) {
                            $this->suggestions[] = $suggestion;
                        }
                    }
                }
            }
        }
    }

    /**
     * Apply suggested fixes to files
     */
    public function applyFixes(): int
    {
        $fixCount = 0;
        $backupDir = $this->basePath . '/backups/' . date('Y-m-d_H-i-s');
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        foreach ($this->suggestions as $suggestion) {
            if ($suggestion['confidence'] >= $this->config['suggestions']['minimum_score']) {
                if ($this->applySuggestion($suggestion, $backupDir)) {
                    $fixCount++;
                }
            }
        }

        return $fixCount;
    }

    /**
     * Apply individual suggestion
     */
    protected function applySuggestion(array $suggestion, string $backupDir): bool
    {
        $filePath = $suggestion['file'];
        
        if (!file_exists($filePath)) {
            return false;
        }

        // Create backup
        $backupPath = $backupDir . '/' . basename($filePath);
        copy($filePath, $backupPath);

        // Apply fix
        $content = file_get_contents($filePath);
        $newContent = str_replace($suggestion['before'], $suggestion['after'], $content);
        
        if ($content !== $newContent) {
            file_put_contents($filePath, $newContent);
            return true;
        }

        return false;
    }

    /**
     * Generate HTML report
     */
    public function generateReport(): string
    {
        $reportData = [
            'timestamp' => now()->toISOString(),
            'config' => $this->config,
            'routes_count' => $this->routes->count(),
            'static_findings' => $this->staticFindings,
            'browser_findings' => $this->browserFindings,
            'suggestions' => $this->suggestions,
            'summary' => [
                'total_issues' => count($this->staticFindings) + count($this->browserFindings),
                'high_confidence_fixes' => count(array_filter($this->suggestions, fn($s) => $s['confidence'] >= 0.9)),
                'medium_confidence_fixes' => count(array_filter($this->suggestions, fn($s) => $s['confidence'] >= 0.7 && $s['confidence'] < 0.9)),
                'low_confidence_fixes' => count(array_filter($this->suggestions, fn($s) => $s['confidence'] < 0.7)),
            ],
        ];

        $reportPath = $this->basePath . '/report.html';
        $htmlContent = view('vendor.link-audit.report', $reportData)->render();
        file_put_contents($reportPath, $htmlContent);

        return $reportPath;
    }

    /**
     * Helper methods
     */
    protected function ensureDirectoriesExist(): void
    {
        $directories = [
            $this->basePath,
            $this->basePath . '/browser-findings',
            $this->basePath . '/screenshots',
            $this->basePath . '/backups',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    protected function saveJson(string $filename, array $data): void
    {
        $path = $this->basePath . '/' . $filename;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function extractLinkText(string $line): string
    {
        if (preg_match('/>([^<]+)<\/a>/', $line, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        
        return '';
    }

    protected function suggestRouteForText(string $linkText): ?array
    {
        if (empty($linkText)) {
            return null;
        }

        return Fuzzy::findBestRoute('', $this->routes->toArray(), $linkText, 0.5);
    }

    protected function findSimilarRoute(string $routeName): ?array
    {
        return Fuzzy::findBestRoute($routeName, $this->routes->toArray(), '', 0.5);
    }

    protected function createSuggestion(array $finding): array
    {
        $suggestion = [
            'type' => $finding['type'],
            'file' => $finding['file'],
            'line' => $finding['line'],
            'issue' => $finding['issue'],
            'confidence' => $finding['suggestion']['similarity_score'] ?? 0.0,
            'before' => '',
            'after' => '',
            'route_suggestion' => $finding['suggestion'],
        ];

        // Generate before/after for different types
        if ($finding['type'] === 'invalid_route') {
            $suggestion['before'] = "route('{$finding['route_name']}')";
            $suggestion['after'] = "route('{$finding['suggestion']['name']}')";
        } elseif ($finding['type'] === 'problematic_href') {
            if ($finding['suggestion']) {
                $suggestion['before'] = 'href="#"';
                $suggestion['after'] = "href=\"{{ route('{$finding['suggestion']['name']}') }}\"";
            }
        }

        return $suggestion;
    }

    protected function suggestFixForBrokenLink(array $brokenLink, string $pageUrl): ?array
    {
        $targetPath = parse_url($brokenLink['href'], PHP_URL_PATH);
        $suggestion = Fuzzy::findBestRoute($targetPath, $this->routes->toArray(), $brokenLink['text'] ?? '');

        if (!$suggestion) {
            return null;
        }

        return [
            'type' => 'broken_browser_link',
            'page_url' => $pageUrl,
            'link_href' => $brokenLink['href'],
            'link_text' => $brokenLink['text'] ?? '',
            'confidence' => $suggestion['similarity_score'],
            'route_suggestion' => $suggestion,
        ];
    }

    protected function output(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message;
        }
    }

    /**
     * Get routes collection
     */
    public function getRoutes(): Collection
    {
        return $this->routes ?? collect();
    }

    /**
     * Get static findings
     */
    public function getStaticFindings(): array
    {
        return $this->staticFindings;
    }

    /**
     * Get browser findings
     */
    public function getBrowserFindings(): array
    {
        return $this->browserFindings;
    }

    /**
     * Get suggestions
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }
}