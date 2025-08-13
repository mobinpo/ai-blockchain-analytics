-- Famous 5 Contracts Database Seeding SQL
-- Includes Uniswap V3, Aave V3, Euler Finance exploit, Compound V3, and Multichain exploit
-- Generated: 2025-08-12

-- Clear existing data
TRUNCATE TABLE famous_contracts CASCADE;
TRUNCATE TABLE contract_analyses CASCADE;
TRUNCATE TABLE analyses CASCADE;
TRUNCATE TABLE findings CASCADE;

-- Insert Famous Contracts
INSERT INTO famous_contracts (
    id, name, address, network, type, category, description, tvl, status, 
    risk_score, security_rating, verification_status, deployment_date, 
    findings_count, gas_optimization_score, has_vulnerabilities, 
    audit_reports, github_url, exploit_date, exploit_amount,
    created_at, updated_at
) VALUES
-- 1. Uniswap V3 SwapRouter
(
    1,
    'Uniswap V3 SwapRouter',
    '0xE592427A0AEce92De3Edee1F18E0157C05861564',
    'ethereum',
    'defi',
    'dex',
    'Uniswap V3 SwapRouter - the most popular decentralized exchange router for token swaps',
    4200000000,
    'active',
    15,
    'A+',
    'verified',
    '2021-05-05',
    2,
    92,
    false,
    '["Trail of Bits", "Consensys Diligence", "ABDK"]',
    'https://github.com/Uniswap/v3-periphery',
    NULL,
    NULL,
    NOW(),
    NOW()
),

-- 2. Aave V3 Pool
(
    2,
    'Aave V3 Pool',
    '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
    'ethereum',
    'defi',
    'lending',
    'Aave V3 Pool contract - leading decentralized lending and borrowing protocol',
    6800000000,
    'active',
    25,
    'A',
    'verified',
    '2022-03-16',
    3,
    88,
    false,
    '["OpenZeppelin", "SigmaPrime", "Consensys", "Certora"]',
    'https://github.com/aave/aave-v3-core',
    NULL,
    NULL,
    NOW(),
    NOW()
),

-- 3. Euler Finance (EXPLOITED)
(
    3,
    'Euler Finance (EXPLOITED)',
    '0x27182842E098f60e3D576794A5bFFb0777E025d3',
    'ethereum',
    'defi',
    'lending',
    'Euler Finance lending protocol - suffered $200M exploit in March 2023',
    0,
    'exploited',
    95,
    'F',
    'verified',
    '2021-08-20',
    8,
    65,
    true,
    '["Halborn", "Solidified"]',
    'https://github.com/euler-xyz/euler-contracts',
    '2023-03-13',
    200000000,
    NOW(),
    NOW()
),

-- 4. Compound V3 Comet
(
    4,
    'Compound V3 Comet',
    '0xc3d688B66703497DAA19211EEdff47f25384cdc3',
    'ethereum',
    'defi',
    'lending',
    'Compound V3 (Comet) - next generation lending protocol with improved architecture',
    1200000000,
    'active',
    35,
    'B+',
    'verified',
    '2022-08-26',
    4,
    85,
    false,
    '["OpenZeppelin", "ChainSecurity", "Code4rena"]',
    'https://github.com/compound-finance/comet',
    NULL,
    NULL,
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
    'cross_chain',
    'Multichain (Anyswap) Bridge - suffered major exploit in July 2023',
    0,
    'exploited',
    98,
    'F',
    'verified',
    '2020-10-15',
    10,
    70,
    true,
    '["Slowmist", "Peckshield"]',
    'https://github.com/anyswap/anyswap-v1-core',
    '2023-07-06',
    126000000,
    NOW(),
    NOW()
);

-- Insert Contract Analyses
INSERT INTO contract_analyses (
    id, contract_address, network, contract_name, analysis_status,
    risk_score, security_score, gas_optimization_score, findings_count,
    critical_issues, high_issues, medium_issues, low_issues, info_issues,
    analysis_result, analyzed_at, created_at, updated_at
) VALUES
-- Uniswap V3 Analysis
(
    1,
    '0xE592427A0AEce92De3Edee1F18E0157C05861564',
    'ethereum',
    'Uniswap V3 SwapRouter',
    'completed',
    15,
    85,
    92,
    2,
    0, 0, 0, 1, 1,
    '{"summary": "Uniswap V3 SwapRouter - the most popular decentralized exchange router for token swaps", "recommendations": ["Multiple audit reports available - good security practice"], "audit_reports": ["Trail of Bits", "Consensys Diligence", "ABDK"], "github_url": "https://github.com/Uniswap/v3-periphery"}',
    NOW(),
    NOW(),
    NOW()
),

-- Aave V3 Analysis
(
    2,
    '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
    'ethereum',
    'Aave V3 Pool',
    'completed',
    25,
    75,
    88,
    3,
    0, 0, 2, 1, 1,
    '{"summary": "Aave V3 Pool contract - leading decentralized lending and borrowing protocol", "recommendations": ["Multiple audit reports available - good security practice"], "audit_reports": ["OpenZeppelin", "SigmaPrime", "Consensys", "Certora"], "github_url": "https://github.com/aave/aave-v3-core"}',
    NOW(),
    NOW(),
    NOW()
),

-- Euler Finance Analysis
(
    3,
    '0x27182842E098f60e3D576794A5bFFb0777E025d3',
    'ethereum',
    'Euler Finance (EXPLOITED)',
    'completed',
    95,
    5,
    65,
    8,
    2, 2, 2, 2, 0,
    '{"summary": "Euler Finance lending protocol - suffered $200M exploit in March 2023", "recommendations": ["Immediate security review required due to known vulnerabilities", "Consider pausing contract operations until issues are resolved", "High risk contract - implement additional monitoring"], "audit_reports": ["Halborn", "Solidified"], "github_url": "https://github.com/euler-xyz/euler-contracts"}',
    NOW(),
    NOW(),
    NOW()
),

-- Compound V3 Analysis
(
    4,
    '0xc3d688B66703497DAA19211EEdff47f25384cdc3',
    'ethereum',
    'Compound V3 Comet',
    'completed',
    35,
    65,
    85,
    4,
    0, 0, 2, 2, 1,
    '{"summary": "Compound V3 (Comet) - next generation lending protocol with improved architecture", "recommendations": ["Multiple audit reports available - good security practice"], "audit_reports": ["OpenZeppelin", "ChainSecurity", "Code4rena"], "github_url": "https://github.com/compound-finance/comet"}',
    NOW(),
    NOW(),
    NOW()
),

-- Multichain Bridge Analysis
(
    5,
    '0x765277EebeCA2e31912C9946eae1021199B39C61',
    'ethereum',
    'Multichain Bridge (EXPLOITED)',
    'completed',
    98,
    2,
    70,
    10,
    2, 3, 3, 2, 0,
    '{"summary": "Multichain (Anyswap) Bridge - suffered major exploit in July 2023", "recommendations": ["Immediate security review required due to known vulnerabilities", "Consider pausing contract operations until issues are resolved", "High risk contract - implement additional monitoring"], "audit_reports": ["Slowmist", "Peckshield"], "github_url": "https://github.com/anyswap/anyswap-v1-core"}',
    NOW(),
    NOW(),
    NOW()
);

-- Insert Main Analyses
INSERT INTO analyses (
    id, contract_address, network, status, analysis_type, progress,
    risk_assessment, gas_analysis, security_analysis, completion_time,
    ai_confidence, metadata, created_at, updated_at
) VALUES
-- Uniswap V3
(
    1,
    '0xE592427A0AEce92De3Edee1F18E0157C05861564',
    'ethereum',
    'completed',
    'comprehensive',
    100,
    'MINIMAL RISK - Excellent security profile',
    '{"optimization_score": 92, "estimated_gas_usage": {"deployment_gas": 1500000, "average_transaction_gas": 150000, "complex_operation_gas": 300000}, "optimization_suggestions": []}',
    '{"security_rating": "A+", "vulnerability_count": 0, "audit_status": "audited", "audit_firms": ["Trail of Bits", "Consensys Diligence", "ABDK"]}',
    120,
    0.95,
    '{"contract_type": "defi", "category": "dex", "tvl": 4200000000, "deployment_date": "2021-05-05", "verification_status": "verified"}',
    NOW(),
    NOW()
),

-- Aave V3
(
    2,
    '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
    'ethereum',
    'completed',
    'comprehensive',
    100,
    'LOW RISK - Generally safe for use',
    '{"optimization_score": 88, "estimated_gas_usage": {"deployment_gas": 2000000, "average_transaction_gas": 200000, "complex_operation_gas": 400000}, "optimization_suggestions": ["Optimize storage operations"]}',
    '{"security_rating": "A", "vulnerability_count": 0, "audit_status": "audited", "audit_firms": ["OpenZeppelin", "SigmaPrime", "Consensys", "Certora"]}',
    150,
    0.95,
    '{"contract_type": "defi", "category": "lending", "tvl": 6800000000, "deployment_date": "2022-03-16", "verification_status": "verified"}',
    NOW(),
    NOW()
),

-- Euler Finance
(
    3,
    '0x27182842E098f60e3D576794A5bFFb0777E025d3',
    'ethereum',
    'completed',
    'comprehensive',
    100,
    'CRITICAL RISK - Immediate action required',
    '{"optimization_score": 65, "estimated_gas_usage": {"deployment_gas": 2000000, "average_transaction_gas": 200000, "complex_operation_gas": 400000}, "optimization_suggestions": ["Optimize storage operations", "Consider function modifiers for common checks", "Review loop operations for efficiency"]}',
    '{"security_rating": "F", "vulnerability_count": 4, "audit_status": "audited", "audit_firms": ["Halborn", "Solidified"]}',
    180,
    0.95,
    '{"contract_type": "defi", "category": "lending", "tvl": 0, "deployment_date": "2021-08-20", "verification_status": "verified"}',
    NOW(),
    NOW()
),

-- Compound V3
(
    4,
    '0xc3d688B66703497DAA19211EEdff47f25384cdc3',
    'ethereum',
    'completed',
    'comprehensive',
    100,
    'MEDIUM RISK - Standard precautions recommended',
    '{"optimization_score": 85, "estimated_gas_usage": {"deployment_gas": 2000000, "average_transaction_gas": 200000, "complex_operation_gas": 400000}, "optimization_suggestions": ["Optimize storage operations"]}',
    '{"security_rating": "B+", "vulnerability_count": 0, "audit_status": "audited", "audit_firms": ["OpenZeppelin", "ChainSecurity", "Code4rena"]}',
    160,
    0.95,
    '{"contract_type": "defi", "category": "lending", "tvl": 1200000000, "deployment_date": "2022-08-26", "verification_status": "verified"}',
    NOW(),
    NOW()
),

-- Multichain Bridge
(
    5,
    '0x765277EebeCA2e31912C9946eae1021199B39C61',
    'ethereum',
    'completed',
    'comprehensive',
    100,
    'CRITICAL RISK - Immediate action required',
    '{"optimization_score": 70, "estimated_gas_usage": {"deployment_gas": 3000000, "average_transaction_gas": 300000, "complex_operation_gas": 600000}, "optimization_suggestions": ["Optimize storage operations", "Consider function modifiers for common checks", "Review loop operations for efficiency"]}',
    '{"security_rating": "F", "vulnerability_count": 5, "audit_status": "audited", "audit_firms": ["Slowmist", "Peckshield"]}',
    75,
    0.95,
    '{"contract_type": "bridge", "category": "cross_chain", "tvl": 0, "deployment_date": "2020-10-15", "verification_status": "verified"}',
    NOW(),
    NOW()
);

-- Insert Findings
INSERT INTO findings (
    id, analysis_id, contract_address, title, description, severity, category,
    recommendation, line_number, confidence_score, impact_score, likelihood_score,
    cvss_score, cwe_id, owasp_category, remediation_effort, false_positive_probability,
    evidence, metadata, created_at, updated_at
) VALUES
-- Uniswap V3 Findings
(1, 1, '0xE592427A0AEce92De3Edee1F18E0157C05861564', 'Gas Optimization Opportunity', 'Minor gas optimization possible in multicall function', 'low', 'gas_optimization', 'Consider batching operations more efficiently', 156, 0.7, 4.0, 0.7, 2.5, NULL, 'SC06-Gas Optimization', 'low', 0.3, '{"detection_method": "static_analysis", "pattern_matched": "gas_optimization", "context": "Minor gas optimization possible in multicall function"}', '{"contract_name": "Uniswap V3 SwapRouter", "network": "ethereum", "finding_type": "gas_optimization"}', NOW(), NOW()),

(2, 1, '0xE592427A0AEce92De3Edee1F18E0157C05861564', 'Front-Running Protection', 'Implements deadline protection against MEV attacks', 'info', 'best_practices', 'Excellent implementation of front-running protection', NULL, 1.0, 2.0, 1.0, 1.0, NULL, 'SC08-Other', 'minimal', 0.0, '{"detection_method": "static_analysis", "pattern_matched": "best_practices", "context": "Implements deadline protection against MEV attacks"}', '{"contract_name": "Uniswap V3 SwapRouter", "network": "ethereum", "finding_type": "best_practices"}', NOW(), NOW()),

-- Aave V3 Findings
(3, 2, '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2', 'Liquidation Logic Complexity', 'Complex liquidation logic requires careful monitoring', 'medium', 'code_quality', 'Consider additional testing for edge cases', 234, 0.8, 6.0, 0.8, 4.4, NULL, 'SC08-Other', 'medium', 0.2, '{"detection_method": "static_analysis", "pattern_matched": "code_quality", "context": "Complex liquidation logic requires careful monitoring"}', '{"contract_name": "Aave V3 Pool", "network": "ethereum", "finding_type": "code_quality"}', NOW(), NOW()),

(4, 2, '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2', 'Interest Rate Model', 'Sophisticated interest rate calculation model', 'low', 'economic', 'Monitor rate model performance under stress', 145, 0.6, 4.0, 0.6, 2.1, NULL, 'SC08-Other', 'low', 0.4, '{"detection_method": "static_analysis", "pattern_matched": "economic", "context": "Sophisticated interest rate calculation model"}', '{"contract_name": "Aave V3 Pool", "network": "ethereum", "finding_type": "economic"}', NOW(), NOW()),

(5, 2, '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2', 'Access Control Implementation', 'Robust role-based access control system', 'info', 'security', 'Excellent security architecture', NULL, 1.0, 2.0, 1.0, 1.0, 'CWE-284', 'SC02-Access Control', 'minimal', 0.0, '{"detection_method": "static_analysis", "pattern_matched": "security", "context": "Robust role-based access control system"}', '{"contract_name": "Aave V3 Pool", "network": "ethereum", "finding_type": "security"}', NOW(), NOW()),

-- Euler Finance Critical Findings
(6, 3, '0x27182842E098f60e3D576794A5bFFb0777E025d3', 'Donation Attack Vulnerability', 'Vulnerable to donation attacks on empty markets allowing price manipulation', 'critical', 'vulnerability', 'Implement minimum liquidity requirements and donation attack protection', 187, 1.0, 10.0, 1.0, 9.5, 'CWE-691', 'SC01-Reentrancy', 'high', 0.0, '{"detection_method": "static_analysis", "pattern_matched": "vulnerability", "context": "Vulnerable to donation attacks on empty markets allowing price manipulation"}', '{"contract_name": "Euler Finance (EXPLOITED)", "network": "ethereum", "finding_type": "vulnerability"}', NOW(), NOW()),

(7, 3, '0x27182842E098f60e3D576794A5bFFb0777E025d3', 'Liquidity Check Bypass', 'Self-liquidation allows bypassing liquidity checks', 'critical', 'vulnerability', 'Prevent self-liquidation or add additional checks', 298, 1.0, 10.0, 1.0, 9.5, 'CWE-691', 'SC01-Reentrancy', 'high', 0.0, '{"detection_method": "static_analysis", "pattern_matched": "vulnerability", "context": "Self-liquidation allows bypassing liquidity checks"}', '{"contract_name": "Euler Finance (EXPLOITED)", "network": "ethereum", "finding_type": "vulnerability"}', NOW(), NOW()),

(8, 3, '0x27182842E098f60e3D576794A5bFFb0777E025d3', 'Flash Loan Integration Risk', 'Flash loan integration increases attack surface', 'high', 'integration', 'Add flash loan specific protections', 456, 0.9, 8.0, 0.9, 6.8, NULL, 'SC08-Other', 'medium', 0.1, '{"detection_method": "static_analysis", "pattern_matched": "integration", "context": "Flash loan integration increases attack surface"}', '{"contract_name": "Euler Finance (EXPLOITED)", "network": "ethereum", "finding_type": "integration"}', NOW(), NOW()),

(9, 3, '0x27182842E098f60e3D576794A5bFFb0777E025d3', 'Price Oracle Manipulation', 'Price oracle susceptible to manipulation in thin markets', 'high', 'oracle', 'Implement TWAP oracles and circuit breakers', 123, 0.95, 8.0, 0.95, 7.2, 'CWE-345', 'SC04-Oracle Manipulation', 'medium', 0.05, '{"detection_method": "static_analysis", "pattern_matched": "oracle", "context": "Price oracle susceptible to manipulation in thin markets"}', '{"contract_name": "Euler Finance (EXPLOITED)", "network": "ethereum", "finding_type": "oracle"}', NOW(), NOW()),

-- Multichain Bridge Critical Findings  
(10, 5, '0x765277EebeCA2e31912C9946eae1021199B39C61', 'Centralized Key Management', 'Single point of failure in multi-signature key management', 'critical', 'centralization', 'Implement decentralized key management with threshold schemes', NULL, 1.0, 10.0, 1.0, 9.5, 'CWE-250', 'SC05-Centralization Risk', 'high', 0.0, '{"detection_method": "static_analysis", "pattern_matched": "centralization", "context": "Single point of failure in multi-signature key management"}', '{"contract_name": "Multichain Bridge (EXPLOITED)", "network": "ethereum", "finding_type": "centralization"}', NOW(), NOW()),

(11, 5, '0x765277EebeCA2e31912C9946eae1021199B39C61', 'MPC Wallet Compromise', 'Multi-party computation wallet private keys were compromised', 'critical', 'key_management', 'Implement hardware security modules and key rotation', NULL, 1.0, 10.0, 1.0, 9.5, 'CWE-320', 'SC08-Other', 'high', 0.0, '{"detection_method": "static_analysis", "pattern_matched": "key_management", "context": "Multi-party computation wallet private keys were compromised"}', '{"contract_name": "Multichain Bridge (EXPLOITED)", "network": "ethereum", "finding_type": "key_management"}', NOW(), NOW());

-- Reset sequences
SELECT setval('famous_contracts_id_seq', (SELECT MAX(id) FROM famous_contracts));
SELECT setval('contract_analyses_id_seq', (SELECT MAX(id) FROM contract_analyses));
SELECT setval('analyses_id_seq', (SELECT MAX(id) FROM analyses));
SELECT setval('findings_id_seq', (SELECT MAX(id) FROM findings));

-- Verification queries
SELECT 
    'Famous Contracts' as table_name,
    COUNT(*) as record_count,
    COUNT(CASE WHEN has_vulnerabilities = true THEN 1 END) as vulnerable_contracts,
    AVG(risk_score) as avg_risk_score
FROM famous_contracts

UNION ALL

SELECT 
    'Contract Analyses' as table_name,
    COUNT(*) as record_count,
    SUM(critical_issues + high_issues) as critical_high_issues,
    AVG(security_score) as avg_security_score
FROM contract_analyses

UNION ALL

SELECT 
    'Analyses' as table_name,
    COUNT(*) as record_count,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_analyses,
    AVG(ai_confidence) as avg_confidence
FROM analyses

UNION ALL

SELECT 
    'Findings' as table_name,
    COUNT(*) as record_count,
    COUNT(CASE WHEN severity IN ('critical', 'high') THEN 1 END) as critical_high_findings,
    AVG(confidence_score) as avg_confidence
FROM findings;

-- Summary report
SELECT 
    fc.name,
    fc.address,
    fc.security_rating,
    fc.risk_score,
    fc.has_vulnerabilities,
    fc.tvl,
    fc.status,
    ca.findings_count,
    ca.critical_issues,
    ca.high_issues
FROM famous_contracts fc
JOIN contract_analyses ca ON fc.address = ca.contract_address
ORDER BY fc.risk_score DESC;