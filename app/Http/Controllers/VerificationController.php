<?php

namespace App\Http\Controllers;

use App\Services\VerificationBadgeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    private VerificationBadgeService $verificationService;
    
    public function __construct(VerificationBadgeService $verificationService)
    {
        $this->verificationService = $verificationService;
    }
    
    /**
     * Show the verification page
     */
    public function index()
    {
        $stats = $this->verificationService->getVerificationStats();
        
        return inertia('VerificationGenerator', [
            'stats' => $stats,
            'title' => 'Get Your Smart Contract Verified',
            'description' => 'Generate cryptographically signed verification badges for your smart contracts'
        ]);
    }
    
    /**
     * Generate a verification badge for a contract
     */
    public function generateVerification(Request $request): JsonResponse
    {
        try {
            // Validate input
            $validated = $request->validate([
                'contract_address' => 'required|string|min:42|max:42|regex:/^0x[a-fA-F0-9]{40}$/',
                'project_name' => 'nullable|string|max:100',
                'description' => 'nullable|string|max:500',
                'website_url' => 'nullable|url|max:255',
                'github_url' => 'nullable|url|max:255',
                'expiry_hours' => 'nullable|integer|min:1|max:168', // Max 1 week
                'metadata' => 'nullable|array'
            ]);
            
            // Rate limiting check
            $key = 'verification:' . $request->ip() . ':' . $validated['contract_address'];
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Too many verification attempts. Please try again later.',
                    'retry_after' => RateLimiter::availableIn($key)
                ], 429);
            }
            
            RateLimiter::hit($key, 3600); // 1 hour window
            
            // Prepare metadata
            $metadata = array_filter([
                'description' => $validated['description'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'github_url' => $validated['github_url'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'generated_at' => now()->toISOString(),
                ...$validated['metadata'] ?? []
            ]);
            
            // Generate verification
            $verification = $this->verificationService->generateContractVerificationUrl(
                $validated['contract_address'],
                $validated['project_name'],
                $metadata,
                $validated['expiry_hours'] ?? 24
            );
            
            // Generate badge HTML
            $badgeHtml = $this->verificationService->generateBadgeHtml(
                $validated['contract_address'],
                $validated['project_name'],
                ['size' => 'medium', 'show_details' => true]
            );
            
            return response()->json([
                'success' => true,
                'verification' => $verification,
                'badge_html' => $badgeHtml,
                'embed_code' => $this->generateEmbedCode($badgeHtml),
                'message' => 'Verification badge generated successfully'
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Verification generation failed', [
                'error' => $e->getMessage(),
                'contract_address' => $request->get('contract_address'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate verification. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Verify a signed verification URL
     */
    public function verifyContract(Request $request): JsonResponse
    {
        try {
            $url = $request->fullUrl();
            
            // Verify the signed URL
            $result = $this->verificationService->verifySignedUrl($url);
            
            if ($result['success'] && $result['valid']) {
                return response()->json([
                    'success' => true,
                    'valid' => true,
                    'verification' => $result,
                    'message' => 'Contract verification is valid and active'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'valid' => false,
                    'error' => $result['error'] ?? 'Verification failed',
                    'message' => 'Invalid or expired verification'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::warning('Contract verification check failed', [
                'url' => $request->fullUrl(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'valid' => false,
                'error' => 'Verification check failed'
            ], 500);
        }
    }
    
    /**
     * Show verification details page
     */
    public function showVerification(Request $request)
    {
        try {
            $url = $request->fullUrl();
            $result = $this->verificationService->verifySignedUrl($url);
            
            if ($result['success'] && $result['valid']) {
                return inertia('Verification/Show', [
                    'verification' => $result,
                    'title' => 'Contract Verification Details',
                    'meta_description' => "Verified smart contract: {$result['project_name']} ({$result['contract_address']})"
                ]);
            } else {
                return inertia('Verification/Invalid', [
                    'error' => $result['error'] ?? 'Invalid verification',
                    'title' => 'Invalid Verification'
                ]);
            }
            
        } catch (\Exception $e) {
            return inertia('Verification/Error', [
                'error' => 'Unable to verify contract',
                'title' => 'Verification Error'
            ]);
        }
    }
    
    /**
     * Generate verification badge API endpoint
     */
    public function generateBadge(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
                'project_name' => 'nullable|string|max:100',
                'size' => 'nullable|in:small,medium,large',
                'style' => 'nullable|string|max:50',
                'show_details' => 'nullable|boolean'
            ]);
            
            $badgeHtml = $this->verificationService->generateBadgeHtml(
                $validated['contract_address'],
                $validated['project_name'] ?? null,
                [
                    'size' => $validated['size'] ?? 'medium',
                    'style' => $validated['style'] ?? 'default',
                    'show_details' => $validated['show_details'] ?? true
                ]
            );
            
            return response()->json([
                'success' => true,
                'badge_html' => $badgeHtml,
                'embed_code' => $this->generateEmbedCode($badgeHtml)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate badge'
            ], 500);
        }
    }
    
    /**
     * Batch generate verifications
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contracts' => 'required|array|max:10', // Limit batch size
                'contracts.*.address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
                'contracts.*.project_name' => 'nullable|string|max:100',
                'contracts.*.metadata' => 'nullable|array',
                'contracts.*.expiry_hours' => 'nullable|integer|min:1|max:168'
            ]);
            
            // Check rate limiting for batch operations
            $key = 'batch_verification:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 2)) { // Only 2 batch requests per hour
                return response()->json([
                    'success' => false,
                    'error' => 'Batch verification rate limit exceeded'
                ], 429);
            }
            
            RateLimiter::hit($key, 3600);
            
            $results = $this->verificationService->batchGenerateVerifications($validated['contracts']);
            
            return response()->json([
                'success' => true,
                'results' => $results,
                'total_processed' => count($results),
                'successful' => count(array_filter($results, fn($r) => $r['success'] ?? false))
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Batch verification failed'
            ], 500);
        }
    }
    
    /**
     * Get verification statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->verificationService->getVerificationStats();
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch statistics'
            ], 500);
        }
    }
    
    /**
     * Check if a contract is verified
     */
    public function checkVerification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/'
            ]);
            
            // In a real implementation, you'd check the database for existing verifications
            // For now, we'll return a basic check result
            
            return response()->json([
                'success' => true,
                'contract_address' => $validated['contract_address'],
                'is_verified' => false, // Would check database
                'verification_count' => 0, // Would count from database
                'last_verified' => null // Would get from database
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to check verification status'
            ], 500);
        }
    }
    
    /**
     * Generate embed code for the badge
     */
    private function generateEmbedCode(string $badgeHtml): string
    {
        return sprintf(
            '<!-- AI Blockchain Analytics Verification Badge -->
<div id="ai-verification-badge">
%s
</div>
<script>
// Verification badge loaded successfully
console.log("AI Blockchain Analytics verification badge loaded");
</script>',
            $badgeHtml
        );
    }

    /**
     * Get verification status for a contract (API endpoint)
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/'
            ]);
            
            // Use the existing checkVerification logic
            $contractAddress = $validated['contract_address'];
            
            // In a real implementation, check database for verification status
            // For demo purposes, return mock data based on known test addresses
            $isVerified = in_array($contractAddress, [
                '0x1234567890123456789012345678901234567890', // Demo address
                '0xA0b86a33E6441b93047e4e3b5c2B4d7e8A9B2C3D', // Another demo address
            ]);
            
            return response()->json([
                'success' => true,
                'contract_address' => $contractAddress,
                'is_verified' => $isVerified,
                'verification_level' => $isVerified ? 'standard' : null,
                'verification_date' => $isVerified ? now()->subDays(rand(1, 30))->toISOString() : null,
                'badge_available' => $isVerified,
                'verification_count' => $isVerified ? rand(1, 5) : 0
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid contract address format',
                'details' => $e->errors()
            ], 422);
            
        } catch (Exception $e) {
            Log::error('Failed to get verification status', [
                'error' => $e->getMessage(),
                'contract_address' => $request->input('contract_address')
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get verification status'
            ], 500);
        }
    }

    /**
     * Get verification badge HTML/JSON (API endpoint)
     */
    public function getBadge(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
                'format' => 'sometimes|string|in:html,json',
                'theme' => 'sometimes|string|in:light,dark',
                'size' => 'sometimes|string|in:small,medium,large'
            ]);
            
            $contractAddress = $validated['contract_address'];
            $format = $validated['format'] ?? 'json';
            $theme = $validated['theme'] ?? 'light';
            $size = $validated['size'] ?? 'medium';
            
            // Check if contract is verified
            $isVerified = in_array($contractAddress, [
                '0x1234567890123456789012345678901234567890',
                '0xA0b86a33E6441b93047e4e3b5c2B4d7e8A9B2C3D',
            ]);
            
            if (!$isVerified) {
                return response()->json([
                    'success' => false,
                    'error' => 'Contract is not verified',
                    'contract_address' => $contractAddress
                ], 404);
            }
            
            // Generate badge data
            $badgeData = [
                'contract_address' => $contractAddress,
                'verification_level' => 'standard',
                'verification_date' => now()->subDays(rand(1, 30))->toISOString(),
                'badge_url' => route('verification.badge', ['token' => 'demo_token_' . substr($contractAddress, 2, 8)]),
                'theme' => $theme,
                'size' => $size
            ];
            
            if ($format === 'html') {
                $badgeHtml = $this->verificationService->generateBadge(
                    $contractAddress,
                    ['theme' => $theme, 'size' => $size]
                );
                
                return response()->json([
                    'success' => true,
                    'format' => 'html',
                    'html' => $badgeHtml,
                    'embed_code' => $this->generateEmbedCode($badgeHtml),
                    'data' => $badgeData
                ]);
            }
            
            return response()->json([
                'success' => true,
                'format' => 'json',
                'data' => $badgeData
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid request parameters',
                'details' => $e->errors()
            ], 422);
            
        } catch (Exception $e) {
            Log::error('Failed to get verification badge', [
                'error' => $e->getMessage(),
                'contract_address' => $request->input('contract_address')
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate verification badge'
            ], 500);
        }
    }

    /**
     * Verify a contract using a signed token/URL
     */
    public function verify(string $token): Response
    {
        try {
            // Decode and validate the verification token
            $tokenData = $this->verificationService->verifyBadgeToken($token);
            
            if (!$tokenData || !($tokenData['valid'] ?? false)) {
                Log::warning('Invalid verification token used', [
                    'token' => substr($token, 0, 10) . '...',
                    'ip' => request()->ip()
                ]);
                
                return response()->view('errors.404', [
                    'message' => 'Invalid or expired verification token'
                ], 404);
            }
            
            // Extract contract information from token
            $contractAddress = $tokenData['contract_address'] ?? null;
            $verificationType = $tokenData['type'] ?? 'standard';
            $expiresAt = $tokenData['expires_at'] ?? null;
            
            // Check if token has expired
            if ($expiresAt && now()->isAfter($expiresAt)) {
                Log::info('Expired verification token used', [
                    'token' => substr($token, 0, 10) . '...',
                    'expired_at' => $expiresAt,
                    'contract_address' => $contractAddress
                ]);
                
                return response()->view('errors.404', [
                    'message' => 'Verification token has expired'
                ], 404);
            }
            
            // Log successful verification
            Log::info('Contract verification via token', [
                'contract_address' => $contractAddress,
                'verification_type' => $verificationType,
                'token' => substr($token, 0, 10) . '...',
                'user_id' => auth()->id()
            ]);
            
            // Generate verification result
            $verificationResult = [
                'success' => true,
                'contract_address' => $contractAddress,
                'verification_type' => $verificationType,
                'verified_at' => now()->toISOString(),
                'token_valid' => true
            ];
            
            // Return verification success page
            return response()->view('verification.success', [
                'contract_address' => $contractAddress,
                'verification_type' => $verificationType,
                'verification_result' => $verificationResult,
                'token' => $token
            ]);
            
        } catch (Exception $e) {
            Log::error('Contract verification failed', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->view('errors.500', [
                'message' => 'Contract verification failed'
            ], 500);
        }
    }
}