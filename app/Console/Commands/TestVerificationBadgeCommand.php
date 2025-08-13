<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VerificationBadgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Test Verification Badge System
 * 
 * Comprehensive testing command for the verification badge functionality
 */
final class TestVerificationBadgeCommand extends Command
{
    protected $signature = 'verification:test-badge 
                           {--type=contract : Type of badge to test (contract, user, analysis)}
                           {--id=test-entity : Entity ID for testing}
                           {--revoke : Test badge revocation}
                           {--performance : Run performance tests}
                           {--all : Run all tests}';

    protected $description = 'Test the verification badge system with SHA-256 + HMAC signatures';

    public function __construct(
        private readonly VerificationBadgeService $verificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔐 Testing Verification Badge System');
        $this->newLine();

        try {
            if ($this->option('all')) {
                return $this->runAllTests();
            }

            if ($this->option('performance')) {
                return $this->runPerformanceTests();
            }

            if ($this->option('revoke')) {
                return $this->testRevocation();
            }

            return $this->runBasicTests();

        } catch (\Exception $e) {
            $this->error('❌ Test failed with exception: ' . $e->getMessage());
            Log::error('Verification badge test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Run all comprehensive tests
     */
    private function runAllTests(): int
    {
        $this->info('🚀 Running comprehensive verification badge tests...');
        $this->newLine();

        $tests = [
            'Basic Badge Generation' => fn() => $this->testBasicGeneration(),
            'Contract Badge' => fn() => $this->testContractBadge(),
            'User Badge' => fn() => $this->testUserBadge(),
            'Analysis Badge' => fn() => $this->testAnalysisBadge(),
            'Signature Verification' => fn() => $this->testSignatureVerification(),
            'Expiry Handling' => fn() => $this->testExpiryHandling(),
            'Error Cases' => fn() => $this->testErrorCases(),
            'Revocation' => fn() => $this->testRevocation(),
            'Performance' => fn() => $this->runPerformanceTests()
        ];

        $passed = 0;
        $total = count($tests);

        foreach ($tests as $testName => $testFunction) {
            $this->info("Testing: {$testName}");
            
            try {
                $result = $testFunction();
                if ($result === true) {
                    $this->info("✅ {$testName} - PASSED");
                    $passed++;
                } else {
                    $this->error("❌ {$testName} - FAILED");
                }
            } catch (\Exception $e) {
                $this->error("❌ {$testName} - ERROR: " . $e->getMessage());
            }
            
            $this->newLine();
        }

        $this->info("📊 Test Results: {$passed}/{$total} tests passed");
        
        return $passed === $total ? 0 : 1;
    }

    /**
     * Run basic tests
     */
    private function runBasicTests(): int
    {
        $entityType = $this->option('type');
        $entityId = $this->option('id');

        $this->info("Testing {$entityType} badge for entity: {$entityId}");
        $this->newLine();

        // Generate badge
        $this->info('1. Generating verification badge...');
        $badgeData = $this->verificationService->generateBadgeUrl(
            $entityType,
            $entityId,
            ['test' => true, 'command_generated' => now()->toISOString()],
            now()->addHours(1),
            'test_verified'
        );

        $this->info('✅ Badge generated successfully');
        $this->displayBadgeInfo($badgeData);

        // Verify badge
        $this->info('2. Verifying badge token...');
        $verificationResult = $this->verificationService->verifyBadgeToken($badgeData['token']);

        if ($verificationResult['valid']) {
            $this->info('✅ Badge verification successful');
            $this->displayVerificationInfo($verificationResult);
        } else {
            $this->error('❌ Badge verification failed: ' . $verificationResult['error']);
            return 1;
        }

        return 0;
    }

    /**
     * Test basic badge generation
     */
    private function testBasicGeneration(): bool
    {
        $badgeData = $this->verificationService->generateBadgeUrl(
            'test',
            'test-entity-123',
            ['test' => true],
            now()->addHours(1),
            'test_verified'
        );

        return isset($badgeData['token']) && 
               isset($badgeData['badge_url']) && 
               isset($badgeData['verification_url']);
    }

    /**
     * Test contract badge generation
     */
    private function testContractBadge(): bool
    {
        $contractAddress = '0x1234567890abcdef1234567890abcdef12345678';
        $analysisResults = [
            'security_score' => 85,
            'vulnerabilities_found' => 2,
            'gas_efficiency' => 'high'
        ];

        $badgeData = $this->verificationService->generateContractBadge(
            $contractAddress,
            'ethereum',
            $analysisResults,
            'security_verified'
        );

        $verificationResult = $this->verificationService->verifyBadgeToken($badgeData['token']);

        return $verificationResult['valid'] && 
               $verificationResult['entity_type'] === 'contract' &&
               $verificationResult['entity_id'] === $contractAddress;
    }

    /**
     * Test user badge generation
     */
    private function testUserBadge(): bool
    {
        $userId = 'user-123';
        $credentials = [
            'github_verified' => true,
            'email_verified' => true,
            'contracts_deployed' => 5
        ];

        $badgeData = $this->verificationService->generateUserBadge(
            $userId,
            $credentials,
            'developer_verified'
        );

        $verificationResult = $this->verificationService->verifyBadgeToken($badgeData['token']);

        return $verificationResult['valid'] && 
               $verificationResult['entity_type'] === 'user' &&
               $verificationResult['badge_type'] === 'developer_verified';
    }

    /**
     * Test analysis badge generation
     */
    private function testAnalysisBadge(): bool
    {
        $analysisId = 'analysis-456';
        $analysisData = [
            'confidence_score' => 0.95,
            'engine' => 'ai_blockchain_analytics',
            'findings' => ['high_gas_usage', 'reentrancy_risk']
        ];

        $badgeData = $this->verificationService->generateAnalysisBadge(
            $analysisId,
            $analysisData,
            'analysis_verified'
        );

        $verificationResult = $this->verificationService->verifyBadgeToken($badgeData['token']);

        return $verificationResult['valid'] && 
               $verificationResult['entity_type'] === 'analysis';
    }

    /**
     * Test signature verification
     */
    private function testSignatureVerification(): bool
    {
        // Generate a badge
        $badgeData = $this->verificationService->generateBadgeUrl(
            'test',
            'signature-test',
            ['test' => 'signature_verification']
        );

        // Verify original token
        $originalResult = $this->verificationService->verifyBadgeToken($badgeData['token']);
        if (!$originalResult['valid']) {
            return false;
        }

        // Test tampered token
        $tamperedToken = str_replace('A', 'B', $badgeData['token']);
        $tamperedResult = $this->verificationService->verifyBadgeToken($tamperedToken);
        
        // Tampered token should be invalid
        return !$tamperedResult['valid'];
    }

    /**
     * Test expiry handling
     */
    private function testExpiryHandling(): bool
    {
        // Test expired badge (expires 1 second ago)
        $expiredBadge = $this->verificationService->generateBadgeUrl(
            'test',
            'expiry-test',
            ['test' => 'expiry'],
            Carbon::now()->subSecond()
        );

        // Wait a moment to ensure expiry
        sleep(1);

        $expiredResult = $this->verificationService->verifyBadgeToken($expiredBadge['token']);
        
        return !$expiredResult['valid'] && $expiredResult['error_code'] === 'EXPIRED';
    }

    /**
     * Test error cases
     */
    private function testErrorCases(): bool
    {
        // Test invalid token format
        $invalidResult = $this->verificationService->verifyBadgeToken('invalid-token');
        if ($invalidResult['valid']) {
            return false;
        }

        // Test empty token
        $emptyResult = $this->verificationService->verifyBadgeToken('');
        if ($emptyResult['valid']) {
            return false;
        }

        // Test malformed base64
        $malformedResult = $this->verificationService->verifyBadgeToken('not@base64!');
        if ($malformedResult['valid']) {
            return false;
        }

        return true;
    }

    /**
     * Test revocation functionality
     */
    private function testRevocation(): bool
    {
        $this->info('Testing badge revocation...');

        // Generate a badge
        $badgeData = $this->verificationService->generateBadgeUrl(
            'test',
            'revocation-test',
            ['test' => 'revocation']
        );

        // Verify it's initially valid
        $initialResult = $this->verificationService->verifyBadgeToken($badgeData['token']);
        if (!$initialResult['valid']) {
            $this->error('Badge should be initially valid');
            return false;
        }

        // Revoke the badge
        $revoked = $this->verificationService->revokeBadge($badgeData['token'], 'Test revocation');
        if (!$revoked) {
            $this->error('Failed to revoke badge');
            return false;
        }

        // Verify it's now revoked
        $revokedCheck = $this->verificationService->isBadgeRevoked($badgeData['token']);
        if (!$revokedCheck['revoked']) {
            $this->error('Badge should be marked as revoked');
            return false;
        }

        $this->info('✅ Revocation test passed');
        return true;
    }

    /**
     * Run performance tests
     */
    private function runPerformanceTests(): int
    {
        $this->info('🚀 Running performance tests...');
        $this->newLine();

        $iterations = 100;
        
        // Test badge generation performance
        $this->info("Testing badge generation ({$iterations} iterations)...");
        $startTime = microtime(true);
        
        $tokens = [];
        for ($i = 0; $i < $iterations; $i++) {
            $badgeData = $this->verificationService->generateBadgeUrl(
                'test',
                "perf-test-{$i}",
                ['iteration' => $i]
            );
            $tokens[] = $badgeData['token'];
        }
        
        $generationTime = microtime(true) - $startTime;
        $avgGenerationTime = ($generationTime / $iterations) * 1000; // milliseconds
        
        $this->info(sprintf("✅ Generation: %.3fs total, %.2fms average", $generationTime, $avgGenerationTime));

        // Test verification performance
        $this->info("Testing badge verification ({$iterations} iterations)...");
        $startTime = microtime(true);
        
        $validCount = 0;
        foreach ($tokens as $token) {
            $result = $this->verificationService->verifyBadgeToken($token);
            if ($result['valid']) {
                $validCount++;
            }
        }
        
        $verificationTime = microtime(true) - $startTime;
        $avgVerificationTime = ($verificationTime / $iterations) * 1000; // milliseconds
        
        $this->info(sprintf("✅ Verification: %.3fs total, %.2fms average", $verificationTime, $avgVerificationTime));
        $this->info("✅ Valid badges: {$validCount}/{$iterations}");

        // Performance benchmarks
        $this->newLine();
        $this->info('📈 Performance Summary:');
        $this->table(
            ['Operation', 'Total Time', 'Average Time', 'Rate'],
            [
                ['Generation', sprintf("%.3fs", $generationTime), sprintf("%.2fms", $avgGenerationTime), round($iterations / $generationTime) . '/s'],
                ['Verification', sprintf("%.3fs", $verificationTime), sprintf("%.2fms", $avgVerificationTime), round($iterations / $verificationTime) . '/s']
            ]
        );

        return 0;
    }

    /**
     * Display badge information
     */
    private function displayBadgeInfo(array $badgeData): void
    {
        $this->table(
            ['Property', 'Value'],
            [
                ['Badge URL', $badgeData['badge_url']],
                ['Verification URL', $badgeData['verification_url']],
                ['Token Preview', substr($badgeData['token'], 0, 50) . '...'],
                ['Expires At', $badgeData['expires_at']],
                ['Algorithm', $badgeData['metadata']['signature_algorithm']]
            ]
        );
    }

    /**
     * Display verification information
     */
    private function displayVerificationInfo(array $verificationResult): void
    {
        $this->table(
            ['Property', 'Value'],
            [
                ['Valid', $verificationResult['valid'] ? 'Yes' : 'No'],
                ['Entity Type', $verificationResult['entity_type'] ?? 'N/A'],
                ['Entity ID', $verificationResult['entity_id'] ?? 'N/A'],
                ['Badge Type', $verificationResult['badge_type'] ?? 'N/A'],
                ['Issued At', $verificationResult['issued_at'] ?? 'N/A'],
                ['Expires At', $verificationResult['expires_at'] ?? 'N/A'],
                ['Verification Level', $verificationResult['metadata']['verification_level'] ?? 'N/A']
            ]
        );
    }
}
