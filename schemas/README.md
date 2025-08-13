# Vulnerability Finding JSON Schema

This directory contains comprehensive JSON schemas for smart contract security vulnerability findings, designed in OWASP style for standardized security reporting.

## 📋 Overview

The schema provides a standardized format for documenting smart contract vulnerabilities, enabling:

- **Consistent Reporting**: Unified format across different security tools and auditors
- **Automated Processing**: Machine-readable format for CI/CD integration
- **Risk Assessment**: Structured impact and exploitability analysis
- **Compliance**: OWASP-aligned categorization and CWE mapping
- **Traceability**: Complete audit trail and evidence tracking

## 🏗️ Schema Structure

### Core Schema
- **`vulnerability-finding-schema.json`**: Main schema with comprehensive vulnerability structure
- **`examples/`**: Real-world example findings for each severity level
- **`specialized/`**: Extended schemas for specific domains (DeFi, NFT)

### Key Components

```json
{
  "id": "VULN_001",
  "severity": "HIGH",
  "title": "Reentrancy in withdrawal function",
  "category": "REENTRANCY",
  "description": "Detailed vulnerability description...",
  "recommendation": {
    "summary": "Implement checks-effects-interactions pattern",
    "details": "Step-by-step remediation guide...",
    "code_example": "// Secure implementation..."
  },
  "location": {
    "file": "Contract.sol",
    "line": 125,
    "function": "withdraw"
  },
  "confidence": {
    "level": "HIGH", 
    "score": 0.95
  }
}
```

## 🎯 Severity Levels

Following OWASP risk rating methodology:

| Severity | Description | Action Required |
|----------|-------------|-----------------|
| **CRITICAL** | Immediate threat to funds/users | Fix immediately |
| **HIGH** | Significant security risk | Fix before next release |
| **MEDIUM** | Moderate risk, needs attention | Fix in planned maintenance |
| **LOW** | Minor issue or best practice | Fix when convenient |
| **INFO** | Informational finding | Consider for improvement |

## 📊 Vulnerability Categories

Comprehensive taxonomy covering smart contract security:

### Core Categories
- `REENTRANCY` - External call vulnerabilities
- `ACCESS_CONTROL` - Permission and authorization issues
- `INTEGER_OVERFLOW/UNDERFLOW` - Arithmetic vulnerabilities
- `DELEGATECALL` - Dangerous delegatecall usage
- `TIMESTAMP_DEPENDENCE` - Block timestamp manipulation
- `WEAK_RANDOMNESS` - Predictable random number generation

### DeFi-Specific
- `FLASH_LOAN_ATTACK` - Flash loan exploitation vectors
- `PRICE_MANIPULATION` - Oracle and pricing vulnerabilities
- `GOVERNANCE_ATTACK` - DAO and voting manipulation
- `MEV_EXTRACTION` - Maximum extractable value risks

### NFT-Specific
- `METADATA_MANIPULATION` - Centralized metadata risks
- `ROYALTY_BYPASS` - Royalty enforcement issues
- `FAKE_COLLECTION` - Collection authenticity problems

## 🔧 Usage Examples

### Basic Usage

```php
// Validate finding against schema
$finding = json_decode($findingJson, true);
$validator = new JsonSchemaValidator();
$isValid = $validator->validate($finding, 'vulnerability-finding-schema.json');
```

### DeFi Protocol Analysis

```php
// Extended DeFi findings
$defiFinding = [
    'id' => 'DEFI_001',
    'severity' => 'CRITICAL',
    'category' => 'FLASH_LOAN_ATTACK',
    'defi_context' => [
        'protocol_type' => 'DEX',
        'tvl_at_risk' => [
            'amount_usd' => 50000000,
            'percentage' => 15.5
        ],
        'flash_loan_exploitable' => true
    ]
    // ... rest of finding
];
```

### Integration with Analysis Pipeline

```php
class VulnerabilityReporter 
{
    public function generateReport(array $findings): string
    {
        $report = [];
        foreach ($findings as $finding) {
            // Validate against schema
            if ($this->validateFinding($finding)) {
                $report[] = $this->formatFinding($finding);
            }
        }
        return json_encode($report, JSON_PRETTY_PRINT);
    }
    
    private function validateFinding(array $finding): bool
    {
        return $this->validator->validate(
            $finding, 
            'vulnerability-finding-schema.json'
        );
    }
}
```

## 📋 Required Fields

All findings must include:

- **`id`**: Unique identifier
- **`severity`**: Risk level (CRITICAL|HIGH|MEDIUM|LOW|INFO)
- **`title`**: Concise vulnerability description
- **`category`**: Primary vulnerability type
- **`description`**: Detailed explanation
- **`recommendation`**: Remediation guidance
- **`location`**: Source code location
- **`confidence`**: Detection confidence level
- **`timestamp`**: Finding timestamp

## 🎨 Example Findings

### Critical Reentrancy
```json
{
  "id": "REENT_001",
  "severity": "CRITICAL", 
  "title": "Cross-function Reentrancy in Token Transfer",
  "category": "REENTRANCY",
  "location": {
    "file": "Token.sol",
    "line": 87,
    "function": "transfer"
  },
  "impact": {
    "financial": {
      "risk_level": "CRITICAL",
      "potential_loss": "Unlimited - entire contract balance"
    }
  }
}
```

### High Access Control Issue
```json
{
  "id": "ACCESS_042",
  "severity": "HIGH",
  "title": "Missing Access Control on Critical Function", 
  "category": "ACCESS_CONTROL",
  "exploitability": {
    "ease": "TRIVIAL",
    "prerequisites": ["None - any address can call the function"]
  }
}
```

## 📚 References

- [OWASP Smart Contract Top 10](https://owasp.org/www-project-smart-contract-top-10/)
- [Common Weakness Enumeration (CWE)](https://cwe.mitre.org/)
- [JSON Schema Specification](https://json-schema.org/)
- [ConsenSys Security Best Practices](https://consensys.github.io/smart-contract-best-practices/)

## 📧 Support

For questions about the schema or integration support:
- Create an issue in the repository
- Consult the examples in `schemas/examples/`
- Review specialized schemas in `schemas/specialized/`