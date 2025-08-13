<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FamousContract;
use App\Models\ContractAnalysis;
use App\Models\Analysis;
use App\Models\Finding;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Famous5ContractsSeeder extends Seeder
{
    /**
     * Seed the database with 5 famous contracts
     * Including Uniswap V3, Aave V3, and recent exploits
     */
    public function run(): void
    {
        Log::info('🌱 Starting Famous 5 Contracts Seeding...');
        
        DB::beginTransaction();
        
        try {
            // Clear existing data
            $this->clearExistingData();
            
            // Seed the 5 famous contracts
            $contracts = $this->getFamous5Contracts();
            
            foreach ($contracts as $contractData) {
                $this->seedContract($contractData);
            }
            
            DB::commit();
            
            $this->command->info('✅ Successfully seeded 5 famous contracts with comprehensive analysis data');
            Log::info('✅ Famous 5 Contracts seeding completed successfully');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Famous 5 Contracts seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Clear existing data
     */
    private function clearExistingData(): void
    {
        $this->command->info('🧹 Clearing existing famous contracts data...');
        
        FamousContract::truncate();
        ContractAnalysis::truncate();
        
        // Also clear related analysis data
        Analysis::where('contract_address', 'LIKE', '0x%')->delete();
        Finding::whereHas('analysis', function($query) {
            $query->where('contract_address', 'LIKE', '0x%');
        })->delete();
        
        $this->command->info('✅ Existing data cleared');
    }
    
    /**
     * Get the 5 famous contracts data
     */
    private function getFamous5Contracts(): array
    {
        return [
            // 1. Uniswap V3 SwapRouter - Most popular DEX
            [
                'name' => 'Uniswap V3 SwapRouter',
                'address' => '0xE592427A0AEce92De3Edee1F18E0157C05861564',
                'network' => 'ethereum',
                'type' => 'defi',
                'category' => 'dex',
                'description' => 'Uniswap V3 SwapRouter - the most popular decentralized exchange router for token swaps',
                'tvl' => 4200000000, // $4.2B TVL
                'status' => 'active',
                'risk_score' => 15, // Very low risk
                'security_rating' => 'A+',
                'verification_status' => 'verified',
                'deployment_date' => '2021-05-05',
                'findings_count' => 2,
                'gas_optimization_score' => 92,
                'has_vulnerabilities' => false,
                'audit_reports' => ['Trail of Bits', 'Consensys Diligence', 'ABDK'],
                'github_url' => 'https://github.com/Uniswap/v3-periphery',
                'findings' => [
                    [
                        'title' => 'Gas Optimization Opportunity',
                        'severity' => 'low',
                        'category' => 'gas_optimization',
                        'description' => 'Minor gas optimization possible in multicall function',
                        'recommendation' => 'Consider batching operations more efficiently',
                        'line_number' => 156,
                        'confidence' => 0.7
                    ],
                    [
                        'title' => 'Front-Running Protection',
                        'severity' => 'info',
                        'category' => 'best_practices',
                        'description' => 'Implements deadline protection against MEV attacks',
                        'recommendation' => 'Excellent implementation of front-running protection',
                        'line_number' => null,
                        'confidence' => 1.0
                    ]
                ]
            ],
            
            // 2. Aave V3 Pool - Leading lending protocol
            [
                'name' => 'Aave V3 Pool',
                'address' => '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
                'network' => 'ethereum',
                'type' => 'defi',
                'category' => 'lending',
                'description' => 'Aave V3 Pool contract - leading decentralized lending and borrowing protocol',
                'tvl' => 6800000000, // $6.8B TVL
                'status' => 'active',
                'risk_score' => 25,
                'security_rating' => 'A',
                'verification_status' => 'verified',
                'deployment_date' => '2022-03-16',
                'findings_count' => 3,
                'gas_optimization_score' => 88,
                'has_vulnerabilities' => false,
                'audit_reports' => ['OpenZeppelin', 'SigmaPrime', 'Consensys', 'Certora'],
                'github_url' => 'https://github.com/aave/aave-v3-core',
                'findings' => [
                    [
                        'title' => 'Liquidation Logic Complexity',
                        'severity' => 'medium',
                        'category' => 'code_quality',
                        'description' => 'Complex liquidation logic requires careful monitoring',
                        'recommendation' => 'Consider additional testing for edge cases',
                        'line_number' => 234,
                        'confidence' => 0.8
                    ],
                    [
                        'title' => 'Interest Rate Model',
                        'severity' => 'low',
                        'category' => 'economic',
                        'description' => 'Sophisticated interest rate calculation model',
                        'recommendation' => 'Monitor rate model performance under stress',
                        'line_number' => 145,
                        'confidence' => 0.6
                    ],
                    [
                        'title' => 'Access Control Implementation',
                        'severity' => 'info',
                        'category' => 'security',
                        'description' => 'Robust role-based access control system',
                        'recommendation' => 'Excellent security architecture',
                        'line_number' => null,
                        'confidence' => 1.0
                    ]
                ]
            ],
            
            // 3. Euler Finance - Major exploit (March 2023)
            [
                'name' => 'Euler Finance (EXPLOITED)',
                'address' => '0x27182842E098f60e3D576794A5bFFb0777E025d3',
                'network' => 'ethereum',
                'type' => 'defi',
                'category' => 'lending',
                'description' => 'Euler Finance lending protocol - suffered $200M exploit in March 2023',
                'tvl' => 0, // TVL zeroed after exploit
                'status' => 'exploited',
                'risk_score' => 95, // Critical risk after exploit
                'security_rating' => 'F',
                'verification_status' => 'verified',
                'deployment_date' => '2021-08-20',
                'findings_count' => 8,
                'gas_optimization_score' => 65,
                'has_vulnerabilities' => true,
                'audit_reports' => ['Halborn', 'Solidified'],
                'github_url' => 'https://github.com/euler-xyz/euler-contracts',
                'exploit_date' => '2023-03-13',
                'exploit_amount' => 200000000, // $200M
                'findings' => [
                    [
                        'title' => 'Donation Attack Vulnerability',
                        'severity' => 'critical',
                        'category' => 'vulnerability',
                        'description' => 'Vulnerable to donation attacks on empty markets allowing price manipulation',
                        'recommendation' => 'Implement minimum liquidity requirements and donation attack protection',
                        'line_number' => 187,
                        'confidence' => 1.0
                    ],
                    [
                        'title' => 'Liquidity Check Bypass',
                        'severity' => 'critical',
                        'category' => 'vulnerability', 
                        'description' => 'Self-liquidation allows bypassing liquidity checks',
                        'recommendation' => 'Prevent self-liquidation or add additional checks',
                        'line_number' => 298,
                        'confidence' => 1.0
                    ],
                    [
                        'title' => 'Flash Loan Integration Risk',
                        'severity' => 'high',
                        'category' => 'integration',
                        'description' => 'Flash loan integration increases attack surface',
                        'recommendation' => 'Add flash loan specific protections',
                        'line_number' => 456,
                        'confidence' => 0.9
                    ],
                    [
                        'title' => 'Price Oracle Manipulation',
                        'severity' => 'high',
                        'category' => 'oracle',
                        'description' => 'Price oracle susceptible to manipulation in thin markets',
                        'recommendation' => 'Implement TWAP oracles and circuit breakers',
                        'line_number' => 123,
                        'confidence' => 0.95
                    ],
                    [
                        'title' => 'Reentrancy in Liquidation',
                        'severity' => 'medium',
                        'category' => 'reentrancy',
                        'description' => 'Potential reentrancy during liquidation process',
                        'recommendation' => 'Add reentrancy guards to liquidation functions',
                        'line_number' => 567,
                        'confidence' => 0.8
                    ],
                    [
                        'title' => 'Insufficient Input Validation',
                        'severity' => 'medium',
                        'category' => 'input_validation',
                        'description' => 'Some functions lack proper input validation',
                        'recommendation' => 'Add comprehensive input validation',
                        'line_number' => 89,
                        'confidence' => 0.7
                    ],
                    [
                        'title' => 'Gas Optimization Issues',
                        'severity' => 'low',
                        'category' => 'gas_optimization',
                        'description' => 'Several functions could be optimized for gas efficiency',
                        'recommendation' => 'Optimize storage operations and loops',
                        'line_number' => 234,
                        'confidence' => 0.6
                    ],
                    [
                        'title' => 'Event Emission Gaps',
                        'severity' => 'low',
                        'category' => 'monitoring',
                        'description' => 'Missing events for critical state changes',
                        'recommendation' => 'Add comprehensive event emission',
                        'line_number' => 345,
                        'confidence' => 0.5
                    ]
                ]
            ],
            
            // 4. Compound V3 - New architecture lending
            [
                'name' => 'Compound V3 Comet',
                'address' => '0xc3d688B66703497DAA19211EEdff47f25384cdc3',
                'network' => 'ethereum',
                'type' => 'defi',
                'category' => 'lending',
                'description' => 'Compound V3 (Comet) - next generation lending protocol with improved architecture',
                'tvl' => 1200000000, // $1.2B TVL
                'status' => 'active',
                'risk_score' => 35,
                'security_rating' => 'B+',
                'verification_status' => 'verified',
                'deployment_date' => '2022-08-26',
                'findings_count' => 4,
                'gas_optimization_score' => 85,
                'has_vulnerabilities' => false,
                'audit_reports' => ['OpenZeppelin', 'ChainSecurity', 'Code4rena'],
                'github_url' => 'https://github.com/compound-finance/comet',
                'findings' => [
                    [
                        'title' => 'Single Collateral Design',
                        'severity' => 'medium',
                        'category' => 'architecture',
                        'description' => 'Single collateral per market design limits composability',
                        'recommendation' => 'Consider multi-collateral support in future versions',
                        'line_number' => null,
                        'confidence' => 0.7
                    ],
                    [
                        'title' => 'Base Token Accounting',
                        'severity' => 'low',
                        'category' => 'accounting',
                        'description' => 'Complex base token accounting requires careful auditing',
                        'recommendation' => 'Extensive testing of accounting edge cases',
                        'line_number' => 178,
                        'confidence' => 0.6
                    ],
                    [
                        'title' => 'Liquidation Incentives',
                        'severity' => 'low',
                        'category' => 'economic',
                        'description' => 'Liquidation incentive structure needs monitoring',
                        'recommendation' => 'Monitor liquidation efficiency in various market conditions',
                        'line_number' => 267,
                        'confidence' => 0.5
                    ],
                    [
                        'title' => 'Governor Architecture',
                        'severity' => 'info',
                        'category' => 'governance',
                        'description' => 'Improved governance architecture over V2',
                        'recommendation' => 'Excellent governance security improvements',
                        'line_number' => null,
                        'confidence' => 1.0
                    ]
                ]
            ],
            
            // 5. Multichain Bridge (Recent Exploit July 2023)
            [
                'name' => 'Multichain Bridge (EXPLOITED)',
                'address' => '0x765277EebeCA2e31912C9946eae1021199B39C61',
                'network' => 'ethereum',
                'type' => 'bridge',
                'category' => 'cross_chain',
                'description' => 'Multichain (Anyswap) Bridge - suffered major exploit in July 2023',
                'tvl' => 0, // TVL drained after exploit
                'status' => 'exploited',
                'risk_score' => 98, // Extremely high risk
                'security_rating' => 'F',
                'verification_status' => 'verified',
                'deployment_date' => '2020-10-15',
                'findings_count' => 10,
                'gas_optimization_score' => 70,
                'has_vulnerabilities' => true,
                'audit_reports' => ['Slowmist', 'Peckshield'],
                'github_url' => 'https://github.com/anyswap/anyswap-v1-core',
                'exploit_date' => '2023-07-06',
                'exploit_amount' => 126000000, // $126M
                'findings' => [
                    [
                        'title' => 'Centralized Key Management',
                        'severity' => 'critical',
                        'category' => 'centralization',
                        'description' => 'Single point of failure in multi-signature key management',
                        'recommendation' => 'Implement decentralized key management with threshold schemes',
                        'line_number' => null,
                        'confidence' => 1.0
                    ],
                    [
                        'title' => 'MPC Wallet Compromise',
                        'severity' => 'critical',
                        'category' => 'key_management',
                        'description' => 'Multi-party computation wallet private keys were compromised',
                        'recommendation' => 'Implement hardware security modules and key rotation',
                        'line_number' => null,
                        'confidence' => 1.0
                    ],
                    [
                        'title' => 'Insufficient Withdrawal Limits',
                        'severity' => 'high',
                        'category' => 'access_control',
                        'description' => 'No rate limiting on large withdrawals from bridge',
                        'recommendation' => 'Implement daily/hourly withdrawal limits and delays',
                        'line_number' => 145,
                        'confidence' => 0.9
                    ],
                    [
                        'title' => 'Cross-Chain Validation Issues',
                        'severity' => 'high',
                        'category' => 'validation',
                        'description' => 'Weak validation of cross-chain transaction proofs',
                        'recommendation' => 'Strengthen cross-chain proof verification',
                        'line_number' => 234,
                        'confidence' => 0.9
                    ],
                    [
                        'title' => 'Oracle Price Manipulation',
                        'severity' => 'high',
                        'category' => 'oracle',
                        'description' => 'Price oracles vulnerable to manipulation during bridge operations',
                        'recommendation' => 'Use multiple oracle sources with deviation checks',
                        'line_number' => 189,
                        'confidence' => 0.85
                    ],
                    [
                        'title' => 'Emergency Pause Mechanism',
                        'severity' => 'medium',
                        'category' => 'emergency',
                        'description' => 'Insufficient emergency pause capabilities',
                        'recommendation' => 'Implement granular emergency pause functions',
                        'line_number' => 356,
                        'confidence' => 0.8
                    ],
                    [
                        'title' => 'Bridge State Synchronization',
                        'severity' => 'medium',
                        'category' => 'synchronization',
                        'description' => 'Risk of state desynchronization between chains',
                        'recommendation' => 'Implement robust state synchronization checks',
                        'line_number' => 267,
                        'confidence' => 0.7
                    ],
                    [
                        'title' => 'Token Minting Controls',
                        'severity' => 'medium',
                        'category' => 'access_control',
                        'description' => 'Overprivileged minting capabilities on destination chains',
                        'recommendation' => 'Restrict minting to verified bridge operations only',
                        'line_number' => 423,
                        'confidence' => 0.8
                    ],
                    [
                        'title' => 'Gas Estimation Errors',
                        'severity' => 'low',
                        'category' => 'gas_optimization',
                        'description' => 'Inaccurate gas estimation for cross-chain operations',
                        'recommendation' => 'Improve gas estimation algorithms',
                        'line_number' => 178,
                        'confidence' => 0.6
                    ],
                    [
                        'title' => 'Event Monitoring Gaps',
                        'severity' => 'low',
                        'category' => 'monitoring',
                        'description' => 'Insufficient event emission for bridge monitoring',
                        'recommendation' => 'Add comprehensive bridge operation events',
                        'line_number' => 89,
                        'confidence' => 0.5
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Seed a single contract with all its data
     */
    private function seedContract(array $contractData): void
    {
        $this->command->info("🔄 Seeding {$contractData['name']}...");
        
        // Create FamousContract record
        $famousContract = FamousContract::create([
            'name' => $contractData['name'],
            'address' => $contractData['address'],
            'network' => $contractData['network'],
            'type' => $contractData['type'],
            'category' => $contractData['category'],
            'description' => $contractData['description'],
            'tvl' => $contractData['tvl'],
            'status' => $contractData['status'],
            'risk_score' => $contractData['risk_score'],
            'security_rating' => $contractData['security_rating'],
            'verification_status' => $contractData['verification_status'],
            'deployment_date' => Carbon::parse($contractData['deployment_date']),
            'findings_count' => $contractData['findings_count'],
            'gas_optimization_score' => $contractData['gas_optimization_score'],
            'has_vulnerabilities' => $contractData['has_vulnerabilities'],
            'audit_reports' => json_encode($contractData['audit_reports']),
            'github_url' => $contractData['github_url'],
            'exploit_date' => isset($contractData['exploit_date']) ? Carbon::parse($contractData['exploit_date']) : null,
            'exploit_amount' => $contractData['exploit_amount'] ?? null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Create ContractAnalysis record
        $contractAnalysis = ContractAnalysis::create([
            'contract_address' => $contractData['address'],
            'network' => $contractData['network'],
            'contract_name' => $contractData['name'],
            'analysis_status' => 'completed',
            'risk_score' => $contractData['risk_score'],
            'security_score' => 100 - $contractData['risk_score'],
            'gas_optimization_score' => $contractData['gas_optimization_score'],
            'findings_count' => $contractData['findings_count'],
            'critical_issues' => collect($contractData['findings'])->where('severity', 'critical')->count(),
            'high_issues' => collect($contractData['findings'])->where('severity', 'high')->count(),
            'medium_issues' => collect($contractData['findings'])->where('severity', 'medium')->count(),
            'low_issues' => collect($contractData['findings'])->where('severity', 'low')->count(),
            'info_issues' => collect($contractData['findings'])->where('severity', 'info')->count(),
            'analysis_result' => json_encode([
                'summary' => $contractData['description'],
                'recommendations' => $this->generateRecommendations($contractData),
                'audit_reports' => $contractData['audit_reports'],
                'github_url' => $contractData['github_url']
            ]),
            'analyzed_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Create Analysis record
        $analysis = Analysis::create([
            'contract_address' => $contractData['address'],
            'network' => $contractData['network'],
            'status' => 'completed',
            'analysis_type' => 'comprehensive',
            'progress' => 100,
            'risk_assessment' => $this->getRiskAssessment($contractData['risk_score']),
            'gas_analysis' => json_encode([
                'optimization_score' => $contractData['gas_optimization_score'],
                'estimated_gas_usage' => $this->estimateGasUsage($contractData),
                'optimization_suggestions' => $this->getGasOptimizationSuggestions($contractData)
            ]),
            'security_analysis' => json_encode([
                'security_rating' => $contractData['security_rating'],
                'vulnerability_count' => collect($contractData['findings'])->where('severity', 'critical')->count() + 
                                       collect($contractData['findings'])->where('severity', 'high')->count(),
                'audit_status' => count($contractData['audit_reports']) > 0 ? 'audited' : 'unaudited',
                'audit_firms' => $contractData['audit_reports']
            ]),
            'completion_time' => $this->getRandomCompletionTime(),
            'ai_confidence' => 0.95,
            'metadata' => json_encode([
                'contract_type' => $contractData['type'],
                'category' => $contractData['category'],
                'tvl' => $contractData['tvl'],
                'deployment_date' => $contractData['deployment_date'],
                'verification_status' => $contractData['verification_status']
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Create Finding records
        foreach ($contractData['findings'] as $findingData) {
            Finding::create([
                'analysis_id' => $analysis->id,
                'contract_address' => $contractData['address'],
                'title' => $findingData['title'],
                'description' => $findingData['description'],
                'severity' => $findingData['severity'],
                'category' => $findingData['category'],
                'recommendation' => $findingData['recommendation'],
                'line_number' => $findingData['line_number'],
                'confidence_score' => $findingData['confidence'],
                'impact_score' => $this->getSeverityScore($findingData['severity']),
                'likelihood_score' => $findingData['confidence'],
                'cvss_score' => $this->calculateCVSSScore($findingData['severity'], $findingData['confidence']),
                'cwe_id' => $this->getCWEId($findingData['category']),
                'owasp_category' => $this->getOWASPCategory($findingData['category']),
                'remediation_effort' => $this->getRemediationEffort($findingData['severity']),
                'false_positive_probability' => 1.0 - $findingData['confidence'],
                'evidence' => json_encode([
                    'detection_method' => 'static_analysis',
                    'pattern_matched' => $findingData['category'],
                    'context' => $findingData['description']
                ]),
                'metadata' => json_encode([
                    'contract_name' => $contractData['name'],
                    'network' => $contractData['network'],
                    'finding_type' => $findingData['category']
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        $this->command->info("✅ {$contractData['name']} seeded successfully");
    }
    
    /**
     * Generate recommendations based on contract data
     */
    private function generateRecommendations(array $contractData): array
    {
        $recommendations = [];
        
        if ($contractData['has_vulnerabilities']) {
            $recommendations[] = 'Immediate security review required due to known vulnerabilities';
            $recommendations[] = 'Consider pausing contract operations until issues are resolved';
        }
        
        if ($contractData['risk_score'] > 70) {
            $recommendations[] = 'High risk contract - implement additional monitoring';
            $recommendations[] = 'Consider bug bounty program for additional security review';
        }
        
        if ($contractData['gas_optimization_score'] < 80) {
            $recommendations[] = 'Gas optimization improvements recommended';
            $recommendations[] = 'Review storage operations and function efficiency';
        }
        
        if (empty($contractData['audit_reports'])) {
            $recommendations[] = 'Professional security audit recommended';
        } else {
            $recommendations[] = 'Multiple audit reports available - good security practice';
        }
        
        return $recommendations;
    }
    
    /**
     * Get risk assessment text
     */
    private function getRiskAssessment(int $riskScore): string
    {
        return match(true) {
            $riskScore >= 90 => 'CRITICAL RISK - Immediate action required',
            $riskScore >= 70 => 'HIGH RISK - Requires careful monitoring',
            $riskScore >= 50 => 'MEDIUM RISK - Standard precautions recommended',
            $riskScore >= 30 => 'LOW RISK - Generally safe for use',
            default => 'MINIMAL RISK - Excellent security profile'
        };
    }
    
    /**
     * Estimate gas usage based on contract complexity
     */
    private function estimateGasUsage(array $contractData): array
    {
        $baseGas = match($contractData['category']) {
            'dex' => 150000,
            'lending' => 200000,
            'bridge' => 300000,
            default => 100000
        };
        
        return [
            'deployment_gas' => $baseGas * 10,
            'average_transaction_gas' => $baseGas,
            'complex_operation_gas' => $baseGas * 2
        ];
    }
    
    /**
     * Get gas optimization suggestions
     */
    private function getGasOptimizationSuggestions(array $contractData): array
    {
        $suggestions = [];
        
        if ($contractData['gas_optimization_score'] < 90) {
            $suggestions[] = 'Optimize storage operations';
            $suggestions[] = 'Consider function modifiers for common checks';
            $suggestions[] = 'Review loop operations for efficiency';
        }
        
        if ($contractData['type'] === 'defi') {
            $suggestions[] = 'Implement batch operations for multiple trades';
            $suggestions[] = 'Consider gas tokens for large operations';
        }
        
        return $suggestions;
    }
    
    /**
     * Get random completion time for analysis
     */
    private function getRandomCompletionTime(): int
    {
        return rand(45, 180); // 45-180 seconds
    }
    
    /**
     * Get severity score
     */
    private function getSeverityScore(string $severity): float
    {
        return match($severity) {
            'critical' => 10.0,
            'high' => 8.0,
            'medium' => 6.0,
            'low' => 4.0,
            'info' => 2.0,
            default => 1.0
        };
    }
    
    /**
     * Calculate CVSS score
     */
    private function calculateCVSSScore(string $severity, float $confidence): float
    {
        $baseCVSS = match($severity) {
            'critical' => 9.5,
            'high' => 7.5,
            'medium' => 5.5,
            'low' => 3.5,
            'info' => 1.0,
            default => 0.0
        };
        
        return round($baseCVSS * $confidence, 1);
    }
    
    /**
     * Get CWE ID based on category
     */
    private function getCWEId(string $category): ?string
    {
        return match($category) {
            'vulnerability' => 'CWE-691',
            'reentrancy' => 'CWE-362',
            'access_control' => 'CWE-284',
            'input_validation' => 'CWE-20',
            'oracle' => 'CWE-345',
            'centralization' => 'CWE-250',
            'key_management' => 'CWE-320',
            default => null
        };
    }
    
    /**
     * Get OWASP category
     */
    private function getOWASPCategory(string $category): string
    {
        return match($category) {
            'vulnerability', 'reentrancy' => 'SC01-Reentrancy',
            'access_control' => 'SC02-Access Control',
            'input_validation' => 'SC03-Input Validation',
            'oracle' => 'SC04-Oracle Manipulation',
            'centralization' => 'SC05-Centralization Risk',
            'gas_optimization' => 'SC06-Gas Optimization',
            'monitoring' => 'SC07-Monitoring',
            default => 'SC08-Other'
        };
    }
    
    /**
     * Get remediation effort
     */
    private function getRemediationEffort(string $severity): string
    {
        return match($severity) {
            'critical' => 'high',
            'high' => 'medium',
            'medium' => 'medium',
            'low' => 'low',
            'info' => 'minimal',
            default => 'low'
        };
    }
}