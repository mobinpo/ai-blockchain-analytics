<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class LegacyRedirects
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Only process GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Get the current path
        $currentPath = $request->path();
        
        // Skip if this path already exists as a route
        if ($this->routeExists($currentPath)) {
            return $next($request);
        }

        // Check for redirect mapping
        $redirects = $this->getRedirectMappings();
        $targetRoute = $this->findRedirectTarget($currentPath, $redirects);

        if ($targetRoute) {
            // Log the redirect for monitoring
            if (config('app.debug')) {
                logger('Legacy redirect', [
                    'from' => $currentPath,
                    'to' => $targetRoute,
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                ]);
            }

            // Generate the target URL
            $targetUrl = $this->generateTargetUrl($targetRoute, $request);
            
            if ($targetUrl) {
                return redirect($targetUrl, 301)
                    ->header('X-Redirect-Reason', 'Legacy URL mapping')
                    ->header('X-Original-Path', $currentPath);
            }
        }

        // Continue with normal request processing
        return $next($request);
    }

    /**
     * Check if a route exists for the given path
     */
    protected function routeExists(string $path): bool
    {
        try {
            // Clean the path
            $path = '/' . ltrim($path, '/');
            
            // Try to find a matching route
            $routes = Route::getRoutes();
            
            foreach ($routes as $route) {
                $routeUri = '/' . ltrim($route->uri(), '/');
                
                // Direct match
                if ($routeUri === $path) {
                    return true;
                }
                
                // Pattern match (for routes with parameters)
                $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routeUri);
                $pattern = str_replace('/', '\/', $pattern);
                
                if (preg_match('/^' . $pattern . '$/', $path)) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get redirect mappings from config
     */
    protected function getRedirectMappings(): array
    {
        try {
            $configPath = config_path('redirects.php');
            
            if (!file_exists($configPath)) {
                return [];
            }
            
            $redirects = include $configPath;
            
            return is_array($redirects) ? $redirects : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Find redirect target for a given path
     */
    protected function findRedirectTarget(string $currentPath, array $redirects): ?string
    {
        // Clean the current path
        $currentPath = '/' . ltrim($currentPath, '/');
        
        // Direct mapping check
        if (isset($redirects[$currentPath])) {
            return $redirects[$currentPath];
        }
        
        // Case-insensitive check
        $lowerPath = strtolower($currentPath);
        foreach ($redirects as $pattern => $target) {
            if (strtolower($pattern) === $lowerPath) {
                return $target;
            }
        }
        
        // Pattern matching with wildcards
        foreach ($redirects as $pattern => $target) {
            if (str_contains($pattern, '*')) {
                $regexPattern = str_replace(['/', '*'], ['\/', '.*'], $pattern);
                if (preg_match('/^' . $regexPattern . '$/', $currentPath)) {
                    return $target;
                }
            }
        }
        
        // Fuzzy matching for common variations
        return $this->findFuzzyMatch($currentPath, $redirects);
    }

    /**
     * Find fuzzy matches for common URL variations
     */
    protected function findFuzzyMatch(string $currentPath, array $redirects): ?string
    {
        $variations = $this->generatePathVariations($currentPath);
        
        foreach ($variations as $variation) {
            if (isset($redirects[$variation])) {
                return $redirects[$variation];
            }
        }
        
        return null;
    }

    /**
     * Generate common path variations
     */
    protected function generatePathVariations(string $path): array
    {
        $variations = [];
        
        // Remove trailing slash
        $variations[] = rtrim($path, '/');
        
        // Add trailing slash
        $variations[] = $path . '/';
        
        // Remove common prefixes/suffixes
        $variations[] = str_replace(['-page', '-view', '.html', '.php'], '', $path);
        
        // Replace separators
        $variations[] = str_replace('-', '_', $path);
        $variations[] = str_replace('_', '-', $path);
        
        // Pluralization attempts
        if (str_ends_with($path, 's')) {
            $variations[] = substr($path, 0, -1); // Remove 's'
        } else {
            $variations[] = $path . 's'; // Add 's'
        }
        
        return array_unique($variations);
    }

    /**
     * Generate target URL from route name
     */
    protected function generateTargetUrl(string $routeName, Request $request): ?string
    {
        try {
            // Check if it's a valid route name
            if (Route::has($routeName)) {
                return route($routeName);
            }
            
            // Check if it's already a URL
            if (str_starts_with($routeName, 'http://') || str_starts_with($routeName, 'https://')) {
                return $routeName;
            }
            
            // Check if it's a relative path
            if (str_starts_with($routeName, '/')) {
                return url($routeName);
            }
            
            // Try to construct URL assuming it's a route name with dots converted to slashes
            $pathFromName = '/' . str_replace('.', '/', $routeName);
            if ($this->routeExists($pathFromName)) {
                return url($pathFromName);
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Add a new redirect mapping programmatically
     */
    public static function addRedirect(string $fromPath, string $toRoute): bool
    {
        try {
            $configPath = config_path('redirects.php');
            $redirects = [];
            
            if (file_exists($configPath)) {
                $redirects = include $configPath;
            }
            
            $redirects[$fromPath] = $toRoute;
            
            $content = "<?php\n\nreturn " . var_export($redirects, true) . ";\n";
            
            return file_put_contents($configPath, $content) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove a redirect mapping
     */
    public static function removeRedirect(string $fromPath): bool
    {
        try {
            $configPath = config_path('redirects.php');
            
            if (!file_exists($configPath)) {
                return true; // Nothing to remove
            }
            
            $redirects = include $configPath;
            
            if (isset($redirects[$fromPath])) {
                unset($redirects[$fromPath]);
                
                $content = "<?php\n\nreturn " . var_export($redirects, true) . ";\n";
                
                return file_put_contents($configPath, $content) !== false;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all redirect mappings
     */
    public static function getAllRedirects(): array
    {
        try {
            $configPath = config_path('redirects.php');
            
            if (!file_exists($configPath)) {
                return [];
            }
            
            $redirects = include $configPath;
            
            return is_array($redirects) ? $redirects : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Clear all redirect mappings
     */
    public static function clearAllRedirects(): bool
    {
        try {
            $configPath = config_path('redirects.php');
            $content = "<?php\n\nreturn [];\n";
            
            return file_put_contents($configPath, $content) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}