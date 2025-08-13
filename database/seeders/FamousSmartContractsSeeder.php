<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FamousSmartContractsSeeder extends Seeder
{
    /**
     * Run the database seeder for famous smart contracts.
     */
    public function run(): void
    {
        $this->command->info('🚀 Seeding famous smart contracts...');

        $contracts = [
            [
                'name' => 'Uniswap V3 Router',
                'address' => '0xE592427A0AEce92De3Edee1F18E0157C05861564',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Uniswap V3 SwapRouter - The main router contract for Uniswap V3 DEX operations',
                'deployment_date' => '2021-05-05',
                'total_value_locked' => '2500000000', // $2.5B
                'transaction_count' => 15000000,
                'creator_address' => '0x1a9C8182C09F50C8318d769245beA52c32BE35BC',
                'is_verified' => true,
                'risk_score' => 15, // Low risk
                'security_features' => [
                    'Multi-sig governance',
                    'Time-locked upgrades',
                    'Comprehensive audits',
                    'Open source',
                    'Battle tested'
                ],
                'vulnerabilities' => [],
                'audit_firms' => ['Trail of Bits', 'ABDK Consulting'],
                'gas_optimization' => 'High',
                'code_quality' => 'Excellent'
            ],
            [
                'name' => 'Aave V3 Pool',
                'address' => '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Aave V3 Lending Pool - Core lending and borrowing protocol for Aave V3',
                'deployment_date' => '2022-03-16',
                'total_value_locked' => '6800000000', // $6.8B
                'transaction_count' => 8500000,
                'creator_address' => '0xd784927Ff2f95ba542BfC824c8a8a98F3495f6b5',
                'is_verified' => true,
                'risk_score' => 20, // Low-medium risk
                'security_features' => [
                    'Risk parameters governance',
                    'Liquidation protections',
                    'Rate model optimizations',
                    'Multi-collateral support',
                    'Flash loan protections'
                ],
                'vulnerabilities' => [
                    'Flash loan attack vectors (mitigated)',
                    'Oracle manipulation risks (protected)'
                ],
                'audit_firms' => ['OpenZeppelin', 'SigmaPrime', 'Peckshield'],
                'gas_optimization' => 'High',
                'code_quality' => 'Excellent'
            ],
            [
                'name' => 'Compound V2 cToken',
                'address' => '0x5d3a536E4D6DbD6114cc1Ead35777bAB948E3643',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Compound cDAI - Interest-bearing DAI token in Compound V2 protocol',
                'deployment_date' => '2019-05-07',
                'total_value_locked' => '1200000000', // $1.2B
                'transaction_count' => 12000000,
                'creator_address' => '0x3d9819210A31b4961b30EF54bE2aeD79B9c9Cd3B',
                'is_verified' => true,
                'risk_score' => 25, // Medium risk
                'security_features' => [
                    'Interest rate models',
                    'Collateral factors',
                    'Liquidation mechanisms',
                    'Price feed oracles',
                    'Governance controls'
                ],
                'vulnerabilities' => [
                    'Oracle price manipulation',
                    'Governance attack vectors',
                    'Interest rate model exploits'
                ],
                'audit_firms' => ['OpenZeppelin', 'Trail of Bits'],
                'gas_optimization' => 'Medium',
                'code_quality' => 'Good'
            ],
            [
                'name' => 'Euler Finance Main',
                'address' => '0x27182842E098f60e3D576794A5bFFb0777E025d3',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Euler Finance Protocol - EXPLOITED March 2023 ($197M stolen)',
                'deployment_date' => '2021-12-15',
                'total_value_locked' => '0', // Drained
                'transaction_count' => 450000,
                'creator_address' => '0x3520d5a913427E6F0D6A83E07ccD4A4da316e4d3',
                'is_verified' => true,
                'risk_score' => 95, // Critical - Recently exploited
                'security_features' => [
                    'Risk-adjusted borrowing',
                    'Reactive interest rates',
                    'MEV-resistant liquidations'
                ],
                'vulnerabilities' => [
                    'CRITICAL: Donation attack on eToken implementation (CVE-2023-EULER)',
                    'Price manipulation via donation',
                    'Inflate supply attack vector',
                    'Lack of proper donation checks'
                ],
                'exploit_details' => [
                    'date' => '2023-03-13',
                    'amount_stolen' => '197000000', // $197M
                    'attack_type' => 'Donation Attack',
                    'root_cause' => 'Missing donation protection in eToken contract',
                    'transactions' => [
                        '0xc310e760778ecbca4c65b6c559874757a4c4ece0',
                        '0x71a908be0bef6174bccc3d493becddf769a36832'
                    ]
                ],
                'audit_firms' => ['Sherlock', 'Zellic'],
                'gas_optimization' => 'High',
                'code_quality' => 'Poor (Critical Vulnerability)'
            ],
            [
                'name' => 'BNB Chain Bridge (BSC)',
                'address' => '0x8894E0a0c962CB723c1976a4421c95949bE2D4E3',
                'network' => 'bsc',
                'contract_type' => 'bridge',
                'description' => 'BNB Chain Bridge - EXPLOITED October 2022 ($586M stolen)',
                'deployment_date' => '2020-09-01',
                'total_value_locked' => '0', // Paused after exploit
                'transaction_count' => 2800000,
                'creator_address' => '0x0000000000000000000000000000000000001004',
                'is_verified' => true,
                'risk_score' => 98, // Critical - Major exploit
                'security_features' => [
                    'Multi-signature validation',
                    'Merkle proof verification',
                    'Cross-chain messaging'
                ],
                'vulnerabilities' => [
                    'CRITICAL: IAVL tree proof forgery (CVE-2022-BSC)',
                    'Merkle tree manipulation',
                    'Proof verification bypass',
                    'Cross-chain message forgery'
                ],
                'exploit_details' => [
                    'date' => '2022-10-07',
                    'amount_stolen' => '586000000', // $586M
                    'attack_type' => 'Proof Forgery',
                    'root_cause' => 'IAVL tree proof verification vulnerability',
                    'transactions' => [
                        '0x05356fd06ce56a9ec5b4eaa3cd50b8c40726e30d',
                        '0xebf78c64c8b8e84c6142bc413943042400623932'
                    ]
                ],
                'audit_firms' => ['PeckShield', 'SlowMist'],
                'gas_optimization' => 'Medium',
                'code_quality' => 'Poor (Critical Vulnerability)'
            ]
        ];

        foreach ($contracts as $index => $contract) {
            $this->command->info("📋 Inserting contract " . ($index + 1) . ": {$contract['name']}");

            // Insert into contracts table (assuming you have a contracts table)
            $contractId = DB::table('famous_contracts')->insertGetId([
                'name' => $contract['name'],
                'address' => $contract['address'],
                'network' => $contract['network'],
                'contract_type' => $contract['contract_type'],
                'description' => $contract['description'],
                'deployment_date' => $contract['deployment_date'],
                'total_value_locked' => $contract['total_value_locked'],
                'transaction_count' => $contract['transaction_count'],
                'creator_address' => $contract['creator_address'],
                'is_verified' => $contract['is_verified'],
                'risk_score' => $contract['risk_score'],
                'security_features' => json_encode($contract['security_features']),
                'vulnerabilities' => json_encode($contract['vulnerabilities']),
                'audit_firms' => json_encode($contract['audit_firms']),
                'gas_optimization' => $contract['gas_optimization'],
                'code_quality' => $contract['code_quality'],
                'exploit_details' => isset($contract['exploit_details']) ? json_encode($contract['exploit_details']) : null,
                'metadata' => json_encode([
                    'seeded_at' => now()->toISOString(),
                    'seed_version' => '1.0.0',
                    'source' => 'FamousSmartContractsSeeder'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create sample analysis records for each contract
            $this->createSampleAnalyses($contractId, $contract);
        }

        $this->command->info('✅ Successfully seeded 5 famous smart contracts!');
        $this->command->info('📊 Contracts include: Uniswap V3, Aave V3, Compound V2, Euler (exploited), BNB Bridge (exploited)');
    }

    /**
     * Create sample analysis records for a contract
     */
    private function createSampleAnalyses(int $contractId, array $contract): void
    {
        $analysisTypes = ['security_audit', 'gas_optimization', 'vulnerability_scan', 'code_quality'];
        
        foreach ($analysisTypes as $type) {
            DB::table('contract_analyses')->insert([
                'contract_id' => $contractId,
                'analysis_type' => $type,
                'status' => 'completed',
                'risk_score' => $contract['risk_score'],
                'findings' => json_encode($this->generateSampleFindings($type, $contract)),
                'recommendations' => json_encode($this->generateRecommendations($type, $contract)),
                'analysis_date' => now()->subDays(rand(1, 30)),
                'analyzer_version' => '2.0.0',
                'metadata' => json_encode([
                    'seed_generated' => true,
                    'contract_name' => $contract['name'],
                    'network' => $contract['network']
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Generate sample findings based on contract data
     */
    private function generateSampleFindings(string $type, array $contract): array
    {
        $baseFindings = [
            'contract_verified' => $contract['is_verified'],
            'deployment_date' => $contract['deployment_date'],
            'total_value_locked' => $contract['total_value_locked'],
            'transaction_volume' => $contract['transaction_count']
        ];

        switch ($type) {
            case 'security_audit':
                return array_merge($baseFindings, [
                    'security_score' => 100 - $contract['risk_score'],
                    'vulnerabilities_found' => count($contract['vulnerabilities']),
                    'audit_status' => !empty($contract['audit_firms']) ? 'audited' : 'unaudited',
                    'critical_issues' => $contract['risk_score'] > 80 ? count($contract['vulnerabilities']) : 0
                ]);

            case 'gas_optimization':
                return array_merge($baseFindings, [
                    'gas_efficiency' => $contract['gas_optimization'],
                    'optimization_score' => match($contract['gas_optimization']) {
                        'High' => 85,
                        'Medium' => 60,
                        'Low' => 35,
                        default => 50
                    },
                    'estimated_savings' => rand(10, 40) . '%'
                ]);

            case 'vulnerability_scan':
                return array_merge($baseFindings, [
                    'vulnerabilities' => $contract['vulnerabilities'],
                    'severity_distribution' => [
                        'critical' => $contract['risk_score'] > 80 ? rand(1, 3) : 0,
                        'high' => $contract['risk_score'] > 60 ? rand(0, 2) : 0,
                        'medium' => rand(0, 3),
                        'low' => rand(1, 5)
                    ]
                ]);

            case 'code_quality':
                return array_merge($baseFindings, [
                    'quality_score' => match($contract['code_quality']) {
                        'Excellent' => rand(90, 100),
                        'Good' => rand(70, 89),
                        'Fair' => rand(50, 69),
                        'Poor' => rand(20, 49),
                        default => 50
                    },
                    'complexity_analysis' => [
                        'cyclomatic_complexity' => rand(10, 50),
                        'code_coverage' => rand(60, 95) . '%',
                        'documentation_score' => rand(40, 90)
                    ]
                ]);

            default:
                return $baseFindings;
        }
    }

    /**
     * Generate recommendations based on analysis type and contract
     */
    private function generateRecommendations(string $type, array $contract): array
    {
        $recommendations = [];

        if ($contract['risk_score'] > 80) {
            $recommendations[] = 'URGENT: Address critical vulnerabilities before deployment';
            $recommendations[] = 'Implement comprehensive security measures';
            $recommendations[] = 'Consider additional security audits';
        }

        if (empty($contract['audit_firms'])) {
            $recommendations[] = 'Recommend professional security audit';
        }

        switch ($type) {
            case 'security_audit':
                $recommendations = array_merge($recommendations, [
                    'Implement multi-signature requirements',
                    'Add time-locked upgrade mechanisms',
                    'Enhance input validation',
                    'Regular security monitoring'
                ]);
                break;

            case 'gas_optimization':
                $recommendations = array_merge($recommendations, [
                    'Optimize storage variable packing',
                    'Reduce external contract calls',
                    'Implement efficient loops',
                    'Use events for data logging'
                ]);
                break;

            case 'vulnerability_scan':
                if (!empty($contract['vulnerabilities'])) {
                    $recommendations = array_merge($recommendations, [
                        'Address identified vulnerabilities immediately',
                        'Implement additional access controls',
                        'Add emergency pause mechanisms',
                        'Monitor for suspicious activities'
                    ]);
                }
                break;

            case 'code_quality':
                $recommendations = array_merge($recommendations, [
                    'Improve code documentation',
                    'Add comprehensive unit tests',
                    'Implement code review processes',
                    'Follow best practice patterns'
                ]);
                break;
        }

        return array_unique($recommendations);
    }
}
