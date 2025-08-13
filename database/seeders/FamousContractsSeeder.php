<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FamousContract;
use App\Models\ContractAnalysis;
use Carbon\Carbon;

class FamousContractsSeeder extends Seeder
{
    /**
     * Seed the famous contracts database with real-world high-profile contracts
     */
    public function run(): void
    {
        $contracts = [
            [
                // 1. Uniswap V3 Router - Most famous DEX
                'name' => 'Uniswap V3 SwapRouter',
                'address' => '0xE592427A0AEce92De3Edee1F18E0157C05861564',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Uniswap V3 SwapRouter contract - the primary interface for swapping tokens on Uniswap V3. Handles single and multi-hop swaps with concentrated liquidity positions.',
                'deployment_date' => Carbon::create(2021, 5, 5),
                'total_value_locked' => 3500000000, // $3.5B
                'transaction_count' => 25000000,
                'creator_address' => '0x41653c7d61609D856f29355E404F310Ec4142Cfb',
                'is_verified' => true,
                'risk_score' => 15, // Very low risk
                'security_features' => [
                    'Multi-sig governance',
                    'Time-locked upgrades',
                    'Pausable functionality',
                    'Access control',
                    'Reentrancy guards',
                    'Safe math libraries',
                    'Oracle integration'
                ],
                'vulnerabilities' => [],
                'audit_firms' => [
                    'Trail of Bits',
                    'ConsenSys Diligence',
                    'ABDK'
                ],
                'gas_optimization' => 85,
                'code_quality' => 95,
                'exploit_details' => [],
                'metadata' => [
                    'protocol' => 'Uniswap',
                    'version' => 'V3',
                    'category' => 'Decentralized Exchange',
                    'token_standard' => 'ERC-20',
                    'github' => 'https://github.com/Uniswap/v3-periphery',
                    'documentation' => 'https://docs.uniswap.org/protocol/reference/periphery',
                    'features' => [
                        'concentrated_liquidity',
                        'multiple_fee_tiers',
                        'range_orders',
                        'flexible_oracle'
                    ],
                    'integrations' => 150000,
                    'daily_volume' => 800000000 // $800M daily
                ]
            ],
            [
                // 2. Aave V3 Pool - Leading lending protocol
                'name' => 'Aave V3 Pool',
                'address' => '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Aave V3 Pool contract - core lending and borrowing functionality with cross-chain capabilities, isolation mode, and efficiency mode features.',
                'deployment_date' => Carbon::create(2022, 3, 16),
                'total_value_locked' => 2800000000, // $2.8B
                'transaction_count' => 12000000,
                'creator_address' => '0xEE56e2B3D491590B5b31738cC34d5232F378a8D5',
                'is_verified' => true,
                'risk_score' => 25, // Low risk
                'security_features' => [
                    'Multi-layered governance',
                    'Risk management',
                    'Isolation mode',
                    'Circuit breakers',
                    'Liquidation protection',
                    'Oracle failsafes',
                    'Emergency pause'
                ],
                'vulnerabilities' => [
                    [
                        'type' => 'Price oracle dependency',
                        'severity' => 'Medium',
                        'status' => 'Mitigated',
                        'description' => 'Dependency on external price oracles'
                    ]
                ],
                'audit_firms' => [
                    'OpenZeppelin',
                    'Trail of Bits',
                    'Consensys Diligence',
                    'SigmaPrime',
                    'ABDK'
                ],
                'gas_optimization' => 80,
                'code_quality' => 90,
                'exploit_details' => [],
                'metadata' => [
                    'protocol' => 'Aave',
                    'version' => 'V3',
                    'category' => 'Lending Protocol',
                    'supported_assets' => 25,
                    'github' => 'https://github.com/aave/aave-v3-core',
                    'documentation' => 'https://docs.aave.com/developers/',
                    'features' => [
                        'variable_rate_borrowing',
                        'stable_rate_borrowing',
                        'flash_loans',
                        'isolation_mode',
                        'efficiency_mode',
                        'cross_chain_functionality'
                    ],
                    'chains_deployed' => [
                        'Ethereum', 'Polygon', 'Avalanche', 'Arbitrum', 'Optimism'
                    ],
                    'governance_token' => 'AAVE'
                ]
            ],
            [
                // 3. Curve Finance - Stablecoin DEX
                'name' => 'Curve 3Pool',
                'address' => '0xbEbc44782C7dB0a1A60Cb6fe97d0b483032FF1C7',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Curve 3Pool contract - largest stablecoin liquidity pool (DAI, USDC, USDT) with low slippage trading and yield farming rewards.',
                'deployment_date' => Carbon::create(2020, 6, 2),
                'total_value_locked' => 1200000000, // $1.2B
                'transaction_count' => 8500000,
                'creator_address' => '0x7a16fF8270133F063aAb6C9977183D9e72835428',
                'is_verified' => true,
                'risk_score' => 20, // Low risk
                'security_features' => [
                    'Bonding curve algorithm',
                    'Administrative keys',
                    'Fee collection',
                    'Slippage protection',
                    'Invariant checks'
                ],
                'vulnerabilities' => [],
                'audit_firms' => [
                    'Trail of Bits',
                    'MixBytes',
                    'Quantstamp'
                ],
                'gas_optimization' => 75,
                'code_quality' => 85,
                'exploit_details' => [],
                'metadata' => [
                    'protocol' => 'Curve Finance',
                    'pool_type' => 'StableSwap',
                    'category' => 'Automated Market Maker',
                    'assets' => ['DAI', 'USDC', 'USDT'],
                    'github' => 'https://github.com/curvefi/curve-contract',
                    'documentation' => 'https://curve.readthedocs.io/',
                    'features' => [
                        'low_slippage_stablecoins',
                        'yield_farming',
                        'gauge_rewards',
                        'vote_escrowed_crv'
                    ],
                    'daily_volume' => 150000000, // $150M daily
                    'rewards_token' => 'CRV'
                ]
            ],
            [
                // 4. Euler Finance Exploit - Major 2023 exploit
                'name' => 'Euler Finance',
                'address' => '0x27182842E098f60e3D576794A5bFFb0777E025d3',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Euler Finance lending protocol that suffered a major exploit in March 2023 due to a donation attack vulnerability in the liquidation mechanism.',
                'deployment_date' => Carbon::create(2021, 8, 13),
                'total_value_locked' => 200000000, // $200M before exploit
                'transaction_count' => 450000,
                'creator_address' => '0x3520d5a913427E6F0D6A83E07ccD4A4da316e4d3',
                'is_verified' => true,
                'risk_score' => 95, // Critical - was exploited
                'security_features' => [
                    'Permissionless lending',
                    'MEV protection',
                    'Risk-adjusted borrowing',
                    'Liquidation system'
                ],
                'vulnerabilities' => [
                    [
                        'type' => 'Donation attack',
                        'severity' => 'Critical',
                        'status' => 'Exploited',
                        'description' => 'Donation attack on liquidation mechanism allowing self-liquidation profit'
                    ],
                    [
                        'type' => 'Liquidation logic flaw',
                        'severity' => 'High',
                        'status' => 'Exploited',
                        'description' => 'Flawed liquidation discount calculation'
                    ]
                ],
                'audit_firms' => [
                    'Halborn',
                    'Solidified',
                    'ZK Labs'
                ],
                'gas_optimization' => 70,
                'code_quality' => 60, // Reduced due to exploit
                'exploit_details' => [
                    'date' => '2023-03-13',
                    'amount_stolen' => 197000000, // $197M
                    'exploit_type' => 'Flash loan + Donation attack',
                    'transactions' => [
                        '0xc310a0affe2169d1f6feec1c63dbc7f7c62a887fa48795d327d4d2da2d6b111d',
                        '0x71a908be0bef6174bccc3d493becdcbf2e2d19dd4fd8f83d12a0e2e54c9c6179'
                    ],
                    'method' => 'Self-liquidation with inflated health score via donation',
                    'recovered' => 177000000, // $177M recovered through negotiations
                    'timeline' => [
                        '2023-03-13 08:56' => 'First exploit transaction',
                        '2023-03-13 09:20' => 'Additional funds drained',
                        '2023-03-13 10:00' => 'Protocol paused',
                        '2023-03-20' => 'Negotiations begin',
                        '2023-04-03' => 'Most funds returned'
                    ],
                    'root_cause' => 'Liquidation discount calculation vulnerability',
                    'lessons_learned' => [
                        'Complex liquidation logic requires extensive testing',
                        'Donation attacks on health calculations',
                        'Self-liquidation edge cases'
                    ]
                ],
                'metadata' => [
                    'protocol' => 'Euler Finance',
                    'category' => 'Lending Protocol',
                    'exploit_rank' => 3, // 3rd largest DeFi exploit of 2023
                    'github' => 'https://github.com/euler-xyz/euler-contracts',
                    'post_mortem' => 'https://blog.euler.finance/eulerdao-post-mortem/',
                    'features' => [
                        'permissionless_listing',
                        'reactive_interest_rates',
                        'protected_collateral',
                        'mev_resistance'
                    ],
                    'status' => 'Permanently shut down post-exploit'
                ]
            ],
            [
                // 5. BNB Chain Bridge Exploit - Recent major exploit
                'name' => 'BSC Token Hub',
                'address' => '0x0000000000000000000000000000000000001004',
                'network' => 'binance',
                'contract_type' => 'bridge',
                'description' => 'BNB Chain (BSC) Token Hub cross-chain bridge that suffered a $570M exploit in October 2022 due to a merkle proof verification vulnerability.',
                'deployment_date' => Carbon::create(2020, 9, 1),
                'total_value_locked' => 1000000000, // $1B+ before exploit
                'transaction_count' => 15000000,
                'creator_address' => '0x0000000000000000000000000000000000000000',
                'is_verified' => true,
                'risk_score' => 98, // Critical - massive exploit
                'security_features' => [
                    'Multi-signature validation',
                    'Cross-chain messaging',
                    'Merkle proof verification',
                    'Validator consensus'
                ],
                'vulnerabilities' => [
                    [
                        'type' => 'Merkle proof forging',
                        'severity' => 'Critical',
                        'status' => 'Exploited',
                        'description' => 'Ability to forge valid merkle proofs for arbitrary withdrawals'
                    ],
                    [
                        'type' => 'Insufficient validation',
                        'severity' => 'High',
                        'status' => 'Exploited',
                        'description' => 'Inadequate verification of cross-chain messages'
                    ]
                ],
                'audit_firms' => [
                    'PeckShield',
                    'SlowMist'
                ],
                'gas_optimization' => 60,
                'code_quality' => 40, // Very low due to critical exploit
                'exploit_details' => [
                    'date' => '2022-10-07',
                    'amount_stolen' => 570000000, // $570M+ (largest DeFi exploit ever)
                    'exploit_type' => 'Cross-chain bridge manipulation',
                    'transactions' => [
                        '0x05356fd06ce56a9ec5b4eaf9c075abd740cae4c21ee1f50ebc0acd298e0798d6',
                        '0x7c30d9d338cda9fe897d31b6e82d102e71b145d3b3ecf74e30b946b7d1b62ede'
                    ],
                    'method' => 'Forged IAVL merkle proof to create fake withdrawal proof',
                    'assets_stolen' => [
                        'BNB' => 2000000, // 2M BNB
                        'Various tokens' => 'Multiple ERC-20 tokens'
                    ],
                    'timeline' => [
                        '2022-10-06 23:30' => 'First malicious transaction',
                        '2022-10-07 02:20' => 'Exploit discovered',
                        '2022-10-07 03:00' => 'BSC network halted',
                        '2022-10-07 06:35' => 'Network resumed with patch',
                        '2022-10-08' => 'Post-mortem published'
                    ],
                    'root_cause' => 'Improper merkle proof verification in cross-chain bridge',
                    'response' => [
                        'Network halt within hours',
                        'Emergency validator consensus',
                        'Immediate patch deployment',
                        'Enhanced monitoring implemented'
                    ],
                    'lessons_learned' => [
                        'Cross-chain bridges are high-value targets',
                        'Merkle proof verification must be bulletproof',
                        'Emergency response procedures critical',
                        'Centralized control has pros and cons'
                    ]
                ],
                'metadata' => [
                    'protocol' => 'BNB Chain',
                    'category' => 'Cross-chain Bridge',
                    'exploit_rank' => 1, // Largest DeFi exploit in history
                    'network_type' => 'Layer 1',
                    'consensus' => 'Proof of Staked Authority',
                    'validators' => 21,
                    'block_time' => 3, // seconds
                    'features' => [
                        'cross_chain_transfers',
                        'validator_staking',
                        'smart_contracts',
                        'evm_compatibility'
                    ],
                    'ecosystem_size' => 1000, // 1000+ dApps
                    'status' => 'Active with enhanced security'
                ]
            ]
        ];

        foreach ($contracts as $contractData) {
            // Create the famous contract
            FamousContract::updateOrCreate(
                ['address' => $contractData['address']],
                $contractData
            );

            $this->command->info("  ✅ Created {$contractData['name']}");
        }

        $this->command->info('Successfully seeded 5 famous contracts:');
        $this->command->info('1. Uniswap V3 SwapRouter - Leading DEX protocol');
        $this->command->info('2. Aave V3 Pool - Premier lending protocol');
        $this->command->info('3. Curve 3Pool - Stablecoin DEX');
        $this->command->info('4. Euler Finance - Exploited lending protocol ($197M loss)');
        $this->command->info('5. BSC Token Hub - Cross-chain bridge ($570M exploit)');
    }


}