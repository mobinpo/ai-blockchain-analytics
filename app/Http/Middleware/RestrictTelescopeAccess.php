<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RestrictTelescopeAccess
{
    protected array $allowedIps;
    protected array $allowedRoles;
    protected int $sessionTimeout;
    protected bool $requireAuth;

    public function __construct()
    {
        $this->allowedIps = config('telescope-enhanced.security.ip_whitelist', []);
        $this->allowedRoles = config('telescope-enhanced.security.allowed_roles', ['admin', 'developer']);
        $this->sessionTimeout = config('telescope-enhanced.security.session_timeout', 3600);
        $this->requireAuth = config('telescope-enhanced.security.require_auth', true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Skip in development environment
        if (app()->environment('local', 'development')) {
            return $next($request);
        }

        // Check if Telescope is enabled
        if (!config('telescope-enhanced.enabled', false)) {
            Log::warning('Telescope access attempted but disabled', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
            ]);
            
            return response()->json([
                'error' => 'Telescope is not available in this environment.'
            ], 503);
        }

        // Rate limiting per IP
        $rateLimitKey = 'telescope_access_attempts:' . $request->ip();
        $attempts = Cache::get($rateLimitKey, 0);
        
        if ($attempts >= 10) {
            Log::warning('Telescope access rate limited', [
                'ip' => $request->ip(),
                'attempts' => $attempts,
            ]);
            
            return response()->json([
                'error' => 'Too many attempts. Please try again later.'
            ], 429);
        }

        // Check IP whitelist if configured
        if (!empty($this->allowedIps) && !$this->isIpAllowed($request->ip())) {
            $this->recordFailedAttempt($request, 'IP not whitelisted');
            Cache::increment($rateLimitKey, 1);
            Cache::expire($rateLimitKey, 3600); // 1 hour
            
            return response()->json([
                'error' => 'Access denied from this IP address.'
            ], 403);
        }

        // Check authentication if required
        if ($this->requireAuth) {
            if (!Auth::check()) {
                $this->recordFailedAttempt($request, 'Not authenticated');
                Cache::increment($rateLimitKey, 1);
                Cache::expire($rateLimitKey, 3600);
                
                return redirect()->route('login');
            }

            // Check user role
            $user = Auth::user();
            if (!$this->hasRequiredRole($user)) {
                $this->recordFailedAttempt($request, 'Insufficient permissions', $user);
                Cache::increment($rateLimitKey, 1);
                Cache::expire($rateLimitKey, 3600);
                
                return response()->json([
                    'error' => 'You do not have permission to access Telescope.'
                ], 403);
            }

            // Check session timeout
            if (!$this->isSessionValid($user)) {
                $this->recordFailedAttempt($request, 'Session expired', $user);
                Auth::logout();
                
                return redirect()->route('login');
            }

            // Update session activity
            $this->updateSessionActivity($user);
        }

        // Log successful access
        Log::info('Telescope accessed successfully', [
            'ip' => $request->ip(),
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
            'url' => $request->fullUrl(),
        ]);

        return $next($request);
    }

    protected function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowedIps)) {
            return true;
        }

        foreach ($this->allowedIps as $allowedIp) {
            if ($this->matchIpPattern($ip, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    protected function matchIpPattern(string $ip, string $pattern): bool
    {
        // Handle CIDR notation
        if (strpos($pattern, '/') !== false) {
            [$subnet, $mask] = explode('/', $pattern);
            return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet);
        }

        // Handle wildcard patterns
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', $pattern);
            return preg_match("/^{$pattern}$/", $ip);
        }

        // Exact match
        return $ip === $pattern;
    }

    protected function hasRequiredRole($user): bool
    {
        if (empty($this->allowedRoles)) {
            return true;
        }

        // Check if user has any of the required roles
        if (method_exists($user, 'hasRole')) {
            foreach ($this->allowedRoles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
            return false;
        }

        // Check if user has 'role' attribute
        if (isset($user->role)) {
            return in_array($user->role, $this->allowedRoles);
        }

        // Check if user has 'roles' relationship
        if (method_exists($user, 'roles') && $user->roles()->exists()) {
            $userRoles = $user->roles()->pluck('name')->toArray();
            return !empty(array_intersect($userRoles, $this->allowedRoles));
        }

        // Default to admin check if no role system is implemented
        return isset($user->is_admin) && $user->is_admin;
    }

    protected function isSessionValid($user): bool
    {
        $sessionKey = 'telescope_session:' . $user->id;
        $lastActivity = Cache::get($sessionKey);

        if (!$lastActivity) {
            return true; // First access
        }

        return (time() - $lastActivity) < $this->sessionTimeout;
    }

    protected function updateSessionActivity($user): void
    {
        $sessionKey = 'telescope_session:' . $user->id;
        Cache::put($sessionKey, time(), $this->sessionTimeout + 300); // Extra 5 minutes
    }

    protected function recordFailedAttempt(Request $request, string $reason, $user = null): void
    {
        Log::warning('Telescope access denied', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'timestamp' => now()->toISOString(),
        ]);

        // Store failed attempts for monitoring
        $failureKey = 'telescope_failures:' . date('Y-m-d-H');
        Cache::increment($failureKey, 1);
        Cache::expire($failureKey, 86400); // 24 hours
    }
}