<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Telescope Production Authorization Middleware
 * 
 * Provides enhanced security for Telescope access in production:
 * - IP-based restrictions
 * - User-based permissions
 * - Time-based access control
 * - Rate limiting
 * - Audit logging
 */
final class TelescopeProductionAuthorize
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply restrictions in production
        if (!app()->environment('production')) {
            return $next($request);
        }

        // Check if Telescope is enabled in production
        if (!config('telescope.ai_blockchain.production_enabled', false)) {
            abort(404, 'Telescope is not available in production');
        }

        // Check maintenance window
        if ($this->isInMaintenanceWindow()) {
            abort(503, 'Telescope is temporarily unavailable during maintenance');
        }

        // Check auto-disable timer
        if ($this->isAutoDisabled()) {
            abort(403, 'Telescope access has been automatically disabled');
        }

        // IP-based restrictions
        if (!$this->isAllowedIp($request)) {
            $this->logUnauthorizedAccess($request, 'IP not allowed');
            abort(403, 'Access denied from this IP address');
        }

        // Rate limiting
        if (!$this->checkRateLimit($request)) {
            $this->logUnauthorizedAccess($request, 'Rate limit exceeded');
            abort(429, 'Too many requests');
        }

        // User authentication and authorization
        if (!$this->isAuthorizedUser($request)) {
            $this->logUnauthorizedAccess($request, 'User not authorized');
            abort(403, 'User not authorized to access Telescope');
        }

        // Log successful access
        $this->logAuthorizedAccess($request);

        // Update last access time
        $this->updateLastAccess();

        return $next($request);
    }

    /**
     * Check if current time is within maintenance window.
     */
    protected function isInMaintenanceWindow(): bool
    {
        $maintenanceConfig = config('telescope.ai_blockchain.production_restrictions.maintenance_window');
        
        if (!$maintenanceConfig) {
            return false;
        }

        $timezone = $maintenanceConfig['timezone'] ?? 'UTC';
        $start = $maintenanceConfig['start'] ?? '02:00';
        $end = $maintenanceConfig['end'] ?? '04:00';

        $now = Carbon::now($timezone);
        $startTime = Carbon::createFromTimeString($start, $timezone);
        $endTime = Carbon::createFromTimeString($end, $timezone);

        // Handle overnight maintenance windows
        if ($endTime->lessThan($startTime)) {
            return $now->greaterThanOrEqualTo($startTime) || $now->lessThanOrEqualTo($endTime);
        }

        return $now->between($startTime, $endTime);
    }

    /**
     * Check if Telescope has been auto-disabled.
     */
    protected function isAutoDisabled(): bool
    {
        $autoDisableHours = config('telescope.ai_blockchain.production_restrictions.auto_disable_hours');
        
        if (!$autoDisableHours) {
            return false;
        }

        $enabledAt = Cache::get('telescope_production_enabled_at');
        
        if (!$enabledAt) {
            // First access - set the timer
            Cache::put('telescope_production_enabled_at', now(), now()->addHours($autoDisableHours));
            return false;
        }

        if (now()->diffInHours($enabledAt) > $autoDisableHours) {
            Cache::forget('telescope_production_enabled_at');
            return true;
        }

        return false;
    }

    /**
     * Check if the request IP is allowed.
     */
    protected function isAllowedIp(Request $request): bool
    {
        $allowedIps = config('telescope.ai_blockchain.production_restrictions.allowed_ips', []);
        
        if (empty($allowedIps)) {
            return true; // No IP restrictions configured
        }

        $clientIp = $request->ip();
        $realIp = $request->header('X-Real-IP');
        $forwardedFor = $request->header('X-Forwarded-For');

        // Check all possible IP sources
        $ipsToCheck = array_filter([
            $clientIp,
            $realIp,
            $forwardedFor ? explode(',', $forwardedFor)[0] : null,
        ]);

        foreach ($ipsToCheck as $ip) {
            $ip = trim($ip);
            
            // Check exact match
            if (in_array($ip, $allowedIps)) {
                return true;
            }

            // Check CIDR ranges
            foreach ($allowedIps as $allowedIp) {
                if (str_contains($allowedIp, '/') && $this->ipInRange($ip, $allowedIp)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range.
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        [$range, $netmask] = explode('/', $range, 2);
        $rangeDecimal = ip2long($range);
        $ipDecimal = ip2long($ip);
        $wildcardDecimal = pow(2, (32 - $netmask)) - 1;
        $netmaskDecimal = ~$wildcardDecimal;

        return ($ipDecimal & $netmaskDecimal) === ($rangeDecimal & $netmaskDecimal);
    }

    /**
     * Check rate limiting.
     */
    protected function checkRateLimit(Request $request): bool
    {
        $key = 'telescope_rate_limit:' . $request->ip();
        $maxAttempts = 60; // requests per hour
        $decayMinutes = 60;

        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return false;
        }

        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));
        
        return true;
    }

    /**
     * Check if user is authorized.
     */
    protected function isAuthorizedUser(Request $request): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        // Check allowed users list
        $allowedUsers = config('telescope.ai_blockchain.production_restrictions.allowed_users', []);
        
        if (!empty($allowedUsers) && !in_array($user->email, $allowedUsers)) {
            return false;
        }

        // Check required permission
        $requiredPermission = config('telescope.ai_blockchain.production_restrictions.required_permission');
        
        if ($requiredPermission && !$user->can($requiredPermission)) {
            return false;
        }

        // Check user role/status
        if (method_exists($user, 'hasRole') && !$user->hasRole(['admin', 'developer', 'devops'])) {
            return false;
        }

        return true;
    }

    /**
     * Log unauthorized access attempt.
     */
    protected function logUnauthorizedAccess(Request $request, string $reason): void
    {
        Log::warning('Unauthorized Telescope access attempt', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
            'reason' => $reason,
            'url' => $request->fullUrl(),
            'timestamp' => now()->toISOString(),
            'headers' => [
                'X-Real-IP' => $request->header('X-Real-IP'),
                'X-Forwarded-For' => $request->header('X-Forwarded-For'),
                'X-Forwarded-Proto' => $request->header('X-Forwarded-Proto'),
            ],
        ]);

        // Send to Sentry if configured
        if (app()->bound('sentry')) {
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($request, $reason): void {
                $scope->setTag('security_event', 'unauthorized_telescope_access');
                $scope->setContext('request', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                ]);
                $scope->setContext('security', [
                    'reason' => $reason,
                    'user_id' => Auth::id(),
                    'user_email' => Auth::user()?->email,
                ]);
                
                \Sentry\captureMessage(
                    'Unauthorized Telescope access attempt: ' . $reason,
                    'warning'
                );
            });
        }
    }

    /**
     * Log authorized access.
     */
    protected function logAuthorizedAccess(Request $request): void
    {
        Log::info('Telescope access granted', [
            'ip' => $request->ip(),
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
            'url' => $request->fullUrl(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Update last access time for monitoring.
     */
    protected function updateLastAccess(): void
    {
        Cache::put('telescope_last_production_access', [
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
        ], now()->addHours(24));
    }
}