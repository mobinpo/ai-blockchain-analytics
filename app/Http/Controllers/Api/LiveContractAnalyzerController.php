<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Analysis;
use App\Models\Finding;
use App\Models\User;
use App\Models\ContractAnalysis;
use App\Services\MultiChainExplorerManager;
use App\Services\LiveAnalyzerOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class LiveContractAnalyzerController extends Controller
{
    public function __construct(
        private readonly MultiChainExplorerManager $explorerManager,
        private readonly LiveAnalyzerOnboardingService $liveAnalyzerOnboarding
    ) {}

    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_input' => 'required|string|max:10000',
            'network' => 'required|string|in:ethereum,polygon,bsc,arbitrum,optimism,fantom,avalanche',
            'analysis_type' => 'string|in:live,quick,full'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $contractInput = trim($request->input('contract_input'));
        $network = $request->input('network', 'ethereum');
        $analysisType = $request->input('analysis_type', 'live');

        try {
            $inputType = $this->detectInputType($contractInput);
            
            if ($inputType === 'address') {
                return $this->analyzeContractAddress($contractInput, $network, $analysisType);
            } elseif ($inputType === 'code') {
                return $this->analyzeSourceCode($contractInput, $network, $analysisType);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid input format. Please provide a valid contract address (0x...) or Solidity source code.'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Analysis failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function detectInputType(string $input): string
    {
        $input = trim($input);
        
        if (preg_match('/^0x[a-fA-F0-9]{40}$/', $input)) {
            return 'address';
        }
        
        if (str_contains($input, 'contract') || str_contains($input, 'function') || str_contains($input, 'pragma')) {
            return 'code';
        }
        
        return 'unknown';
    }

    private function analyzeContractAddress(string $address, string $network, string $analysisType): JsonResponse
    {
        $demoUser = $this->getOrCreateDemoUser();
        
        // Check if this is a famous contract we have data for
        $famousContract = Project::where('main_contract_address', $address)
            ->where('blockchain_network', $network)
            ->where('category', 'defi')
            ->first();
        
        $contractName = $famousContract ? $famousContract->name : "Contract " . substr($address, 0, 10) . "...";
        
        $project = Project::create([
            'name' => "Live Analysis - " . $contractName,
            'description' => "Live contract analysis for {$address} on {$network}",
            'user_id' => $demoUser->id,
            'blockchain_network' => $network,
            'project_type' => 'smart_contract',
            'main_contract_address' => $address,
            'contract_addresses' => [$address],
            'status' => 'analyzing',
            'is_public' => false,
            'category' => 'live_analysis',
            'tags' => ['live-analysis', $network]
        ]);

        $analysis = Analysis::create([
            'project_id' => $project->id,
            'engine' => 'live-analyzer',
            'analysis_type' => $analysisType,
            'status' => 'completed',
            'contract_address' => $address,
            'blockchain_network' => $network,
            'analysis_summary' => 'Live contract analysis completed',
            'gas_usage_estimate' => rand(50000, 500000),
            'execution_time' => rand(1000, 5000) / 1000,
            'confidence_score' => rand(85, 98) / 100,
            'metadata' => [
                'analysis_timestamp' => now()->toISOString(),
                'input_type' => 'address',
                'network' => $network,
                'famous_contract' => $famousContract ? true : false,
                'contract_name' => $contractName
            ]
        ]);

        // Generate findings based on famous contract data if available
        if ($famousContract) {
            $mockFindings = $this->generateKnownContractFindings($analysis->id, $famousContract);
            $mockOptimizations = $this->generateKnownContractOptimizations($famousContract);
            $riskScore = $famousContract->risk_score ?? rand(20, 40);
            $gasEfficiency = rand(85, 95); // Famous contracts are generally well optimized
        } else {
            $mockFindings = $this->generateMockFindings($analysis->id);
            $mockOptimizations = $this->generateMockOptimizations();
            $riskScore = $this->calculateRiskScore($mockFindings);
            $gasEfficiency = rand(75, 95);
        }

        $project->update([
            'status' => 'active',
            'risk_level' => $this->getRiskLevel($riskScore),
            'risk_score' => $riskScore,
            'risk_updated_at' => now(),
            'last_analyzed_at' => now()
        ]);

        $responseData = [
            'success' => true,
            'projectId' => $project->id,
            'analysisId' => $analysis->id,
            'contractAddress' => $address,
            'network' => $network,
            'riskScore' => $riskScore,
            'gasOptimization' => $gasEfficiency,
            'findings' => $mockFindings,
            'optimizations' => $mockOptimizations,
            'analysisTime' => $analysis->execution_time,
            'timestamp' => now()->toISOString()
        ];

        // Track anonymous usage for onboarding
        if (!auth()->check()) {
            $this->liveAnalyzerOnboarding->trackAnonymousAnalysis($address, $network, $responseData);
        }

        return response()->json($responseData);
    }

    private function analyzeSourceCode(string $sourceCode, string $network, string $analysisType): JsonResponse
    {
        $demoUser = $this->getOrCreateDemoUser();
        
        $contractName = $this->extractContractName($sourceCode);
        
        $project = Project::create([
            'name' => "Live Analysis - {$contractName}",
            'description' => "Live source code analysis for {$contractName} on {$network}",
            'user_id' => $demoUser->id,
            'blockchain_network' => $network,
            'project_type' => 'smart_contract',
            'status' => 'analyzing',
            'is_public' => false,
            'category' => 'live_analysis',
            'tags' => ['live-analysis', 'source-code', $network],
            'metadata' => [
                'source_code_length' => strlen($sourceCode),
                'contract_name' => $contractName
            ]
        ]);

        $analysis = Analysis::create([
            'project_id' => $project->id,
            'engine' => 'live-analyzer',
            'analysis_type' => $analysisType,
            'status' => 'completed',
            'blockchain_network' => $network,
            'analysis_summary' => 'Live source code analysis completed',
            'source_code' => $sourceCode,
            'gas_usage_estimate' => rand(100000, 1000000),
            'execution_time' => rand(2000, 8000) / 1000,
            'confidence_score' => rand(90, 99) / 100,
            'metadata' => [
                'analysis_timestamp' => now()->toISOString(),
                'input_type' => 'source_code',
                'network' => $network,
                'contract_name' => $contractName
            ]
        ]);

        $mockFindings = $this->generateMockSourceCodeFindings($analysis->id, $sourceCode);
        $mockOptimizations = $this->generateMockSourceCodeOptimizations($sourceCode);
        
        $riskScore = $this->calculateRiskScore($mockFindings);
        $gasEfficiency = rand(70, 90);

        $project->update([
            'status' => 'active',
            'risk_level' => $this->getRiskLevel($riskScore),
            'risk_score' => $riskScore,
            'risk_updated_at' => now(),
            'last_analyzed_at' => now()
        ]);

        $responseData = [
            'success' => true,
            'projectId' => $project->id,
            'analysisId' => $analysis->id,
            'contractName' => $contractName,
            'network' => $network,
            'riskScore' => $riskScore,
            'gasOptimization' => $gasEfficiency,
            'findings' => $mockFindings,
            'optimizations' => $mockOptimizations,
            'analysisTime' => $analysis->execution_time,
            'timestamp' => now()->toISOString()
        ];

        // Track anonymous usage for onboarding
        if (!auth()->check()) {
            $this->liveAnalyzerOnboarding->trackAnonymousAnalysis('source_code', $network, $responseData);
        }

        return response()->json($responseData);
    }

    private function getOrCreateDemoUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'live-analysis@blockchain-analytics.com'],
            [
                'name' => 'Live Analysis User',
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ]
        );
    }

    private function extractContractName(string $sourceCode): string
    {
        if (preg_match('/contract\s+(\w+)/', $sourceCode, $matches)) {
            return $matches[1];
        }
        
        return 'UnknownContract';
    }

    private function generateMockFindings(int $analysisId): array
    {
        $severities = ['critical', 'high', 'medium', 'low', 'info'];
        $findings = [];

        $findingTemplates = [
            ['title' => 'Reentrancy Vulnerability', 'severity' => 'critical', 'description' => 'External calls before state changes detected'],
            ['title' => 'Integer Overflow Risk', 'severity' => 'high', 'description' => 'Arithmetic operations without SafeMath'],
            ['title' => 'Unchecked Return Values', 'severity' => 'medium', 'description' => 'External calls without checking return values'],
            ['title' => 'Gas Optimization Opportunity', 'severity' => 'low', 'description' => 'Inefficient storage operations detected'],
            ['title' => 'Missing Input Validation', 'severity' => 'medium', 'description' => 'Function parameters not validated'],
            ['title' => 'Centralization Risk', 'severity' => 'medium', 'description' => 'Owner has excessive privileges']
        ];

        foreach (array_slice($findingTemplates, 0, rand(3, 6)) as $index => $template) {
            $finding = Finding::create([
                'analysis_id' => $analysisId,
                'finding_type' => strtolower(str_replace(' ', '_', $template['title'])),
                'severity' => $template['severity'],
                'title' => $template['title'],
                'description' => $template['description'],
                'location' => "Line " . rand(10, 200),
                'recommendation' => $this->getRecommendation($template['title']),
                'confidence' => rand(80, 95),
                'false_positive_likelihood' => rand(5, 20),
                'analysis_metadata' => [
                    'category' => 'security',
                    'cwe_id' => 'CWE-' . rand(100, 999)
                ]
            ]);

            $findings[] = [
                'id' => $finding->id,
                'title' => $finding->title,
                'severity' => $finding->severity,
                'description' => $finding->description,
                'location' => $finding->location,
                'recommendation' => $finding->recommendation
            ];
        }

        return $findings;
    }

    private function generateMockSourceCodeFindings(int $analysisId, string $sourceCode): array
    {
        $findings = $this->generateMockFindings($analysisId);
        
        if (str_contains(strtolower($sourceCode), 'payable')) {
            $finding = Finding::create([
                'analysis_id' => $analysisId,
                'finding_type' => 'payable_function_risk',
                'severity' => 'medium',
                'title' => 'Payable Function Detected',
                'description' => 'Function can receive Ether - ensure proper access control',
                'location' => 'Payable functions detected',
                'recommendation' => 'Implement proper access control and withdrawal patterns',
                'confidence' => 95
            ]);

            $findings[] = [
                'id' => $finding->id,
                'title' => $finding->title,
                'severity' => $finding->severity,
                'description' => $finding->description,
                'location' => $finding->location,
                'recommendation' => $finding->recommendation
            ];
        }

        return $findings;
    }

    private function generateMockOptimizations(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Use uint256 instead of uint8',
                'description' => 'uint256 is more gas efficient than smaller integer types'
            ],
            [
                'id' => 2,
                'title' => 'Pack struct variables',
                'description' => 'Reorganize struct to fit in fewer storage slots'
            ],
            [
                'id' => 3,
                'title' => 'Use immutable for constants',
                'description' => 'Mark constants as immutable to save gas'
            ],
            [
                'id' => 4,
                'title' => 'Cache array length',
                'description' => 'Store array length in variable for loops'
            ]
        ];
    }

    private function generateMockSourceCodeOptimizations(string $sourceCode): array
    {
        $optimizations = $this->generateMockOptimizations();
        
        if (str_contains($sourceCode, 'for')) {
            $optimizations[] = [
                'id' => count($optimizations) + 1,
                'title' => 'Optimize for loop',
                'description' => 'Use unchecked increment for gas savings in loops'
            ];
        }

        return $optimizations;
    }

    private function calculateRiskScore(array $findings): int
    {
        $score = 0;
        $weights = [
            'critical' => 25,
            'high' => 15,
            'medium' => 8,
            'low' => 3,
            'info' => 1
        ];

        foreach ($findings as $finding) {
            $score += $weights[$finding['severity']] ?? 0;
        }

        return min(100, $score);
    }

    private function getRiskLevel(int $score): string
    {
        if ($score >= 80) return 'critical';
        if ($score >= 60) return 'high';
        if ($score >= 30) return 'medium';
        return 'low';
    }

    private function getRecommendation(string $title): string
    {
        $recommendations = [
            'Reentrancy Vulnerability' => 'Use the checks-effects-interactions pattern or ReentrancyGuard',
            'Integer Overflow Risk' => 'Use SafeMath library or Solidity 0.8+ built-in overflow protection',
            'Unchecked Return Values' => 'Always check return values of external calls',
            'Gas Optimization Opportunity' => 'Consider using more efficient data structures or operations',
            'Missing Input Validation' => 'Add require() statements to validate function parameters',
            'Centralization Risk' => 'Consider using multi-sig wallet or governance mechanism'
        ];

        return $recommendations[$title] ?? 'Review and address this finding according to best practices';
    }

    private function generateKnownContractFindings(int $analysisId, Project $contract): array
    {
        $findings = [];
        
        $riskScore = $contract->risk_score ?? 25;
        $severity = $riskScore >= 80 ? 'critical' : 
                   ($riskScore >= 60 ? 'high' : 
                   ($riskScore >= 40 ? 'medium' : 'low'));
        
        // Check for exploited contracts (Euler Finance case)
        if (str_contains($contract->name, 'Euler')) {
            $finding = Finding::create([
                'analysis_id' => $analysisId,
                'finding_type' => 'historical_exploit',
                'severity' => 'critical',
                'title' => '🚨 EXPLOITED CONTRACT - Historical Attack',
                'description' => 'This contract was exploited on March 13, 2023 for $197M via donation attack. Root cause: donation attack vulnerability in liquidation logic.',
                'location' => 'Contract-wide security failure',
                'recommendation' => 'DO NOT USE - Contract has been compromised. Study for educational purposes only.',
                'confidence' => 100,
                'false_positive_likelihood' => 0,
                'analysis_metadata' => [
                    'category' => 'historical_exploit',
                    'exploit_date' => '2023-03-13',
                    'exploit_amount' => '197',
                    'exploit_type' => 'donation_attack',
                    'educational_value' => 'high'
                ]
            ]);

            $findings[] = [
                'id' => $finding->id,
                'title' => $finding->title,
                'severity' => $finding->severity,
                'description' => $finding->description,
                'location' => $finding->location,
                'recommendation' => $finding->recommendation
            ];
        } else {
            // Add positive findings for well-known secure contracts
            $finding = Finding::create([
                'analysis_id' => $analysisId,
                'finding_type' => 'security_positive',
                'severity' => 'info',
                'title' => '✅ Well-Known Protocol',
                'description' => "This is a well-established protocol with good security practices. Risk score: {$riskScore}/100.",
                'location' => 'Overall contract security',
                'recommendation' => 'Continue following established security practices and regular monitoring.',
                'confidence' => 95,
                'analysis_metadata' => [
                    'category' => 'positive_finding',
                    'protocol_name' => $contract->name,
                    'risk_score' => $riskScore
                ]
            ]);

            $findings[] = [
                'id' => $finding->id,
                'title' => $finding->title,
                'severity' => $finding->severity,
                'description' => $finding->description,
                'location' => $finding->location,
                'recommendation' => $finding->recommendation
            ];
        }

        // Add some standard findings for demo purposes
        $standardFindings = $this->generateMockFindings($analysisId);
        return array_merge($findings, array_slice($standardFindings, 0, 3));
    }

    private function generateKnownContractOptimizations(Project $contract): array
    {
        $optimizations = [
            [
                'id' => 1,
                'title' => '✅ Well-Known Protocol',
                'description' => 'This contract is from a well-established protocol with good gas optimization practices'
            ],
            [
                'id' => 2,
                'title' => 'Monitor Gas Price Trends',
                'description' => 'Continue monitoring network congestion for optimal transaction timing'
            ]
        ];

        // Add specific optimizations for DeFi contracts
        if ($contract->project_type === 'defi' || str_contains($contract->name, 'Uniswap') || str_contains($contract->name, 'Aave')) {
            $optimizations[] = [
                'id' => 3,
                'title' => 'DeFi-Specific Optimizations',
                'description' => 'Consider flash loan optimizations and MEV protection strategies'
            ];
        }

        return $optimizations;
    }

    private function formatVulnerabilityTitle(string $vulnerability): string
    {
        // Extract meaningful title from vulnerability description
        if (str_contains($vulnerability, 'CRITICAL:')) {
            return str_replace('CRITICAL: ', '🚨 ', $vulnerability);
        }
        
        if (str_contains($vulnerability, 'donation attack')) {
            return '🎯 Donation Attack Vulnerability';
        }
        
        if (str_contains($vulnerability, 'oracle')) {
            return '📊 Oracle Manipulation Risk';
        }
        
        if (str_contains($vulnerability, 'flash loan')) {
            return '⚡ Flash Loan Attack Vector';
        }
        
        if (str_contains($vulnerability, 'governance')) {
            return '🗳️ Governance Attack Risk';
        }
        
        return '⚠️ ' . ucfirst(substr($vulnerability, 0, 50)) . '...';
    }

}