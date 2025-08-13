<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Telescope;

/**
 * Enhanced Telescope Authorization Middleware
 * 
 * Provides strict production access control for Laravel Telescope
 * with IP whitelisting, user authorization, and environment restrictions
 */
final class EnhancedTelescopeAuthorize
{
    /**
     * Allowed IP addresses for Telescope access in production
     */
    private const ALLOWED_IPS = [
        '127.0.0.1',
        '::1',
        // Add your production admin IPs here
        // '203.0.113.0/24', // Example: Office network
        // '198.51.100.50',  // Example: Admin VPN
    ];

    /**
     * Allowed user emails for Telescope access
     */
    private const ALLOWED_EMAILS = [
        // Add admin emails here
        // 'admin@yourcompany.com',
        // 'dev@yourcompany.com',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Always deny access in production unless explicitly enabled
        if (App::environment('production') && !config('telescope.enabled')) {
            return $this->denyAccess('Telescope is disabled in production');
        }

        // Check if Telescope is enabled
        if (!config('telescope.enabled', false)) {
            return $this->denyAccess('Telescope is disabled');
        }

        // In production, enforce strict access control
        if (App::environment('production')) {
            return $this->authorizeProductionAccess($request, $next);
        }

        // In non-production environments, use standard authorization
        return $this->authorizeNonProductionAccess($request, $next);
    }

    /**
     * Authorize access in production environment
     */
    private function authorizeProductionAccess(Request $request, Closure $next): mixed
    {
        // Check IP whitelist first
        if (!$this->isAllowedIP($request)) {
            \Log::warning('Telescope access denied: IP not whitelisted', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->url(),
            ]);
            return $this->denyAccess('Access denied: IP not authorized');
        }

        // Require authentication
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Authentication required for Telescope access');
        }

        // Check user authorization
        if (!$this->isAuthorizedUser($request)) {
            \Log::warning('Telescope access denied: User not authorized', [
                'user_id' => Auth::id(),
                'email' => Auth::user()->email,
                'ip' => $request->ip(),
            ]);
            return $this->denyAccess('Access denied: User not authorized');
        }

        // Check custom gate if defined
        if (Gate::has('viewTelescope') && !Gate::allows('viewTelescope', $request)) {
            return $this->denyAccess('Access denied: Gate authorization failed');
        }

        // Log successful access
        \Log::info('Telescope access granted in production', [
            'user_id' => Auth::id(),
            'email' => Auth::user()->email,
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }

    /**
     * Authorize access in non-production environments
     */
    private function authorizeNonProductionAccess(Request $request, Closure $next): mixed
    {
        // In development, allow access but still log it
        if (App::environment('local', 'development')) {
            return $next($request);
        }

        // In staging, require authentication but be less strict
        if (!Auth::check()) {
            return redirect()->route('login')->with('info', 'Please login to access Telescope');
        }

        // Check custom gate if defined
        if (Gate::has('viewTelescope') && !Gate::allows('viewTelescope', $request)) {
            return $this->denyAccess('Access denied: Gate authorization failed');
        }

        return $next($request);
    }

    /**
     * Check if the request IP is in the allowed list
     */
    private function isAllowedIP(Request $request): bool
    {
        $clientIP = $request->ip();
        
        foreach (self::ALLOWED_IPS as $allowedIP) {
            if ($this->ipInRange($clientIP, $allowedIP)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in range (supports CIDR notation)
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            // Single IP
            return $ip === $range;
        }

        // CIDR range
        [$subnet, $bits] = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->ipv4InRange($ip, $subnet, (int) $bits);
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $subnet, (int) $bits);
        }

        return false;
    }

    /**
     * Check if IPv4 is in range
     */
    private function ipv4InRange(string $ip, string $subnet, int $bits): bool
    {
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        
        return ($ip & $mask) === $subnet;
    }

    /**
     * Check if IPv6 is in range
     */
    private function ipv6InRange(string $ip, string $subnet, int $bits): bool
    {
        $subnet = inet_pton($subnet);
        $ip = inet_pton($ip);
        $binaryMask = str_repeat('f', $bits >> 2);
        
        switch ($bits & 3) {
            case 0:
                break;
            case 1:
                $binaryMask .= '8';
                break;
            case 2:
                $binaryMask .= 'c';
                break;
            case 3:
                $binaryMask .= 'e';
                break;
        }
        
        $binaryMask = str_pad($binaryMask, 32, '0');
        $binaryMask = pack('H*', $binaryMask);
        
        return ($ip & $binaryMask) === $subnet;
    }

    /**
     * Check if the authenticated user is authorized
     */
    private function isAuthorizedUser(Request $request): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        // Check email whitelist
        if (in_array($user->email, self::ALLOWED_EMAILS)) {
            return true;
        }

        // Check if user has admin role (if you have roles)
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        // Check if user is marked as telescope_access in database
        if (isset($user->telescope_access) && $user->telescope_access) {
            return true;
        }

        // Additional custom authorization logic can be added here
        
        return false;
    }

    /**
     * Deny access with appropriate response
     */
    private function denyAccess(string $reason = 'Access denied'): Response
    {
        if (request()->expectsJson()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $reason,
            ], 403);
        }

        abort(403, $reason);
    }
}