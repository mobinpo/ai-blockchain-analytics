-- =============================================================================
-- Famous Smart Contracts Database Seeding
-- Using existing table structures
-- =============================================================================

-- Clear existing data first (optional)
TRUNCATE TABLE famous_contracts CASCADE;

-- Insert 5 famous smart contracts
INSERT INTO famous_contracts (
    name, address, network, contract_type, description, deployment_date,
    total_value_locked, transaction_count, creator_address, is_verified,
    risk_score, security_features, vulnerabilities, audit_firms,
    gas_optimization, code_quality, exploit_details, metadata,
    created_at, updated_at
) VALUES 

-- 1. Uniswap V3 Router (Low Risk - Blue Chip DeFi)
(
    'Uniswap V3 Router',
    '0xE592427A0AEce92De3Edee1F18E0157C05861564',
    'ethereum',
    'defi',
    'Uniswap V3 SwapRouter - The main router contract for Uniswap V3 DEX operations, handling swaps with concentrated liquidity',
    '2021-05-05',
    2500000000, -- $2.5B
    15000000,
    '0x1a9C8182C09F50C8318d769245beA52c32BE35BC',
    true,
    15, -- Low risk
    '["Multi-sig governance", "Time-locked upgrades", "Comprehensive audits", "Open source", "Battle tested", "Concentrated liquidity"]'::jsonb,
    '[]'::jsonb,
    '["Trail of Bits", "ABDK Consulting", "ConsenSys Diligence"]'::jsonb,
    'High',
    'Excellent',
    null,
    '{"deployment_block": 12369621, "daily_volume": 1200000000, "fee_tier": "0.3%", "seeded_at": "2025-01-08T20:00:00Z"}'::jsonb,
    NOW(),
    NOW()
),

-- 2. Aave V3 Pool (Low-Medium Risk - Leading Lending)
(
    'Aave V3 Pool',
    '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
    'ethereum',
    'defi',
    'Aave V3 Lending Pool - Core lending and borrowing protocol with improved capital efficiency and risk management',
    '2022-03-16',
    6800000000, -- $6.8B
    8500000,
    '0xd784927Ff2f95ba542BfC824c8a8a98F3495f6b5',
    true,
    20, -- Low-medium risk
    '["Risk parameters governance", "Liquidation protections", "Rate model optimizations", "Multi-collateral support", "Flash loan protections", "Isolation mode"]'::jsonb,
    '["Flash loan attack vectors (mitigated)", "Oracle manipulation risks (protected)"]'::jsonb,
    '["OpenZeppelin", "SigmaPrime", "Peckshield", "ABDK"]'::jsonb,
    'High',
    'Excellent',
    null,
    '{"version": "3.0", "deployment_block": 14414000, "supported_assets": 25, "isolation_mode": true, "seeded_at": "2025-01-08T20:00:00Z"}'::jsonb,
    NOW(),
    NOW()
),

-- 3. Compound V2 cDAI (Medium Risk - Legacy DeFi)
(
    'Compound V2 cDAI',
    '0x5d3a536E4D6DbD6114cc1Ead35777bAB948E3643',
    'ethereum',
    'defi',
    'Compound cDAI - Interest-bearing DAI token in Compound V2 protocol, one of the first lending protocols',
    '2019-05-07',
    1200000000, -- $1.2B
    12000000,
    '0x3d9819210A31b4961b30EF54bE2aeD79B9c9Cd3B',
    true,
    25, -- Medium risk
    '["Interest rate models", "Collateral factors", "Liquidation mechanisms", "Price feed oracles", "Governance controls"]'::jsonb,
    '["Oracle price manipulation", "Governance attack vectors", "Interest rate model exploits", "Legacy codebase risks"]'::jsonb,
    '["OpenZeppelin", "Trail of Bits"]'::jsonb,
    'Medium',
    'Good',
    null,
    '{"version": "2.0", "deployment_block": 7710735, "underlying_asset": "DAI", "seeded_at": "2025-01-08T20:00:00Z"}'::jsonb,
    NOW(),
    NOW()
),

-- 4. Euler Finance Main (CRITICAL RISK - Major Exploit March 2023)
(
    'Euler Finance Main',
    '0x27182842E098f60e3D576794A5bFFb0777E025d3',
    'ethereum',
    'defi',
    'Euler Finance Protocol - EXPLOITED March 2023 ($197M stolen) - Donation attack vulnerability in eToken implementation',
    '2021-12-15',
    0, -- Drained after exploit
    450000,
    '0x3520d5a913427E6F0D6A83E07ccD4A4da316e4d3',
    true,
    95, -- Critical risk - Recently exploited
    '["Risk-adjusted borrowing", "Reactive interest rates", "MEV-resistant liquidations", "Sub-accounts"]'::jsonb,
    '["CRITICAL: Donation attack on eToken implementation (CVE-2023-EULER)", "Price manipulation via donation", "Inflate supply attack vector", "Lack of proper donation checks", "Vulnerable violateCollateralInvariant function"]'::jsonb,
    '["Sherlock", "Zellic"]'::jsonb,
    'High',
    'Poor (Critical Vulnerability)',
    '{
        "exploit_date": "2023-03-13",
        "amount_stolen_usd": 197000000,
        "attack_type": "Donation Attack",
        "root_cause": "Missing donation protection in eToken contract",
        "attacker_address": "0xb66cd966670d962c227b3eaba30a872dbfb995db",
        "exploit_transactions": [
            "0xc310e760778ecbca4c65b6c559874757a4c4ece0",
            "0x71a908be0bef6174bccc3d493becddf769a36832"
        ],
        "recovery_status": "Partial recovery via attacker negotiation",
        "post_mortem": "https://blog.euler.xyz/euler-exploit-report/"
    }'::jsonb,
    '{"exploit_confirmed": true, "severity": "critical", "status": "exploited", "seeded_at": "2025-01-08T20:00:00Z"}'::jsonb,
    NOW(),
    NOW()
),

-- 5. BNB Chain Bridge (CRITICAL RISK - Major Exploit October 2022)
(
    'BNB Chain Bridge',
    '0x8894E0a0c962CB723c1976a4421c95949bE2D4E3',
    'bsc',
    'bridge',
    'BNB Chain Bridge - EXPLOITED October 2022 ($586M stolen) - IAVL tree proof forgery vulnerability',
    '2020-09-01',
    0, -- Paused and drained after exploit
    2800000,
    '0x0000000000000000000000000000000000001004',
    true,
    98, -- Critical risk - Major bridge exploit
    '["Multi-signature validation", "Merkle proof verification", "Cross-chain messaging", "Validator consensus"]'::jsonb,
    '["CRITICAL: IAVL tree proof forgery (CVE-2022-BSC)", "Merkle tree manipulation", "Proof verification bypass", "Cross-chain message forgery", "Weak proof validation logic"]'::jsonb,
    '["PeckShield", "SlowMist", "Certik"]'::jsonb,
    'Medium',
    'Poor (Critical Vulnerability)',
    '{
        "exploit_date": "2022-10-07",
        "amount_stolen_usd": 586000000,
        "attack_type": "Proof Forgery Attack",
        "root_cause": "IAVL tree proof verification vulnerability allowing forged withdraw proofs",
        "attacker_address": "0x489a8756c18c0b8b24ec2a2b9ff3d4d447f79bec",
        "exploit_transactions": [
            "0x05356fd06ce56a9ec5b4eaa3cd50b8c40726e30d",
            "0xebf78c64c8b8e84c6142bc413943042400623932"
        ],
        "tokens_stolen": ["BNB", "BUSD", "USDT", "ETH"],
        "recovery_status": "Bridge temporarily halted, partial funds frozen",
        "post_mortem": "https://www.bnbchain.org/en/blog/bnb-chain-ecosystem-update/"
    }'::jsonb,
    '{"exploit_confirmed": true, "severity": "critical", "status": "exploited", "bridge_type": "cross_chain", "seeded_at": "2025-01-08T20:00:00Z"}'::jsonb,
    NOW(),
    NOW()
);

-- Insert sample contract analyses using the existing schema
INSERT INTO contract_analyses (
    id, contract_address, network, status, model, progress, current_step,
    source_metadata, findings, findings_count, metadata, analysis_options,
    triggered_by, created_at, updated_at, started_at, completed_at
)
SELECT 
    gen_random_uuid(),
    fc.address,
    fc.network,
    'completed',
    'gpt-4-turbo',
    100,
    'analysis_complete',
    json_build_object(
        'contract_name', fc.name,
        'contract_type', fc.contract_type,
        'verification_status', fc.is_verified,
        'deployment_date', fc.deployment_date
    ),
    -- Generate findings based on risk score and vulnerabilities
    CASE 
        WHEN fc.risk_score > 80 THEN 
            json_build_object(
                'critical_vulnerabilities', jsonb_array_length(COALESCE(fc.vulnerabilities, '[]'::jsonb)),
                'risk_assessment', 'CRITICAL - Immediate action required',
                'security_score', 100 - fc.risk_score,
                'exploited', CASE WHEN fc.exploit_details IS NOT NULL THEN true ELSE false END,
                'audit_status', CASE WHEN fc.audit_firms IS NOT NULL THEN 'audited' ELSE 'unaudited' END,
                'recommendations', [
                    'Immediate security review required',
                    'Consider pausing contract operations',
                    'Implement emergency procedures',
                    'Enhanced monitoring required'
                ]
            )
        WHEN fc.risk_score > 40 THEN
            json_build_object(
                'medium_risk_issues', (random() * 3 + 1)::int,
                'risk_assessment', 'MEDIUM - Monitor closely',
                'security_score', 100 - fc.risk_score,
                'audit_status', CASE WHEN fc.audit_firms IS NOT NULL THEN 'audited' ELSE 'unaudited' END,
                'recommendations', [
                    'Regular security monitoring',
                    'Consider additional audits',
                    'Implement best practices',
                    'Monitor for suspicious activity'
                ]
            )
        ELSE
            json_build_object(
                'low_risk_issues', (random() * 2)::int,
                'risk_assessment', 'LOW - Well secured',
                'security_score', 100 - fc.risk_score,
                'audit_status', 'professionally_audited',
                'recommendations', [
                    'Maintain current security practices',
                    'Continue regular monitoring',
                    'Keep dependencies updated',
                    'Regular security reviews'
                ]
            )
    END,
    -- Findings count based on risk
    CASE 
        WHEN fc.risk_score > 80 THEN (random() * 8 + 5)::int
        WHEN fc.risk_score > 40 THEN (random() * 4 + 2)::int
        ELSE (random() * 2)::int
    END,
    json_build_object(
        'analysis_version', '2.0.0',
        'seed_generated', true,
        'risk_category', 
            CASE 
                WHEN fc.risk_score >= 80 THEN 'critical'
                WHEN fc.risk_score >= 60 THEN 'high'
                WHEN fc.risk_score >= 40 THEN 'medium'
                WHEN fc.risk_score >= 20 THEN 'low'
                ELSE 'very_low'
            END,
        'total_value_locked', fc.total_value_locked,
        'transaction_count', fc.transaction_count
    ),
    json_build_object(
        'analysis_type', 'comprehensive_security_audit',
        'include_gas_analysis', true,
        'include_vulnerability_scan', true,
        'deep_analysis', true
    ),
    'seeder_script',
    NOW() - INTERVAL '1 day' * (random() * 7)::int, -- Random time in last week
    NOW() - INTERVAL '1 day' * (random() * 7)::int,
    NOW() - INTERVAL '1 day' * (random() * 7)::int,
    NOW() - INTERVAL '1 day' * (random() * 7)::int
FROM famous_contracts fc;

-- Create summary statistics
SELECT 
    'Famous Contracts Seeded Successfully!' as message,
    COUNT(*) as total_contracts,
    COUNT(*) FILTER (WHERE risk_score >= 80) as critical_risk,
    COUNT(*) FILTER (WHERE risk_score >= 60 AND risk_score < 80) as high_risk,
    COUNT(*) FILTER (WHERE risk_score >= 40 AND risk_score < 60) as medium_risk,
    COUNT(*) FILTER (WHERE risk_score < 40) as low_risk,
    COUNT(*) FILTER (WHERE exploit_details IS NOT NULL) as exploited_contracts,
    SUM(total_value_locked) as total_tvl
FROM famous_contracts;

-- Show the seeded contracts
SELECT 
    name,
    address,
    network,
    contract_type,
    CASE 
        WHEN risk_score >= 80 THEN 'CRITICAL'
        WHEN risk_score >= 60 THEN 'HIGH'
        WHEN risk_score >= 40 THEN 'MEDIUM'
        ELSE 'LOW'
    END as risk_level,
    CASE WHEN exploit_details IS NOT NULL THEN '🚨 EXPLOITED' ELSE '✅ SECURE' END as status,
    '$' || (total_value_locked / 1000000)::text || 'M' as tvl
FROM famous_contracts
ORDER BY risk_score DESC;
