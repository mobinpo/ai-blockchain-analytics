<?php

namespace Tests\Browser;

use App\Models\User;
use App\Services\LinkAuditor;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LinkAuditTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected LinkAuditor $auditor;
    protected Collection $routes;
    protected array $visitedPages = [];
    protected array $findings = [];
    protected int $maxDepth;
    protected string $baseUrl;
    protected bool $includeAuth;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->auditor = app(LinkAuditor::class);
        $this->baseUrl = env('LINK_AUDIT_BASE_URL', config('link_audit.base_url'));
        $this->includeAuth = env('LINK_AUDIT_INCLUDE_AUTH', 'false') === 'true';
        $this->maxDepth = config('link_audit.crawl.max_depth', 2);
        
        // Build route index
        $this->auditor->buildRouteIndex();
        $this->routes = $this->auditor->getRoutes();
    }

    /**
     * Test all accessible routes for broken links
     */
    public function test_audit_all_routes_for_broken_links(): void
    {
        $this->browse(function (Browser $browser) {
            // Set up browser
            $browser->resize(1920, 1080);
            
            // Login if authentication is enabled
            $user = null;
            if ($this->includeAuth) {
                $user = $this->loginUser($browser);
            }

            // Test each route
            foreach ($this->routes as $route) {
                $this->auditRoute($browser, $route, $user);
            }

            // Save findings
            $this->auditor->processBrowserFindings($this->findings);
        });
    }

    /**
     * Login user for authenticated testing
     */
    protected function loginUser(Browser $browser): User
    {
        $user = User::factory()->create([
            'email' => config('link_audit.login.email'),
            'password' => bcrypt(config('link_audit.login.password')),
            'email_verified_at' => now(),
        ]);

        $browser->visit('/login')
            ->waitFor('input[name="email"]')
            ->type('email', $user->email)
            ->type('password', config('link_audit.login.password'))
            ->press('Login')
            ->waitForLocation(config('link_audit.login.redirect_after_login', '/dashboard'));

        return $user;
    }

    /**
     * Audit a specific route
     */
    protected function auditRoute(Browser $browser, array $route, ?User $user = null): void
    {
        $url = $this->buildFullUrl($route['uri']);
        
        if (in_array($url, $this->visitedPages)) {
            return;
        }

        $this->visitedPages[] = $url;

        try {
            echo "Auditing: {$url}\n";
            
            $browser->visit($route['uri'])
                ->waitFor('body', 10);

            // Wait for JavaScript to complete
            $browser->waitUntil('document.readyState === "complete"', 5);
            usleep(config('link_audit.crawl.wait_after_click', 2000) * 1000);

            // Check for obvious errors
            $this->checkForErrors($browser, $url);

            // Extract and test links
            $links = $this->extractLinks($browser, $url);
            $brokenLinks = $this->testLinks($browser, $links);

            // Record findings
            if (!empty($brokenLinks) || $browser->element('body')->getText() === '') {
                $this->findings[$url] = [
                    'route' => $route,
                    'status' => $this->getPageStatus($browser),
                    'broken_links' => $brokenLinks,
                    'errors' => $this->findJavaScriptErrors($browser),
                    'timestamp' => now()->toISOString(),
                ];

                // Take screenshot if there are issues
                if (!empty($brokenLinks)) {
                    $this->takeScreenshot($browser, $url);
                }
            }

        } catch (\Exception $e) {
            $this->findings[$url] = [
                'route' => $route,
                'status' => 'error',
                'error' => $e->getMessage(),
                'broken_links' => [],
                'errors' => [$e->getMessage()],
                'timestamp' => now()->toISOString(),
            ];

            echo "Error auditing {$url}: {$e->getMessage()}\n";
        }
    }

    /**
     * Extract all links from the current page
     */
    protected function extractLinks(Browser $browser, string $currentUrl): array
    {
        $links = [];
        $selectors = config('link_audit.patterns.link_selectors');

        foreach ($selectors as $selector) {
            try {
                $elements = $browser->driver->findElements(WebDriverBy::cssSelector($selector));
                
                foreach ($elements as $element) {
                    $link = $this->extractLinkData($element, $currentUrl);
                    if ($link && $this->shouldTestLink($link['href'])) {
                        $links[] = $link;
                    }
                }
            } catch (\Exception $e) {
                // Continue if selector fails
                continue;
            }
        }

        return array_unique($links, SORT_REGULAR);
    }

    /**
     * Extract link data from a WebDriver element
     */
    protected function extractLinkData(WebDriverElement $element, string $currentUrl): ?array
    {
        $href = $element->getAttribute('href') ?: $element->getAttribute('data-href');
        
        if (!$href) {
            return null;
        }

        // Convert relative to absolute URLs
        if (str_starts_with($href, '/')) {
            $href = rtrim($this->baseUrl, '/') . $href;
        } elseif (!str_starts_with($href, 'http')) {
            $href = rtrim($currentUrl, '/') . '/' . ltrim($href, '/');
        }

        return [
            'href' => $href,
            'text' => trim($element->getText()),
            'title' => $element->getAttribute('title'),
            'class' => $element->getAttribute('class'),
            'id' => $element->getAttribute('id'),
            'tag' => $element->getTagName(),
        ];
    }

    /**
     * Determine if a link should be tested
     */
    protected function shouldTestLink(string $href): bool
    {
        // Skip ignore patterns
        foreach (config('link_audit.patterns.ignore_hrefs') as $pattern) {
            if (str_starts_with($href, $pattern)) {
                return false;
            }
        }

        // Skip external domains if configured
        if (config('link_audit.exclusions.external_domains')) {
            $parsedHref = parse_url($href);
            $parsedBase = parse_url($this->baseUrl);
            
            if (isset($parsedHref['host']) && isset($parsedBase['host']) && 
                $parsedHref['host'] !== $parsedBase['host']) {
                return false;
            }
        }

        // Skip excluded paths
        $path = parse_url($href, PHP_URL_PATH);
        foreach (config('link_audit.exclusions.paths', []) as $excludePath) {
            if (fnmatch($excludePath, $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Test a collection of links
     */
    protected function testLinks(Browser $browser, array $links): array
    {
        $brokenLinks = [];
        $currentUrl = $browser->driver->getCurrentURL();

        foreach ($links as $link) {
            try {
                // Use a separate HTTP client for testing to avoid browser navigation issues
                $status = $this->testLinkWithHttpClient($link['href']);
                
                if (!$this->isSuccessStatus($status)) {
                    $brokenLinks[] = array_merge($link, [
                        'status' => $status,
                        'found_on' => $currentUrl,
                    ]);
                }

            } catch (\Exception $e) {
                $brokenLinks[] = array_merge($link, [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'found_on' => $currentUrl,
                ]);
            }
        }

        return $brokenLinks;
    }

    /**
     * Test a link using HTTP client
     */
    protected function testLinkWithHttpClient(string $url): int
    {
        $client = new \GuzzleHttp\Client([
            'timeout' => config('link_audit.crawl.timeout_seconds', 30),
            'allow_redirects' => true,
            'verify' => false, // For local development
        ]);

        try {
            $response = $client->head($url);
            return $response->getStatusCode();
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $e->getResponse()->getStatusCode();
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            return $e->getResponse()->getStatusCode();
        } catch (\Exception $e) {
            return 0; // Connection error
        }
    }

    /**
     * Check if status code indicates success
     */
    protected function isSuccessStatus(int $status): bool
    {
        return $status >= 200 && $status < 400;
    }

    /**
     * Check for obvious page errors
     */
    protected function checkForErrors(Browser $browser, string $url): void
    {
        // Check for Laravel error pages
        if ($browser->element('body')->getText() === '' || 
            str_contains($browser->element('title')->getText(), 'Error') ||
            str_contains($browser->driver->getPageSource(), 'Whoops, looks like something went wrong')) {
            
            throw new \Exception("Page appears to have errors or is empty");
        }
    }

    /**
     * Find JavaScript errors in the console
     */
    protected function findJavaScriptErrors(Browser $browser): array
    {
        try {
            $logs = $browser->driver->manage()->getLog('browser');
            $errors = [];

            foreach ($logs as $log) {
                if ($log['level'] === 'SEVERE') {
                    $errors[] = $log['message'];
                }
            }

            return $errors;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get page status information
     */
    protected function getPageStatus(Browser $browser): string
    {
        try {
            // Check if page loaded successfully
            $title = $browser->element('title')->getText();
            $bodyText = $browser->element('body')->getText();
            
            if (empty($bodyText)) {
                return 'empty';
            }
            
            if (str_contains($title, 'Error') || str_contains($bodyText, 'Error')) {
                return 'error';
            }
            
            return 'success';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Take screenshot for failed pages
     */
    protected function takeScreenshot(Browser $browser, string $url): void
    {
        try {
            $filename = 'screenshot_' . md5($url) . '_' . time() . '.png';
            $path = config('link_audit.storage.base_path') . '/screenshots/' . $filename;
            
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            
            $browser->screenshot($filename);
            
            // Move screenshot to our custom path
            $defaultPath = base_path('tests/Browser/screenshots/' . $filename . '.png');
            if (file_exists($defaultPath)) {
                rename($defaultPath, $path);
            }
            
        } catch (\Exception $e) {
            // Screenshot failed, continue
        }
    }

    /**
     * Build full URL from route URI
     */
    protected function buildFullUrl(string $uri): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($uri, '/');
    }

    /**
     * Clean up after test
     */
    protected function tearDown(): void
    {
        // Save final findings
        if (!empty($this->findings)) {
            $this->auditor->processBrowserFindings($this->findings);
        }

        parent::tearDown();
    }
}