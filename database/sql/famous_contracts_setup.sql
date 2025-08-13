-- =============================================================================
-- Famous Smart Contracts Database Setup
-- =============================================================================

-- Create famous_contracts table
CREATE TABLE IF NOT EXISTS famous_contracts (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(42) UNIQUE NOT NULL,
    network VARCHAR(50) NOT NULL,
    contract_type VARCHAR(50) NOT NULL,
    description TEXT,
    deployment_date DATE,
    total_value_locked BIGINT DEFAULT 0,
    transaction_count BIGINT DEFAULT 0,
    creator_address VARCHAR(42),
    is_verified BOOLEAN DEFAULT false,
    risk_score INTEGER DEFAULT 50,
    security_features JSONB,
    vulnerabilities JSONB,
    audit_firms JSONB,
    gas_optimization VARCHAR(20) DEFAULT 'Medium',
    code_quality VARCHAR(20) DEFAULT 'Unknown',
    exploit_details JSONB,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_famous_contracts_name ON famous_contracts(name);
CREATE INDEX IF NOT EXISTS idx_famous_contracts_address ON famous_contracts(address);
CREATE INDEX IF NOT EXISTS idx_famous_contracts_network ON famous_contracts(network);
CREATE INDEX IF NOT EXISTS idx_famous_contracts_type ON famous_contracts(contract_type);
CREATE INDEX IF NOT EXISTS idx_famous_contracts_verified ON famous_contracts(is_verified);
CREATE INDEX IF NOT EXISTS idx_famous_contracts_risk ON famous_contracts(risk_score);
CREATE INDEX IF NOT EXISTS idx_famous_contracts_network_type ON famous_contracts(network, contract_type);

-- Create contract_analyses table
CREATE TABLE IF NOT EXISTS contract_analyses (
    id BIGSERIAL PRIMARY KEY,
    contract_id BIGINT REFERENCES famous_contracts(id) ON DELETE CASCADE,
    analysis_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    risk_score INTEGER DEFAULT 50,
    findings JSONB,
    recommendations JSONB,
    analysis_date TIMESTAMP,
    analyzer_version VARCHAR(20) DEFAULT '1.0.0',
    execution_time_ms INTEGER,
    confidence_score DECIMAL(5,2) DEFAULT 0.00,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Create indexes for contract_analyses
CREATE INDEX IF NOT EXISTS idx_contract_analyses_contract_id ON contract_analyses(contract_id);
CREATE INDEX IF NOT EXISTS idx_contract_analyses_type ON contract_analyses(analysis_type);
CREATE INDEX IF NOT EXISTS idx_contract_analyses_status ON contract_analyses(status);
CREATE INDEX IF NOT EXISTS idx_contract_analyses_date ON contract_analyses(analysis_date);
CREATE INDEX IF NOT EXISTS idx_contract_analyses_contract_type ON contract_analyses(contract_id, analysis_type);

-- Insert famous contracts data
INSERT INTO famous_contracts (
    name, address, network, contract_type, description, deployment_date,
    total_value_locked, transaction_count, creator_address, is_verified,
    risk_score, security_features, vulnerabilities, audit_firms,
    gas_optimization, code_quality, exploit_details, metadata
) VALUES 
-- Uniswap V3 Router
(
    'Uniswap V3 Router',
    '0xE592427A0AEce92De3Edee1F18E0157C05861564',
    'ethereum',
    'defi',
    'Uniswap V3 SwapRouter - The main router contract for Uniswap V3 DEX operations',
    '2021-05-05',
    2500000000000000000000000000, -- $2.5B in wei
    15000000,
    '0x1a9C8182C09F50C8318d769245beA52c32BE35BC',
    true,
    15,
    '["Multi-sig governance", "Time-locked upgrades", "Comprehensive audits", "Open source", "Battle tested"]'::jsonb,
    '[]'::jsonb,
    '["Trail of Bits", "ABDK Consulting"]'::jsonb,
    'High',
    'Excellent',
    null,
    '{"seeded_at": "2025-01-08T20:00:00Z", "seed_version": "1.0.0", "source": "FamousSmartContractsSeeder"}'::jsonb
),
-- Aave V3 Pool
(
    'Aave V3 Pool',
    '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2',
    'ethereum',
    'defi',
    'Aave V3 Lending Pool - Core lending and borrowing protocol for Aave V3',
    '2022-03-16',
    6800000000000000000000000000, -- $6.8B in wei
    8500000,
    '0xd784927Ff2f95ba542BfC824c8a8a98F3495f6b5',
    true,
    20,
    '["Risk parameters governance", "Liquidation protections", "Rate model optimizations", "Multi-collateral support", "Flash loan protections"]'::jsonb,
    '["Flash loan attack vectors (mitigated)", "Oracle manipulation risks (protected)"]'::jsonb,
    '["OpenZeppelin", "SigmaPrime", "Peckshield"]'::jsonb,
    'High',
    'Excellent',
    null,
    '{"seeded_at": "2025-01-08T20:00:00Z", "seed_version": "1.0.0", "source": "FamousSmartContractsSeeder"}'::jsonb
),
-- Compound V2 cToken
(
    'Compound V2 cToken',
    '0x5d3a536E4D6DbD6114cc1Ead35777bAB948E3643',
    'ethereum',
    'defi',
    'Compound cDAI - Interest-bearing DAI token in Compound V2 protocol',
    '2019-05-07',
    1200000000000000000000000000, -- $1.2B in wei
    12000000,
    '0x3d9819210A31b4961b30EF54bE2aeD79B9c9Cd3B',
    true,
    25,
    '["Interest rate models", "Collateral factors", "Liquidation mechanisms", "Price feed oracles", "Governance controls"]'::jsonb,
    '["Oracle price manipulation", "Governance attack vectors", "Interest rate model exploits"]'::jsonb,
    '["OpenZeppelin", "Trail of Bits"]'::jsonb,
    'Medium',
    'Good',
    null,
    '{"seeded_at": "2025-01-08T20:00:00Z", "seed_version": "1.0.0", "source": "FamousSmartContractsSeeder"}'::jsonb
),
-- Euler Finance Main (EXPLOITED)
(
    'Euler Finance Main',
    '0x27182842E098f60e3D576794A5bFFb0777E025d3',
    'ethereum',
    'defi',
    'Euler Finance Protocol - EXPLOITED March 2023 ($197M stolen)',
    '2021-12-15',
    0, -- Drained
    450000,
    '0x3520d5a913427E6F0D6A83E07ccD4A4da316e4d3',
    true,
    95,
    '["Risk-adjusted borrowing", "Reactive interest rates", "MEV-resistant liquidations"]'::jsonb,
    '["CRITICAL: Donation attack on eToken implementation (CVE-2023-EULER)", "Price manipulation via donation", "Inflate supply attack vector", "Lack of proper donation checks"]'::jsonb,
    '["Sherlock", "Zellic"]'::jsonb,
    'High',
    'Poor (Critical Vulnerability)',
    '{
        "date": "2023-03-13",
        "amount_stolen": "197000000",
        "attack_type": "Donation Attack",
        "root_cause": "Missing donation protection in eToken contract",
        "transactions": [
            "0xc310e760778ecbca4c65b6c559874757a4c4ece0",
            "0x71a908be0bef6174bccc3d493becddf769a36832"
        ]
    }'::jsonb,
    '{"seeded_at": "2025-01-08T20:00:00Z", "seed_version": "1.0.0", "source": "FamousSmartContractsSeeder"}'::jsonb
),
-- BNB Chain Bridge (EXPLOITED)
(
    'BNB Chain Bridge (BSC)',
    '0x8894E0a0c962CB723c1976a4421c95949bE2D4E3',
    'bsc',
    'bridge',
    'BNB Chain Bridge - EXPLOITED October 2022 ($586M stolen)',
    '2020-09-01',
    0, -- Paused after exploit
    2800000,
    '0x0000000000000000000000000000000000001004',
    true,
    98,
    '["Multi-signature validation", "Merkle proof verification", "Cross-chain messaging"]'::jsonb,
    '["CRITICAL: IAVL tree proof forgery (CVE-2022-BSC)", "Merkle tree manipulation", "Proof verification bypass", "Cross-chain message forgery"]'::jsonb,
    '["PeckShield", "SlowMist"]'::jsonb,
    'Medium',
    'Poor (Critical Vulnerability)',
    '{
        "date": "2022-10-07",
        "amount_stolen": "586000000",
        "attack_type": "Proof Forgery",
        "root_cause": "IAVL tree proof verification vulnerability",
        "transactions": [
            "0x05356fd06ce56a9ec5b4eaa3cd50b8c40726e30d",
            "0xebf78c64c8b8e84c6142bc413943042400623932"
        ]
    }'::jsonb,
    '{"seeded_at": "2025-01-08T20:00:00Z", "seed_version": "1.0.0", "source": "FamousSmartContractsSeeder"}'::jsonb
)
ON CONFLICT (address) DO NOTHING;

-- Insert sample analyses for each contract
INSERT INTO contract_analyses (
    contract_id, analysis_type, status, risk_score, findings, recommendations,
    analysis_date, analyzer_version, confidence_score, metadata
)
SELECT 
    fc.id,
    analysis_type,
    'completed',
    fc.risk_score,
    CASE analysis_type
        WHEN 'security_audit' THEN json_build_object(
            'contract_verified', fc.is_verified,
            'security_score', 100 - fc.risk_score,
            'vulnerabilities_found', COALESCE(jsonb_array_length(fc.vulnerabilities), 0),
            'audit_status', CASE WHEN fc.audit_firms IS NOT NULL THEN 'audited' ELSE 'unaudited' END,
            'critical_issues', CASE WHEN fc.risk_score > 80 THEN COALESCE(jsonb_array_length(fc.vulnerabilities), 0) ELSE 0 END
        )
        WHEN 'gas_optimization' THEN json_build_object(
            'gas_efficiency', fc.gas_optimization,
            'optimization_score', CASE fc.gas_optimization 
                WHEN 'High' THEN 85 
                WHEN 'Medium' THEN 60 
                WHEN 'Low' THEN 35 
                ELSE 50 END,
            'estimated_savings', (random() * 30 + 10)::int::text || '%'
        )
        WHEN 'vulnerability_scan' THEN json_build_object(
            'vulnerabilities', fc.vulnerabilities,
            'severity_distribution', json_build_object(
                'critical', CASE WHEN fc.risk_score > 80 THEN (random() * 2 + 1)::int ELSE 0 END,
                'high', CASE WHEN fc.risk_score > 60 THEN (random() * 2)::int ELSE 0 END,
                'medium', (random() * 3)::int,
                'low', (random() * 4 + 1)::int
            )
        )
        WHEN 'code_quality' THEN json_build_object(
            'quality_score', CASE fc.code_quality
                WHEN 'Excellent' THEN (random() * 10 + 90)::int
                WHEN 'Good' THEN (random() * 19 + 70)::int
                WHEN 'Fair' THEN (random() * 19 + 50)::int
                ELSE (random() * 29 + 20)::int
            END,
            'complexity_analysis', json_build_object(
                'cyclomatic_complexity', (random() * 40 + 10)::int,
                'code_coverage', (random() * 35 + 60)::int::text || '%',
                'documentation_score', (random() * 50 + 40)::int
            )
        )
    END::jsonb,
    CASE analysis_type
        WHEN 'security_audit' THEN 
            CASE WHEN fc.risk_score > 80 THEN 
                '["URGENT: Address critical vulnerabilities before deployment", "Implement comprehensive security measures", "Consider additional security audits"]'
            ELSE 
                '["Implement multi-signature requirements", "Add time-locked upgrade mechanisms", "Enhance input validation"]'
            END
        WHEN 'gas_optimization' THEN '["Optimize storage variable packing", "Reduce external contract calls", "Implement efficient loops"]'
        WHEN 'vulnerability_scan' THEN 
            CASE WHEN jsonb_array_length(COALESCE(fc.vulnerabilities, '[]'::jsonb)) > 0 THEN
                '["Address identified vulnerabilities immediately", "Implement additional access controls", "Add emergency pause mechanisms"]'
            ELSE
                '["Continue regular security monitoring", "Maintain security best practices"]'
            END
        WHEN 'code_quality' THEN '["Improve code documentation", "Add comprehensive unit tests", "Implement code review processes"]'
    END::jsonb,
    NOW() - INTERVAL '1 day' * (random() * 30)::int,
    '2.0.0',
    (random() * 30 + 70)::decimal(5,2),
    json_build_object(
        'seed_generated', true,
        'contract_name', fc.name,
        'network', fc.network
    )::jsonb
FROM famous_contracts fc
CROSS JOIN (VALUES ('security_audit'), ('gas_optimization'), ('vulnerability_scan'), ('code_quality')) AS t(analysis_type);

-- Create a view for easy querying
CREATE OR REPLACE VIEW contract_summary AS
SELECT 
    fc.*,
    CASE 
        WHEN fc.risk_score >= 80 THEN 'Critical'
        WHEN fc.risk_score >= 60 THEN 'High'
        WHEN fc.risk_score >= 40 THEN 'Medium'
        WHEN fc.risk_score >= 20 THEN 'Low'
        ELSE 'Very Low'
    END as risk_level,
    (fc.total_value_locked::decimal / 1e18) as tvl_formatted,
    CASE WHEN fc.exploit_details IS NOT NULL THEN true ELSE false END as is_exploited,
    COUNT(ca.id) as analysis_count,
    MAX(ca.analysis_date) as last_analysis_date
FROM famous_contracts fc
LEFT JOIN contract_analyses ca ON fc.id = ca.contract_id
GROUP BY fc.id;
