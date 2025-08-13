<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TelescopeAuthorize
{
    public function handle(Request $request, Closure $next): Response
    {
        // Log access attempt for security monitoring
        if (config('telescope.ai_blockchain.security.log_access_attempts', true)) {
            $this->logAccessAttempt($request);
        }

        // Check production restrictions
        if (app()->isProduction()) {
            return $this->handleProductionAccess($request, $next);
        }

        // In non-production environments, allow access for authenticated users
        return $this->handleNonProductionAccess($request, $next);
    }

    /**
     * Handle production environment access with enhanced security
     */
    private function handleProductionAccess(Request $request, Closure $next): Response
    {
        // Check if Telescope is enabled in production
        if (!config('telescope.ai_blockchain.production_enabled', false)) {
            abort(404, 'Service not found');
        }

        // IP address restrictions
        if (!$this->isAllowedIp($request->ip())) {
            abort(403, 'Access denied - IP not authorized');
        }

        // Rate limiting for production access
        if ($this->isRateLimited($request)) {
            abort(429, 'Too many requests - rate limited');
        }

        // Require authentication
        if (!Auth::check()) {
            return redirect()->route('login')->with('warning', 'Authentication required for monitoring access');
        }

        // User authorization checks
        if (!$this->isAuthorizedUser(Auth::user())) {
            abort(403, 'Unauthorized access - insufficient permissions');
        }

        // Auto-disable check
        if ($this->shouldAutoDisable()) {
            $this->disableTelescope();
            abort(503, 'Service temporarily unavailable');
        }

        return $next($request);
    }

    /**
     * Handle non-production environment access
     */
    private function handleNonProductionAccess(Request $request, Closure $next): Response
    {
        // In development, require authentication but be more permissive
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        return $next($request);
    }

    /**
     * Check if IP is allowed for production access
     */
    private function isAllowedIp(string $ip): bool
    {
        $allowedIps = config('telescope.ai_blockchain.production_restrictions.allowed_ips', []);
        
        if (empty($allowedIps)) {
            return true; // If no IPs configured, allow all
        }

        return in_array($ip, $allowedIps);
    }

    /**
     * Check if user is authorized for Telescope access
     */
    private function isAuthorizedUser($user): bool
    {
        // Check if user has required permission
        $requiredPermission = config('telescope.ai_blockchain.production_restrictions.required_permission');
        if ($requiredPermission && method_exists($user, 'can')) {
            if (!$user->can($requiredPermission)) {
                return false;
            }
        }

        // Check if user is in allowed users list
        $allowedUsers = config('telescope.ai_blockchain.production_restrictions.allowed_users', []);
        if (!empty($allowedUsers)) {
            return in_array($user->email, $allowedUsers);
        }

        // Check allowed emails from old config for backward compatibility
        $allowedEmails = array_filter(explode(',', env('TELESCOPE_ALLOWED_EMAILS', '')));
        if (!empty($allowedEmails)) {
            return in_array($user->email, $allowedEmails);
        }

        // Check if user has admin role
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * Check if request should be rate limited
     */
    private function isRateLimited(Request $request): bool
    {
        $key = 'telescope_access:' . $request->ip();
        $maxAttempts = 10; // Max 10 requests per hour in production
        $decayMinutes = 60;

        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return true;
        }

        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));
        return false;
    }

    /**
     * Check if Telescope should be auto-disabled
     */
    private function shouldAutoDisable(): bool
    {
        $autoDisableHours = config('telescope.ai_blockchain.production_restrictions.auto_disable_hours', 24);
        
        if (!$autoDisableHours) {
            return false;
        }

        $lastAccess = Cache::get('telescope_last_access', now());
        $hoursSinceLastAccess = now()->diffInHours($lastAccess);

        return $hoursSinceLastAccess >= $autoDisableHours;
    }

    /**
     * Disable Telescope for security
     */
    private function disableTelescope(): void
    {
        // Log the auto-disable event
        Log::warning('Telescope auto-disabled due to inactivity', [
            'auto_disable_hours' => config('telescope.ai_blockchain.production_restrictions.auto_disable_hours', 24),
            'environment' => app()->environment(),
        ]);

        // You could implement additional logic here to actually disable Telescope
        // For example, updating a database flag or cache value
    }

    /**
     * Log access attempts for security monitoring
     */
    private function logAccessAttempt(Request $request): void
    {
        $logData = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'environment' => app()->environment(),
            'authenticated' => Auth::check(),
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ];

        Log::info('Telescope access attempt', $logData);

        // Update last access time for auto-disable feature
        Cache::put('telescope_last_access', now(), now()->addDays(7));

        // Send to Sentry if configured
        if (app()->bound('sentry') && config('telescope.ai_blockchain.security.log_access_attempts', true)) {
            app('sentry')->addBreadcrumb([
                'message' => 'Telescope access attempt',
                'category' => 'security',
                'level' => 'info',
                'data' => $logData,
            ]);
        }
    }
}