<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Jobs\SendOnboardingEmailJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

final class LiveAnalyzerOnboardingService
{
    public function __construct(
        private readonly OnboardingEmailService $onboardingService
    ) {}

    /**
     * Track when anonymous users use the live analyzer
     */
    public function trackAnonymousAnalysis(string $contractAddress, string $network, array $analysisData): void
    {
        $sessionId = session()->getId();
        $cacheKey = "live_analysis_{$sessionId}";
        
        $analyses = Cache::get($cacheKey, []);
        $analyses[] = [
            'contract_address' => $contractAddress,
            'network' => $network,
            'timestamp' => now()->toISOString(),
            'analysis_data' => $analysisData
        ];
        
        // Store for 24 hours
        Cache::put($cacheKey, $analyses, now()->addHours(24));
        
        Log::info('Anonymous live analysis tracked', [
            'session_id' => $sessionId,
            'contract' => $contractAddress,
            'network' => $network,
            'total_analyses' => count($analyses)
        ]);
    }

    /**
     * Start specialized onboarding for users who used live analyzer
     */
    public function startLiveAnalyzerOnboarding(User $user): void
    {
        if (!config('onboarding.enabled', true)) {
            return;
        }

        // Check if user has previous live analyzer usage
        $sessionId = session()->getId();
        $cacheKey = "live_analysis_{$sessionId}";
        $previousAnalyses = Cache::get($cacheKey, []);

        if (empty($previousAnalyses)) {
            // No previous live analyzer usage, use standard onboarding
            $this->onboardingService->startOnboardingSequence($user);
            return;
        }

        Log::info('Starting live analyzer onboarding sequence', [
            'user_id' => $user->id,
            'previous_analyses' => count($previousAnalyses)
        ]);

        // Send specialized welcome email immediately
        SendOnboardingEmailJob::dispatch(
            $user->id,
            'live_analyzer_welcome',
            [
                'previous_analyses' => $previousAnalyses,
                'analysis_count' => count($previousAnalyses)
            ]
        )->delay(now()->addMinutes(2));

        // Send getting started email with live analyzer context
        SendOnboardingEmailJob::dispatch(
            $user->id,
            'live_analyzer_next_steps',
            [
                'previous_analyses' => $previousAnalyses
            ]
        )->delay(now()->addHours(2));

        // Continue with standard sequence after 24 hours
        SendOnboardingEmailJob::dispatch($user->id, 'first_analysis')
            ->delay(now()->addHours(24));

        SendOnboardingEmailJob::dispatch($user->id, 'advanced_features')
            ->delay(now()->addDays(3));

        SendOnboardingEmailJob::dispatch($user->id, 'feedback')
            ->delay(now()->addDays(7));

        // Clear the cache since user is now registered
        Cache::forget($cacheKey);
    }

    /**
     * Create conversion email for users who register after using live analyzer
     */
    public function sendConversionEmail(User $user, array $analysisHistory): void
    {
        $mostAnalyzedNetwork = $this->getMostAnalyzedNetwork($analysisHistory);
        $riskContracts = $this->getHighRiskContracts($analysisHistory);
        
        SendOnboardingEmailJob::dispatch(
            $user->id,
            'live_analyzer_conversion',
            [
                'analysis_history' => $analysisHistory,
                'analysis_count' => count($analysisHistory),
                'most_analyzed_network' => $mostAnalyzedNetwork,
                'risk_contracts' => $riskContracts,
                'conversion_value' => $this->calculateConversionValue($analysisHistory)
            ]
        )->delay(now()->addMinutes(5));
    }

    /**
     * Get the most analyzed network from user's history
     */
    private function getMostAnalyzedNetwork(array $analysisHistory): string
    {
        $networks = array_column($analysisHistory, 'network');
        $networkCounts = array_count_values($networks);
        
        return array_key_first($networkCounts) ?? 'ethereum';
    }

    /**
     * Get high-risk contracts from analysis history
     */
    private function getHighRiskContracts(array $analysisHistory): array
    {
        return array_filter($analysisHistory, function ($analysis) {
            $riskScore = $analysis['analysis_data']['riskScore'] ?? 0;
            return $riskScore > 70; // High risk threshold
        });
    }

    /**
     * Calculate the value proposition for the user based on their analysis history
     */
    private function calculateConversionValue(array $analysisHistory): array
    {
        $totalContracts = count($analysisHistory);
        $totalFindings = 0;
        $criticalFindings = 0;
        $estimatedValue = 0;

        foreach ($analysisHistory as $analysis) {
            $findings = $analysis['analysis_data']['findings'] ?? [];
            $totalFindings += count($findings);
            
            foreach ($findings as $finding) {
                if (($finding['severity'] ?? '') === 'critical') {
                    $criticalFindings++;
                    $estimatedValue += 50000; // Estimated value of preventing critical vulnerability
                } elseif (($finding['severity'] ?? '') === 'high') {
                    $estimatedValue += 10000; // Estimated value of preventing high vulnerability
                }
            }
        }

        return [
            'total_contracts' => $totalContracts,
            'total_findings' => $totalFindings,
            'critical_findings' => $criticalFindings,
            'estimated_value_saved' => $estimatedValue,
            'time_saved_hours' => $totalContracts * 2, // Estimated 2 hours saved per contract
        ];
    }

    /**
     * Send immediate follow-up email to engaged live analyzer users
     */
    public function sendEngagementFollowUp(User $user, string $contractAddress, array $analysisResults): void
    {
        $isHighRisk = ($analysisResults['riskScore'] ?? 0) > 70;
        $hasCriticalFindings = !empty(array_filter(
            $analysisResults['findings'] ?? [],
            fn($finding) => ($finding['severity'] ?? '') === 'critical'
        ));

        if ($isHighRisk || $hasCriticalFindings) {
            // Send urgent security alert email
            SendOnboardingEmailJob::dispatch(
                $user->id,
                'security_alert',
                [
                    'contract_address' => $contractAddress,
                    'risk_score' => $analysisResults['riskScore'] ?? 0,
                    'critical_findings' => array_filter(
                        $analysisResults['findings'] ?? [],
                        fn($finding) => ($finding['severity'] ?? '') === 'critical'
                    ),
                    'urgent' => true
                ]
            )->delay(now()->addMinutes(10));
        } else {
            // Send positive reinforcement email
            SendOnboardingEmailJob::dispatch(
                $user->id,
                'analysis_success',
                [
                    'contract_address' => $contractAddress,
                    'analysis_results' => $analysisResults,
                    'next_steps' => $this->getNextStepsRecommendations($analysisResults)
                ]
            )->delay(now()->addHours(1));
        }
    }

    private function getNextStepsRecommendations(array $analysisResults): array
    {
        $recommendations = [];
        
        $gasOptimization = $analysisResults['gasOptimization'] ?? 0;
        if ($gasOptimization < 80) {
            $recommendations[] = 'gas_optimization';
        }
        
        $riskScore = $analysisResults['riskScore'] ?? 0;
        if ($riskScore > 50) {
            $recommendations[] = 'security_review';
        }
        
        $findingsCount = count($analysisResults['findings'] ?? []);
        if ($findingsCount > 5) {
            $recommendations[] = 'comprehensive_audit';
        }
        
        return $recommendations;
    }
}
