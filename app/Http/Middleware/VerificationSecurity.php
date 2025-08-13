<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerificationSecurity
{
    /**
     * Handle an incoming request for verification endpoints
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limiting for verification requests
        $key = 'verification_security:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 60)) { // 60 requests per minute
            Log::warning('Verification rate limit exceeded', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Please slow down.',
                'retry_after' => RateLimiter::availableIn($key)
            ], 429);
        }
        
        RateLimiter::hit($key, 60); // 1-minute window
        
        // Validate user agent (block known bad bots)
        $userAgent = $request->userAgent();
        $blockedAgents = [
            'sqlmap',
            'nikto',
            'burpsuite',
            'nessus',
            'acunetix',
            'w3af',
            'skipfish',
            'havij',
            'pangolin',
            'netsparker',
            'websecurify'
        ];
        
        foreach ($blockedAgents as $blockedAgent) {
            if (stripos($userAgent, $blockedAgent) !== false) {
                Log::alert('Blocked security scanning tool detected', [
                    'ip' => $request->ip(),
                    'user_agent' => $userAgent,
                    'path' => $request->path()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied'
                ], 403);
            }
        }
        
        // Check for suspicious request patterns
        $suspiciousPatterns = [
            'union select',
            'drop table',
            'exec(',
            'javascript:',
            '<script',
            'onload=',
            'onerror=',
            '../../../',
            '..\\..\\',
            'cmd.exe',
            '/etc/passwd',
            'base64_decode',
            'eval(',
            'assert(',
            'system(',
            'shell_exec'
        ];
        
        $requestContent = json_encode([
            'query' => $request->query(),
            'input' => $request->input(),
            'headers' => $request->headers->all()
        ]);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($requestContent, $pattern) !== false) {
                Log::alert('Suspicious request pattern detected', [
                    'ip' => $request->ip(),
                    'pattern' => $pattern,
                    'path' => $request->path(),
                    'method' => $request->method()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid request format'
                ], 400);
            }
        }
        
        // Validate contract address format if present
        if ($request->has('contract_address')) {
            $contractAddress = $request->get('contract_address');
            if (!$this->isValidContractAddress($contractAddress)) {
                Log::info('Invalid contract address format', [
                    'ip' => $request->ip(),
                    'contract_address' => $contractAddress
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid contract address format'
                ], 400);
            }
        }
        
        // Check for verification URL tampering
        if ($request->hasAny(['contract', 'signature', 'expires', 'id'])) {
            if (!$this->validateVerificationParameters($request)) {
                Log::warning('Verification URL tampering detected', [
                    'ip' => $request->ip(),
                    'params' => $request->query()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid verification parameters'
                ], 400);
            }
        }
        
        // Add security headers to response
        $response = $next($request);
        
        if ($response instanceof Response) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('X-XSS-Protection', '1; mode=block');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
        
        return $response;
    }
    
    /**
     * Validate contract address format
     */
    private function isValidContractAddress(string $address): bool
    {
        // Check if it's a valid Ethereum-style address
        return preg_match('/^0x[a-fA-F0-9]{40}$/', trim($address)) === 1;
    }
    
    /**
     * Validate verification URL parameters
     */
    private function validateVerificationParameters(Request $request): bool
    {
        $requiredParams = ['contract', 'signature', 'expires', 'id'];
        
        // Check all required parameters are present
        foreach ($requiredParams as $param) {
            if (!$request->has($param) || empty($request->get($param))) {
                return false;
            }
        }
        
        // Validate contract address
        $contract = $request->get('contract');
        if (!$this->isValidContractAddress($contract)) {
            return false;
        }
        
        // Validate verification ID format
        $id = $request->get('id');
        if (!preg_match('/^verify_[a-zA-Z0-9]{32}$/', $id)) {
            return false;
        }
        
        // Validate expiry date format
        $expires = $request->get('expires');
        try {
            $expiryDate = new \DateTime($expires);
            // Check if expiry is too far in the future (max 1 week)
            $maxExpiry = new \DateTime('+1 week');
            if ($expiryDate > $maxExpiry) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
        
        // Validate signature format (should be hex)
        $signature = $request->get('signature');
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $signature)) {
            return false;
        }
        
        return true;
    }
}