-- =============================================================================
-- Famous Smart Contracts Database Seeding (Simplified)
-- Working with existing constraints
-- =============================================================================

-- Clear existing data first
TRUNCATE TABLE famous_contracts CASCADE;

-- Insert 5 famous smart contracts (simplified to fit constraints)
INSERT INTO famous_contracts (
    name, address, network, contract_type, description, deployment_date,
    total_value_locked, transaction_count, creator_address, is_verified,
    risk_score, security_features, vulnerabilities, audit_firms,
    gas_optimization, code_quality, exploit_details, metadata,
    created_at, updated_at
) VALUES 

-- 1. Uniswap V3 Router
(
    'Uniswap V3 Router',
    '0xE592427A0AEce92De3Edee1F18E0157C05861564',
    'ethereum',
    'defi',
    'Uniswap V3 SwapRouter - Main router for DEX operations',
    '2021-05-05',
    2500000000,
    15000000,
    '0x1a9C8182C09F50C8318d769245beA52c32BE35BC',
    true,
    15,
    '["Multi-sig governance", "Time-locked upgrades", "Audited"]'::jsonb,
    '[]'::jsonb,
    '["Trail of Bits", "ABDK"]'::jsonb,
    'High',
    'Excellent',
    null,
    '{"type": "dex", "version": "v3"}'::jsonb,
    NOW(),
    NOW()
),

-- 2. Aave V3 Pool
(
    'Aave V3 Pool',
    '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
    'ethereum',
    'defi',
    'Aave V3 Lending Pool - Core lending protocol',
    '2022-03-16',
    6800000000,
    8500000,
    '0xd784927Ff2f95ba542BfC824c8a8a98F3495f6b5',
    true,
    20,
    '["Risk governance", "Liquidation protection", "Flash loans"]'::jsonb,
    '["Flash loan risks (mitigated)"]'::jsonb,
    '["OpenZeppelin", "SigmaPrime"]'::jsonb,
    'High',
    'Excellent',
    null,
    '{"type": "lending", "version": "v3"}'::jsonb,
    NOW(),
    NOW()
),

-- 3. Compound V2 cDAI
(
    'Compound V2 cDAI',
    '0x5d3a536E4D6DbD6114cc1Ead35777bAB948E3643',
    'ethereum',
    'defi',
    'Compound cDAI - Interest-bearing DAI token',
    '2019-05-07',
    1200000000,
    12000000,
    '0x3d9819210A31b4961b30EF54bE2aeD79B9c9Cd3B',
    true,
    25,
    '["Interest rate models", "Collateral factors"]'::jsonb,
    '["Oracle manipulation", "Governance risks"]'::jsonb,
    '["OpenZeppelin", "Trail of Bits"]'::jsonb,
    'Medium',
    'Good',
    null,
    '{"type": "lending", "version": "v2"}'::jsonb,
    NOW(),
    NOW()
),

-- 4. Euler Finance (EXPLOITED)
(
    'Euler Finance',
    '0x27182842E098f60e3D576794A5bFFb0777E025d3',
    'ethereum',
    'defi',
    'Euler Finance Protocol - EXPLOITED March 2023',
    '2021-12-15',
    0,
    450000,
    '0x3520d5a913427E6F0D6A83E07ccD4A4da316e4d3',
    true,
    95,
    '["Risk-adjusted borrowing", "Reactive rates"]'::jsonb,
    '["CRITICAL: Donation attack vulnerability", "Price manipulation"]'::jsonb,
    '["Sherlock", "Zellic"]'::jsonb,
    'High',
    'Poor',
    '{"date": "2023-03-13", "amount": 197000000, "type": "donation_attack"}'::jsonb,
    '{"exploited": true, "severity": "critical"}'::jsonb,
    NOW(),
    NOW()
),

-- 5. BNB Chain Bridge (EXPLOITED)
(
    'BNB Chain Bridge',
    '0x8894E0a0c962CB723c1976a4421c95949bE2D4E3',
    'bsc',
    'bridge',
    'BNB Chain Bridge - EXPLOITED October 2022',
    '2020-09-01',
    0,
    2800000,
    '0x0000000000000000000000000000000000001004',
    true,
    98,
    '["Multi-sig validation", "Merkle proofs"]'::jsonb,
    '["CRITICAL: Proof forgery", "Merkle manipulation"]'::jsonb,
    '["PeckShield", "SlowMist"]'::jsonb,
    'Medium',
    'Poor',
    '{"date": "2022-10-07", "amount": 586000000, "type": "proof_forgery"}'::jsonb,
    '{"exploited": true, "severity": "critical"}'::jsonb,
    NOW(),
    NOW()
);

-- Show what was inserted
SELECT 
    'SUCCESS: Famous contracts seeded!' as status,
    COUNT(*) as contracts_inserted
FROM famous_contracts;

-- Display the contracts
SELECT 
    name,
    LEFT(address, 10) || '...' as address_short,
    network,
    contract_type,
    risk_score,
    CASE 
        WHEN exploit_details IS NOT NULL THEN 'EXPLOITED 🚨'
        WHEN risk_score < 30 THEN 'LOW RISK ✅'
        ELSE 'MEDIUM RISK ⚠️'
    END as status
FROM famous_contracts
ORDER BY risk_score DESC;
