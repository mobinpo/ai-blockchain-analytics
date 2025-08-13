<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class TelescopeProduction
{
    /**
     * Handle an incoming request to Telescope in production.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always allow in non-production environments
        if (!app()->environment('production')) {
            return $next($request);
        }

        // Check if Telescope is enabled in production
        if (!config('telescope.ai_blockchain.production_enabled')) {
            Log::warning('Telescope access denied: disabled in production', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
            ]);
            
            abort(404, 'Not Found');
        }

        // Check IP restrictions
        $allowedIps = config('telescope.ai_blockchain.production_restrictions.allowed_ips', []);
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
            Log::warning('Telescope access denied: IP not allowed', [
                'ip' => $request->ip(),
                'allowed_ips' => $allowedIps,
                'user' => Auth::user()?->email,
            ]);
            
            abort(403, 'Access Denied');
        }

        // Check user restrictions
        if (!Auth::check()) {
            Log::warning('Telescope access denied: not authenticated', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);
            
            return redirect()->route('login');
        }

        $user = Auth::user();
        $allowedUsers = config('telescope.ai_blockchain.production_restrictions.allowed_users', []);
        
        if (!empty($allowedUsers) && !in_array($user->email, $allowedUsers)) {
            Log::warning('Telescope access denied: user not allowed', [
                'user' => $user->email,
                'allowed_users' => $allowedUsers,
                'ip' => $request->ip(),
            ]);
            
            abort(403, 'Access Denied');
        }

        // Check permission restrictions
        $requiredPermission = config('telescope.ai_blockchain.production_restrictions.required_permission');
        if ($requiredPermission && !$user->can($requiredPermission)) {
            Log::warning('Telescope access denied: insufficient permissions', [
                'user' => $user->email,
                'required_permission' => $requiredPermission,
                'ip' => $request->ip(),
            ]);
            
            abort(403, 'Insufficient Permissions');
        }

        // Check auto-disable timer
        $autoDisableHours = config('telescope.ai_blockchain.production_restrictions.auto_disable_hours');
        if ($autoDisableHours) {
            $enabledAt = cache()->get('telescope_enabled_at');
            
            if (!$enabledAt) {
                cache()->put('telescope_enabled_at', now(), now()->addHours($autoDisableHours));
                Log::info('Telescope auto-disable timer started', [
                    'user' => $user->email,
                    'hours' => $autoDisableHours,
                ]);
            } elseif (now()->diffInHours($enabledAt) > $autoDisableHours) {
                cache()->forget('telescope_enabled_at');
                
                Log::warning('Telescope auto-disabled due to timeout', [
                    'user' => $user->email,
                    'enabled_at' => $enabledAt,
                    'hours_passed' => now()->diffInHours($enabledAt),
                ]);
                
                abort(503, 'Telescope has been automatically disabled for security');
            }
        }

        // Log successful access
        Log::info('Telescope access granted in production', [
            'user' => $user->email,
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
        ]);

        return $next($request);
    }
}