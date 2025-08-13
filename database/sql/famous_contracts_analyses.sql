-- =============================================================================
-- Create Sample Analysis Records for Famous Contracts
-- =============================================================================

-- Insert comprehensive analysis records for each famous contract
INSERT INTO contract_analyses (
    id, contract_address, network, status, model, progress, current_step,
    source_metadata, findings, findings_count, metadata, analysis_options,
    triggered_by, processing_time_ms, tokens_used,
    created_at, updated_at, started_at, completed_at
)

-- Uniswap V3 Router Analysis
SELECT 
    gen_random_uuid(),
    '0xE592427A0AEce92De3Edee1F18E0157C05861564',
    'ethereum',
    'completed',
    'gpt-4-turbo',
    100,
    'analysis_complete',
    '{
        "contract_name": "Uniswap V3 Router", 
        "contract_type": "defi", 
        "deployment_date": "2021-05-05",
        "source_lines": 2847,
        "compiler_version": "0.7.6"
    }'::json,
    '{
        "security_score": 85,
        "risk_assessment": "LOW RISK - Well architected DEX router",
        "vulnerabilities": [],
        "gas_efficiency": "High - Optimized swap routing",
        "code_quality": "Excellent - Clean, well-documented",
        "audit_status": "Multiple professional audits completed",
        "recommendations": [
            "Continue regular security monitoring",
            "Monitor for MEV manipulation patterns", 
            "Keep dependencies updated",
            "Regular governance reviews"
        ],
        "technical_findings": {
            "access_control": "Proper - Multi-sig governance",
            "reentrancy_protection": "Implemented",
            "overflow_protection": "SafeMath used",
            "price_oracle_security": "Robust TWAP implementation"
        }
    }'::json,
    2,
    '{
        "analysis_version": "2.1.0",
        "total_value_locked": 2500000000,
        "daily_volume": 1200000000,
        "risk_category": "low",
        "confidence_score": 94.5
    }'::json,
    '{
        "analysis_type": "comprehensive_security_audit",
        "include_gas_analysis": true,
        "deep_analysis": true,
        "check_latest_exploits": true
    }'::json,
    'security_team',
    45000,
    8500,
    NOW() - INTERVAL '3 days',
    NOW() - INTERVAL '3 days',
    NOW() - INTERVAL '3 days',
    NOW() - INTERVAL '3 days'

UNION ALL

-- Aave V3 Pool Analysis  
SELECT 
    gen_random_uuid(),
    '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
    'ethereum',
    'completed',
    'gpt-4-turbo',
    100,
    'analysis_complete',
    '{
        "contract_name": "Aave V3 Pool",
        "contract_type": "defi",
        "deployment_date": "2022-03-16", 
        "source_lines": 3521,
        "compiler_version": "0.8.10"
    }'::json,
    '{
        "security_score": 80,
        "risk_assessment": "LOW-MEDIUM RISK - Advanced lending protocol",
        "vulnerabilities": [
            "Flash loan attack vectors (mitigated with proper checks)",
            "Oracle manipulation risks (protected by price feeds)"
        ],
        "gas_efficiency": "High - Optimized for capital efficiency",
        "code_quality": "Excellent - Modular architecture",
        "audit_status": "Extensively audited by top firms",
        "recommendations": [
            "Monitor oracle price feeds closely",
            "Regular flash loan security reviews",
            "Isolation mode parameter updates",
            "Risk parameter optimization"
        ],
        "technical_findings": {
            "access_control": "Robust role-based system",
            "liquidation_logic": "Well implemented with safety checks",
            "interest_rate_models": "Advanced and battle-tested",
            "isolation_mode": "Innovative risk management feature"
        }
    }'::json,
    3,
    '{
        "analysis_version": "2.1.0",
        "total_value_locked": 6800000000,
        "supported_assets": 25,
        "risk_category": "low_medium",
        "confidence_score": 91.2
    }'::json,
    '{
        "analysis_type": "comprehensive_security_audit",
        "focus_areas": ["flash_loans", "liquidations", "oracle_security"]
    }'::json,
    'security_team',
    67000,
    12300,
    NOW() - INTERVAL '2 days',
    NOW() - INTERVAL '2 days', 
    NOW() - INTERVAL '2 days',
    NOW() - INTERVAL '2 days'

UNION ALL

-- Compound V2 cDAI Analysis
SELECT 
    gen_random_uuid(),
    '0x5d3a536E4D6DbD6114cc1Ead35777bAB948E3643',
    'ethereum',
    'completed',
    'gpt-4-turbo',
    100,
    'analysis_complete',
    '{
        "contract_name": "Compound V2 cDAI",
        "contract_type": "defi",
        "deployment_date": "2019-05-07",
        "source_lines": 1892,
        "compiler_version": "0.5.16"
    }'::json,
    '{
        "security_score": 75,
        "risk_assessment": "MEDIUM RISK - Legacy DeFi with known vectors",
        "vulnerabilities": [
            "Oracle price manipulation potential",
            "Governance attack vectors via COMP token",
            "Interest rate model manipulation risks",
            "Legacy codebase with older security patterns"
        ],
        "gas_efficiency": "Medium - Legacy gas patterns",
        "code_quality": "Good - Pioneer in lending space",
        "audit_status": "Audited but older security standards",
        "recommendations": [
            "Enhanced oracle security monitoring",
            "Governance proposal security reviews",
            "Consider migration to V3 architecture",
            "Regular risk parameter updates",
            "Enhanced liquidation monitoring"
        ],
        "technical_findings": {
            "access_control": "Basic - Governance-based",
            "oracle_dependency": "Single point of failure risk",
            "interest_rate_models": "Functional but dated",
            "liquidation_mechanism": "Works but can be optimized"
        }
    }'::json,
    6,
    '{
        "analysis_version": "2.1.0",
        "total_value_locked": 1200000000,
        "legacy_status": true,
        "risk_category": "medium", 
        "confidence_score": 87.8
    }'::json,
    '{
        "analysis_type": "comprehensive_security_audit",
        "focus_areas": ["oracle_security", "governance", "legacy_risks"]
    }'::json,
    'security_team',
    38000,
    7200,
    NOW() - INTERVAL '1 day',
    NOW() - INTERVAL '1 day',
    NOW() - INTERVAL '1 day',
    NOW() - INTERVAL '1 day'

UNION ALL

-- Euler Finance Analysis (EXPLOITED)
SELECT 
    gen_random_uuid(),
    '0x27182842E098f60e3D576794A5bFFb0777E025d3',
    'ethereum',
    'completed',
    'gpt-4-turbo',
    100,
    'analysis_complete',
    '{
        "contract_name": "Euler Finance",
        "contract_type": "defi",
        "deployment_date": "2021-12-15",
        "source_lines": 4200,
        "compiler_version": "0.8.13"
    }'::json,
    '{
        "security_score": 5,
        "risk_assessment": "CRITICAL RISK - EXPLOITED CONTRACT",
        "exploit_confirmed": true,
        "exploit_date": "2023-03-13",
        "amount_stolen": 197000000,
        "vulnerabilities": [
            "CRITICAL: Donation attack vulnerability in eToken contract",
            "Missing donation protection in violateCollateralInvariant function", 
            "Price manipulation via eToken supply inflation",
            "Lack of proper checks in liquidation logic",
            "Flash loan integration without adequate safeguards"
        ],
        "exploit_details": {
            "attack_type": "Donation Attack",
            "root_cause": "Missing donation protection checks",
            "attack_vector": "Artificial balance inflation via donation",
            "attacker_profit": "$197M USD",
            "recovery_status": "Partial recovery via negotiations"
        },
        "code_quality": "Poor - Critical security flaw",
        "audit_status": "Audited but critical vulnerability missed",
        "recommendations": [
            "CONTRACT PERMANENTLY COMPROMISED - DO NOT USE",
            "Case study for donation attack prevention",
            "Reference for proper balance validation",
            "Example of audit limitations"
        ],
        "technical_findings": {
            "donation_protection": "FAILED - Root cause of exploit",
            "balance_validation": "INSUFFICIENT",
            "liquidation_logic": "VULNERABLE to manipulation",
            "access_control": "Bypassed via donation attack"
        }
    }'::json,
    12,
    '{
        "analysis_version": "2.1.0", 
        "exploit_confirmed": true,
        "post_exploit_analysis": true,
        "risk_category": "critical",
        "confidence_score": 99.9,
        "educational_value": "high"
    }'::json,
    '{
        "analysis_type": "post_exploit_forensic_analysis",
        "focus_areas": ["donation_attacks", "balance_manipulation", "exploit_vectors"]
    }'::json,
    'exploit_research_team',
    89000,
    15600,
    NOW() - INTERVAL '4 hours',
    NOW() - INTERVAL '4 hours',
    NOW() - INTERVAL '4 hours', 
    NOW() - INTERVAL '4 hours'

UNION ALL

-- BNB Chain Bridge Analysis (EXPLOITED)
SELECT 
    gen_random_uuid(),
    '0x8894E0a0c962CB723c1976a4421c95949bE2D4E3',
    'bsc',
    'completed',
    'gpt-4-turbo',
    100,
    'analysis_complete',
    '{
        "contract_name": "BNB Chain Bridge",
        "contract_type": "bridge",
        "deployment_date": "2020-09-01",
        "source_lines": 3800,
        "compiler_version": "0.6.12"
    }'::json,
    '{
        "security_score": 2,
        "risk_assessment": "CRITICAL RISK - EXPLOITED BRIDGE",
        "exploit_confirmed": true,
        "exploit_date": "2022-10-07", 
        "amount_stolen": 586000000,
        "vulnerabilities": [
            "CRITICAL: IAVL tree proof forgery vulnerability",
            "Merkle tree manipulation allowing fake proofs",
            "Cross-chain message verification bypass",
            "Proof validation logic failure",
            "Insufficient cryptographic verification"
        ],
        "exploit_details": {
            "attack_type": "Proof Forgery Attack",
            "root_cause": "IAVL tree proof verification vulnerability",
            "attack_vector": "Forged withdraw proofs accepted as valid",
            "attacker_profit": "$586M USD (largest bridge hack)",
            "recovery_status": "Bridge halted, partial funds frozen"
        },
        "code_quality": "Poor - Critical cryptographic flaw",
        "audit_status": "Audited but critical flaw missed",
        "recommendations": [
            "BRIDGE PERMANENTLY COMPROMISED - AVOID USAGE",
            "Case study for cross-chain security",
            "Reference for proper proof verification",
            "Example of bridge attack vectors"
        ],
        "technical_findings": {
            "proof_verification": "FAILED - Core vulnerability",
            "merkle_validation": "INSUFFICIENT",
            "cross_chain_security": "BROKEN",
            "cryptographic_checks": "BYPASSED"
        }
    }'::json,
    15,
    '{
        "analysis_version": "2.1.0",
        "exploit_confirmed": true,
        "bridge_type": "cross_chain",
        "risk_category": "critical",
        "confidence_score": 99.9,
        "historical_significance": "largest_bridge_exploit"
    }'::json,
    '{
        "analysis_type": "post_exploit_forensic_analysis", 
        "focus_areas": ["proof_forgery", "bridge_security", "cross_chain_vulnerabilities"]
    }'::json,
    'bridge_security_team',
    112000,
    18900,
    NOW() - INTERVAL '6 hours',
    NOW() - INTERVAL '6 hours',
    NOW() - INTERVAL '6 hours',
    NOW() - INTERVAL '6 hours';

-- Show summary of analyses created
SELECT 
    'SUCCESS: Analysis records created!' as status,
    COUNT(*) as analyses_created
FROM contract_analyses ca
JOIN famous_contracts fc ON ca.contract_address = fc.address;

-- Show analysis summary by contract
SELECT 
    fc.name,
    ca.status,
    ca.findings_count,
    (ca.findings->>'security_score')::int as security_score,
    ca.findings->>'risk_assessment' as risk_level,
    CASE 
        WHEN ca.findings->>'exploit_confirmed' = 'true' THEN '🚨 EXPLOITED'
        WHEN (ca.findings->>'security_score')::int > 80 THEN '✅ SECURE'
        WHEN (ca.findings->>'security_score')::int > 60 THEN '⚠️ MEDIUM RISK'
        ELSE '🔴 HIGH RISK'
    END as status_icon
FROM contract_analyses ca
JOIN famous_contracts fc ON ca.contract_address = fc.address
ORDER BY (ca.findings->>'security_score')::int ASC;
