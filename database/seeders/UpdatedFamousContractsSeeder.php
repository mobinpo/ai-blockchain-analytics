<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class UpdatedFamousContractsSeeder extends Seeder
{
    /**
     * Seed the database with 5 famous smart contracts including recent exploits.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('famous_contracts')->truncate();

        // Insert 5 famous smart contracts with recent data
        $contracts = [
            [
                'name' => 'Uniswap V3 Router',
                'address' => '0xE592427A0AEce92De3Edee1F18E0157C05861564',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Uniswap V3 SwapRouter - The most popular decentralized exchange router handling billions in daily volume with concentrated liquidity features.',
                'deployment_date' => '2021-05-05',
                'total_value_locked' => 3200000000, // $3.2B
                'transaction_count' => 25000000,
                'creator_address' => '0x1a9C8182C09F50C8318d769245beA52c32BE35BC',
                'is_verified' => true,
                'risk_score' => 10, // Very low risk
                'security_features' => json_encode([
                    'Multi-sig governance',
                    'Time-locked upgrades', 
                    'Comprehensive audits',
                    'Open source',
                    'Battle tested',
                    'Concentrated liquidity',
                    'MEV protection'
                ]),
                'vulnerabilities' => json_encode([]),
                'audit_firms' => json_encode([
                    'Trail of Bits',
                    'ABDK Consulting', 
                    'ConsenSys Diligence',
                    'OpenZeppelin'
                ]),
                'gas_optimization' => 'Excellent',
                'code_quality' => 'Excellent',
                'exploit_details' => null,
                'metadata' => json_encode([
                    'protocol' => 'Uniswap V3',
                    'deployment_block' => 12369621,
                    'daily_volume_usd' => 1500000000,
                    'fee_tiers' => ['0.05%', '0.30%', '1.00%'],
                    'website' => 'https://uniswap.org',
                    'github' => 'https://github.com/Uniswap/v3-core',
                    'category' => 'DEX'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Aave V3 Pool',
                'address' => '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Aave V3 Lending Pool - Leading decentralized lending protocol with advanced risk management and cross-chain capabilities.',
                'deployment_date' => '2022-03-16',
                'total_value_locked' => 7800000000, // $7.8B
                'transaction_count' => 12000000,
                'creator_address' => '0xd784927Ff2f95ba542BfC824c8a8a98F3495f6b5',
                'is_verified' => true,
                'risk_score' => 15, // Low risk
                'security_features' => json_encode([
                    'Risk governance framework',
                    'Liquidation protection',
                    'Flash loans with fees',
                    'Isolation mode',
                    'Efficiency mode (eMode)',
                    'Rate strategy optimization',
                    'Cross-chain support'
                ]),
                'vulnerabilities' => json_encode([
                    'Flash loan attack vectors (mitigated with fees)',
                    'Oracle manipulation risks (mitigated)'
                ]),
                'audit_firms' => json_encode([
                    'OpenZeppelin',
                    'SigmaPrime',
                    'Certora',
                    'PeckShield'
                ]),
                'gas_optimization' => 'Excellent',
                'code_quality' => 'Excellent',
                'exploit_details' => null,
                'metadata' => json_encode([
                    'protocol' => 'Aave V3',
                    'deployment_block' => 14485735,
                    'supported_assets' => 30,
                    'flash_loan_fee' => '0.09%',
                    'website' => 'https://aave.com',
                    'github' => 'https://github.com/aave/aave-v3-core',
                    'category' => 'Lending'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Multichain Bridge (Anyswap)',
                'address' => '0x6b7a87899490EcE95443e979cA9485CBE7E71522',
                'network' => 'ethereum',
                'contract_type' => 'bridge',
                'description' => 'Multichain (formerly Anyswap) cross-chain bridge protocol - EXPLOITED in July 2023 with $126M loss due to compromised private keys.',
                'deployment_date' => '2021-01-15',
                'total_value_locked' => 0, // Drained during exploit
                'transaction_count' => 3500000,
                'creator_address' => '0x8F8c8c51CC6fF5395b1dB9e1Dd9Ae2c1e8F4b4E9',
                'is_verified' => true,
                'risk_score' => 95, // Very high risk - exploited
                'security_features' => json_encode([
                    'Multi-signature validation',
                    'Cross-chain messaging',
                    'Automated routing'
                ]),
                'vulnerabilities' => json_encode([
                    'Private key compromise',
                    'Centralized key management',
                    'Lack of multi-party computation',
                    'Insufficient monitoring'
                ]),
                'audit_firms' => json_encode([
                    'SlowMist',
                    'PeckShield'
                ]),
                'gas_optimization' => 'Good',
                'code_quality' => 'Poor',
                'exploit_details' => json_encode([
                    'exploit_date' => '2023-07-06',
                    'loss_amount_usd' => 126000000,
                    'exploit_type' => 'Private Key Compromise',
                    'affected_chains' => ['Ethereum', 'BNB Chain', 'Polygon', 'Arbitrum'],
                    'root_cause' => 'Compromised private keys allowed attackers to mint unlimited tokens',
                    'recovery_status' => 'Partial - some funds recovered',
                    'post_mortem' => 'https://medium.com/@multichainorg/multichain-incident-update-126-million-in-losses'
                ]),
                'metadata' => json_encode([
                    'protocol' => 'Multichain',
                    'exploit_block' => 17650946,
                    'status' => 'Exploited/Defunct',
                    'website' => 'https://multichain.org',
                    'category' => 'Cross-chain Bridge',
                    'lesson_learned' => 'Importance of decentralized key management'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lido Staked ETH (stETH)',
                'address' => '0xae7ab96520DE3A18E5e111B5EaAb095312D7fE84',
                'network' => 'ethereum',
                'contract_type' => 'staking',
                'description' => 'Lido Staked Ethereum - Largest liquid staking protocol allowing users to stake ETH while maintaining liquidity through stETH tokens.',
                'deployment_date' => '2020-12-18',
                'total_value_locked' => 14500000000, // $14.5B
                'transaction_count' => 8000000,
                'creator_address' => '0x3e40D73EB977Dc6a537aF587D48316feE66E9C8c',
                'is_verified' => true,
                'risk_score' => 25, // Low-medium risk
                'security_features' => json_encode([
                    'Distributed validator network',
                    'DAO governance',
                    'Slashing protection',
                    'Oracle system for rates',
                    'Emergency pause mechanism',
                    'Node operator diversification'
                ]),
                'vulnerabilities' => json_encode([
                    'Validator slashing risk',
                    'Smart contract risk',
                    'Oracle manipulation potential',
                    'Centralization of large stake'
                ]),
                'audit_firms' => json_encode([
                    'MixBytes',
                    'Sigma Prime',
                    'Quantstamp',
                    'StateMind'
                ]),
                'gas_optimization' => 'Good',
                'code_quality' => 'Excellent',
                'exploit_details' => null,
                'metadata' => json_encode([
                    'protocol' => 'Lido',
                    'deployment_block' => 11473216,
                    'staked_eth' => 9200000,
                    'node_operators' => 30,
                    'staking_fee' => '10%',
                    'website' => 'https://lido.fi',
                    'github' => 'https://github.com/lidofinance/lido-dao',
                    'category' => 'Liquid Staking'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Curve Finance 3Pool',
                'address' => '0xbEbc44782C7dB0a1A60Cb6fe97d0b483032FF1C7',
                'network' => 'ethereum',
                'contract_type' => 'defi',
                'description' => 'Curve Finance 3Pool (DAI/USDC/USDT) - Largest stablecoin AMM providing efficient swaps with minimal slippage and yield farming opportunities.',
                'deployment_date' => '2020-01-02',
                'total_value_locked' => 1800000000, // $1.8B
                'transaction_count' => 18000000,
                'creator_address' => '0x7a16fF8270133F063aAb6C9977183D9e72835428',
                'is_verified' => true,
                'risk_score' => 20, // Low risk
                'security_features' => json_encode([
                    'Stable asset focus',
                    'Bonding curve optimization',
                    'Admin key timelock',
                    'Emergency withdrawal',
                    'Amplification parameter',
                    'Fee optimization'
                ]),
                'vulnerabilities' => json_encode([
                    'Admin key risks (mitigated with timelock)',
                    'Oracle dependency for some pools',
                    'Impermanent loss potential'
                ]),
                'audit_firms' => json_encode([
                    'Trail of Bits',
                    'MixBytes',
                    'ChainSecurity'
                ]),
                'gas_optimization' => 'Excellent',
                'code_quality' => 'Excellent',
                'exploit_details' => null,
                'metadata' => json_encode([
                    'protocol' => 'Curve Finance',
                    'deployment_block' => 9456293,
                    'pool_type' => 'StableSwap',
                    'assets' => ['DAI', 'USDC', 'USDT'],
                    'amplification' => 'A=2000',
                    'website' => 'https://curve.fi',
                    'github' => 'https://github.com/curvefi/curve-contract',
                    'category' => 'Stablecoin DEX'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        // Insert all contracts
        DB::table('famous_contracts')->insert($contracts);

        $this->command->info('✅ Successfully seeded 5 famous contracts:');
        $this->command->info('   1. Uniswap V3 Router (Low Risk DEX)');
        $this->command->info('   2. Aave V3 Pool (Low Risk Lending)');
        $this->command->info('   3. Multichain Bridge (EXPLOITED - High Risk)');
        $this->command->info('   4. Lido Staked ETH (Medium Risk Staking)');
        $this->command->info('   5. Curve Finance 3Pool (Low Risk Stablecoin DEX)');
        $this->command->info('');
        $this->command->info('💡 Includes real exploit data from Multichain bridge hack (July 2023, $126M loss)');
    }
}
