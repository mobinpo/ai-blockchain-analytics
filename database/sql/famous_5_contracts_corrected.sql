-- Famous 5 Contracts Database Seeding SQL (Schema Corrected)
-- Includes Uniswap V3, Aave V3, Euler Finance exploit, Compound V3, and Multichain exploit
-- Generated: 2025-08-12 - Schema Updated

-- Clear existing data
TRUNCATE TABLE famous_contracts CASCADE;
TRUNCATE TABLE contract_analyses CASCADE;

-- Insert Famous Contracts (using correct schema)
INSERT INTO famous_contracts (
    id, name, address, network, contract_type, description, 
    deployment_date, total_value_locked, transaction_count, 
    creator_address, is_verified, risk_score, security_features, 
    vulnerabilities, audit_firms, gas_optimization, code_quality, 
    exploit_details, metadata, created_at, updated_at
) VALUES
-- 1. Uniswap V3 SwapRouter
(
    1,
    'Uniswap V3 SwapRouter',
    '0xE592427A0AEce92De3Edee1F18E0157C05861564',
    'ethereum',
    'dex',
    'Uniswap V3 SwapRouter - the most popular decentralized exchange router for token swaps',
    '2021-05-05',
    4200000000,
    15000000,
    '0x1F98431c8aD98523631AE4a59f267346ea31F984',
    true,
    15,
    '{"access_control": true, "reentrancy_protection": true, "deadline_protection": true}',
    '[]',
    '["Trail of Bits", "Consensys Diligence", "ABDK"]',
    'Excellent',
    'High',
    NULL,
    '{"category": "dex", "github_url": "https://github.com/Uniswap/v3-periphery", "security_rating": "A+"}',
    NOW(),
    NOW()
),

-- 2. Aave V3 Pool
(
    2,
    'Aave V3 Pool',
    '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
    'ethereum',
    'lending',
    'Aave V3 Pool contract - leading decentralized lending and borrowing protocol',
    '2022-03-16',
    6800000000,
    8500000,
    '0xEC568fffba86c094cf06b22134B23074DFE2252c',
    true,
    25,
    '{"access_control": true, "liquidation_protection": true, "interest_rate_model": true}',
    '[]',
    '["OpenZeppelin", "SigmaPrime", "Consensys", "Certora"]',
    'Good',
    'High',
    NULL,
    '{"category": "lending", "github_url": "https://github.com/aave/aave-v3-core", "security_rating": "A"}',
    NOW(),
    NOW()
),

-- 3. Euler Finance (EXPLOITED)
(
    3,
    'Euler Finance (EXPLOITED)',
    '0x27182842E098f60e3D576794A5bFFb0777E025d3',
    'ethereum',
    'lending',
    'Euler Finance lending protocol - suffered $200M exploit in March 2023',
    '2021-08-20',
    0,
    3200000,
    '0x5C69bEe701ef814a2B6a3EDD4B1652CB9cc5aA6f',
    true,
    95,
    '{"liquidation_system": true, "isolated_markets": true}',
    '["donation_attack", "self_liquidation", "flash_loan_exploit"]',
    '["Halborn", "Solidified"]',
    'Poor',
    'Medium',
    '{"exploit_date": "2023-03-13", "exploit_amount": 200000000, "exploit_type": "donation_attack", "affected_markets": ["USDC", "WETH", "DAI", "WBTC"]}',
    '{"category": "lending", "github_url": "https://github.com/euler-xyz/euler-contracts", "security_rating": "F", "status": "exploited"}',
    NOW(),
    NOW()
),

-- 4. Compound V3 Comet
(
    4,
    'Compound V3 Comet',
    '0xc3d688B66703497DAA19211EEdff47f25384cdc3',
    'ethereum',
    'lending',
    'Compound V3 (Comet) - next generation lending protocol with improved architecture',
    '2022-08-26',
    1200000000,
    2100000,
    '0x316f9708bB98af7dA9c68C1C3b5e79039cD336E3',
    true,
    35,
    '{"single_collateral": true, "base_token_model": true, "improved_governance": true}',
    '[]',
    '["OpenZeppelin", "ChainSecurity", "Code4rena"]',
    'Good',
    'High',
    NULL,
    '{"category": "lending", "github_url": "https://github.com/compound-finance/comet", "security_rating": "B+"}',
    NOW(),
    NOW()
),

-- 5. Multichain Bridge (EXPLOITED)
(
    5,
    'Multichain Bridge (EXPLOITED)',
    '0x765277EebeCA2e31912C9946eae1021199B39C61',
    'ethereum',
    'bridge',
    'Multichain (Anyswap) Bridge - suffered major exploit in July 2023',
    '2020-10-15',
    0,
    12500000,
    '0x6b7a87899490EcE95443e979cA9485CBE7E71522',
    true,
    98,
    '{"multi_signature": true, "cross_chain_validation": true}',
    '["centralized_keys", "mpc_compromise", "insufficient_validation"]',
    '["Slowmist", "Peckshield"]',
    'Poor',
    'Medium',
    '{"exploit_date": "2023-07-06", "exploit_amount": 126000000, "exploit_type": "key_compromise", "affected_chains": ["ethereum", "fantom", "moonbeam", "milkomeda"]}',
    '{"category": "cross_chain", "github_url": "https://github.com/anyswap/anyswap-v1-core", "security_rating": "F", "status": "exploited"}',
    NOW(),
    NOW()
);

-- Insert Contract Analyses (using correct schema)
INSERT INTO contract_analyses (
    id, contract_id, analysis_type, status, risk_score, 
    findings, recommendations, analysis_date, analyzer_version,
    execution_time_ms, confidence_score, metadata, created_at, updated_at
) VALUES
-- Uniswap V3 Analysis
(
    1,
    1,
    'comprehensive',
    'completed',
    15,
    '[
        {
            "title": "Gas Optimization Opportunity",
            "severity": "low",
            "category": "gas_optimization",
            "description": "Minor gas optimization possible in multicall function",
            "recommendation": "Consider batching operations more efficiently",
            "confidence": 0.7
        },
        {
            "title": "Front-Running Protection",
            "severity": "info", 
            "category": "best_practices",
            "description": "Implements deadline protection against MEV attacks",
            "recommendation": "Excellent implementation of front-running protection",
            "confidence": 1.0
        }
    ]',
    '["Multiple audit reports available - good security practice", "Excellent security architecture", "Consider minor gas optimizations"]',
    NOW(),
    '1.0.0',
    120000,
    95.00,
    '{"github_url": "https://github.com/Uniswap/v3-periphery", "audit_reports": ["Trail of Bits", "Consensys Diligence", "ABDK"]}',
    NOW(),
    NOW()
),

-- Aave V3 Analysis
(
    2,
    2,
    'comprehensive',
    'completed',
    25,
    '[
        {
            "title": "Liquidation Logic Complexity",
            "severity": "medium",
            "category": "code_quality", 
            "description": "Complex liquidation logic requires careful monitoring",
            "recommendation": "Consider additional testing for edge cases",
            "confidence": 0.8
        },
        {
            "title": "Interest Rate Model",
            "severity": "low",
            "category": "economic",
            "description": "Sophisticated interest rate calculation model", 
            "recommendation": "Monitor rate model performance under stress",
            "confidence": 0.6
        },
        {
            "title": "Access Control Implementation",
            "severity": "info",
            "category": "security",
            "description": "Robust role-based access control system",
            "recommendation": "Excellent security architecture",
            "confidence": 1.0
        }
    ]',
    '["Multiple audit reports available - good security practice", "Monitor complex liquidation logic", "Strong access control implementation"]',
    NOW(),
    '1.0.0',
    150000,
    85.00,
    '{"github_url": "https://github.com/aave/aave-v3-core", "audit_reports": ["OpenZeppelin", "SigmaPrime", "Consensys", "Certora"]}',
    NOW(),
    NOW()
),

-- Euler Finance Analysis
(
    3,
    3,
    'comprehensive',
    'completed',
    95,
    '[
        {
            "title": "Donation Attack Vulnerability",
            "severity": "critical",
            "category": "vulnerability",
            "description": "Vulnerable to donation attacks on empty markets allowing price manipulation",
            "recommendation": "Implement minimum liquidity requirements and donation attack protection",
            "confidence": 1.0
        },
        {
            "title": "Liquidity Check Bypass", 
            "severity": "critical",
            "category": "vulnerability",
            "description": "Self-liquidation allows bypassing liquidity checks",
            "recommendation": "Prevent self-liquidation or add additional checks",
            "confidence": 1.0
        },
        {
            "title": "Flash Loan Integration Risk",
            "severity": "high",
            "category": "integration",
            "description": "Flash loan integration increases attack surface",
            "recommendation": "Add flash loan specific protections",
            "confidence": 0.9
        },
        {
            "title": "Price Oracle Manipulation",
            "severity": "high", 
            "category": "oracle",
            "description": "Price oracle susceptible to manipulation in thin markets",
            "recommendation": "Implement TWAP oracles and circuit breakers",
            "confidence": 0.95
        }
    ]',
    '["Immediate security review required due to known vulnerabilities", "Consider pausing contract operations until issues are resolved", "High risk contract - implement additional monitoring"]',
    NOW(),
    '1.0.0',
    180000,
    95.00,
    '{"github_url": "https://github.com/euler-xyz/euler-contracts", "audit_reports": ["Halborn", "Solidified"], "exploit_details": {"date": "2023-03-13", "amount": "$200M"}}',
    NOW(),
    NOW()
),

-- Compound V3 Analysis
(
    4,
    4,
    'comprehensive',
    'completed',
    35,
    '[
        {
            "title": "Single Collateral Design",
            "severity": "medium",
            "category": "architecture",
            "description": "Single collateral per market design limits composability",
            "recommendation": "Consider multi-collateral support in future versions",
            "confidence": 0.7
        },
        {
            "title": "Base Token Accounting",
            "severity": "low",
            "category": "accounting",
            "description": "Complex base token accounting requires careful auditing",
            "recommendation": "Extensive testing of accounting edge cases",
            "confidence": 0.6
        },
        {
            "title": "Governor Architecture",
            "severity": "info",
            "category": "governance",
            "description": "Improved governance architecture over V2",
            "recommendation": "Excellent governance security improvements",
            "confidence": 1.0
        }
    ]',
    '["Multiple audit reports available - good security practice", "Monitor single collateral architecture limitations", "Strong governance improvements"]',
    NOW(),
    '1.0.0',
    160000,
    75.00,
    '{"github_url": "https://github.com/compound-finance/comet", "audit_reports": ["OpenZeppelin", "ChainSecurity", "Code4rena"]}',
    NOW(),
    NOW()
),

-- Multichain Bridge Analysis
(
    5,
    5,
    'comprehensive',
    'completed',
    98,
    '[
        {
            "title": "Centralized Key Management",
            "severity": "critical",
            "category": "centralization",
            "description": "Single point of failure in multi-signature key management",
            "recommendation": "Implement decentralized key management with threshold schemes",
            "confidence": 1.0
        },
        {
            "title": "MPC Wallet Compromise",
            "severity": "critical", 
            "category": "key_management",
            "description": "Multi-party computation wallet private keys were compromised",
            "recommendation": "Implement hardware security modules and key rotation",
            "confidence": 1.0
        },
        {
            "title": "Insufficient Withdrawal Limits",
            "severity": "high",
            "category": "access_control",
            "description": "No rate limiting on large withdrawals from bridge",
            "recommendation": "Implement daily/hourly withdrawal limits and delays",
            "confidence": 0.9
        },
        {
            "title": "Cross-Chain Validation Issues",
            "severity": "high",
            "category": "validation",
            "description": "Weak validation of cross-chain transaction proofs",
            "recommendation": "Strengthen cross-chain proof verification",
            "confidence": 0.9
        }
    ]',
    '["Immediate security review required due to known vulnerabilities", "Consider pausing contract operations until issues are resolved", "High risk contract - implement additional monitoring", "Critical centralization risks"]',
    NOW(),
    '1.0.0',
    75000,
    95.00,
    '{"github_url": "https://github.com/anyswap/anyswap-v1-core", "audit_reports": ["Slowmist", "Peckshield"], "exploit_details": {"date": "2023-07-06", "amount": "$126M"}}',
    NOW(),
    NOW()
);

-- Reset sequences
SELECT setval('famous_contracts_id_seq', (SELECT MAX(id) FROM famous_contracts));
SELECT setval('contract_analyses_id_seq', (SELECT MAX(id) FROM contract_analyses));

-- Verification queries
SELECT 
    'Famous Contracts' as table_name,
    COUNT(*) as record_count,
    COUNT(CASE WHEN risk_score > 70 THEN 1 END) as high_risk_contracts,
    AVG(risk_score) as avg_risk_score,
    COUNT(CASE WHEN exploit_details IS NOT NULL THEN 1 END) as exploited_contracts
FROM famous_contracts

UNION ALL

SELECT 
    'Contract Analyses' as table_name,
    COUNT(*) as record_count,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_analyses,
    AVG(risk_score) as avg_risk_score,
    AVG(confidence_score) as avg_confidence
FROM contract_analyses;

-- Summary report
SELECT 
    fc.name,
    fc.address,
    fc.risk_score,
    fc.contract_type,
    fc.total_value_locked,
    ca.status as analysis_status,
    ca.confidence_score,
    CASE 
        WHEN fc.exploit_details IS NOT NULL THEN 'EXPLOITED'
        WHEN fc.risk_score >= 90 THEN 'CRITICAL'
        WHEN fc.risk_score >= 70 THEN 'HIGH'
        WHEN fc.risk_score >= 50 THEN 'MEDIUM'
        WHEN fc.risk_score >= 30 THEN 'LOW'
        ELSE 'MINIMAL'
    END as risk_level
FROM famous_contracts fc
JOIN contract_analyses ca ON fc.id = ca.contract_id
ORDER BY fc.risk_score DESC;