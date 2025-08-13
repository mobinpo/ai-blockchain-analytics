# Security Finding Schema v4.0 - AI Prompt Engineering Guide

## Overview

The Security Finding Schema v4.0 is a comprehensive, OWASP-style JSON schema designed for AI-powered smart contract security analysis. This guide provides detailed instructions for AI models and prompt engineers on how to create high-quality security findings.

## Schema Structure

### Core Philosophy
- **Precision**: Exact location information with line numbers and code snippets
- **Actionability**: Clear, step-by-step remediation guidance
- **Comprehensiveness**: Complete risk assessment and business impact analysis
- **AI-Optimized**: Structured for consistent AI analysis and prompt engineering

### Required Fields

```json
{
  "id": "FIND-12345678-ABCD-EFGH-IJKL-123456789ABC",
  "severity": "HIGH",
  "title": "Reentrancy in withdraw function enables fund drainage",
  "category": "SWC-107-Reentrancy", 
  "description": "Detailed technical explanation...",
  "confidence": "HIGH",
  "location": { "line": 125, "function": "withdraw", "contract": "Contract" },
  "recommendation": { "summary": "Fix approach", "detailed_steps": [...] },
  "ai_metadata": { "model": "gpt-4", "analysis_version": "4.1.0" }
}
```

## AI Prompt Engineering Guidelines

### 1. Severity Classification

Use these guidelines for consistent severity assignment:

- **CRITICAL**: Immediate threat to funds, complete system compromise
- **HIGH**: Significant security risk with potential for substantial losses
- **MEDIUM**: Moderate security issue requiring attention
- **LOW**: Minor security concern or best practice violation  
- **INFO**: Informational finding for awareness
- **GAS_OPTIMIZATION**: Efficiency improvement without security impact

### 2. Title Formatting

Follow this pattern for maximum clarity:
```
[Vulnerability Type] in [Function/Component] enables/allows [Impact/Consequence]
```

Examples:
- ✅ "Reentrancy in withdraw function enables fund drainage through recursive calls"
- ✅ "Integer overflow in token calculation allows unlimited minting bypassing max supply"
- ❌ "Security issue found" (too vague)
- ❌ "Reentrancy vulnerability." (ends with period, not descriptive enough)

### 3. Description Structure

Organize descriptions with this 4-part structure:

1. **What**: Clearly state the vulnerability type and mechanism
2. **Where**: Identify exact location (function, line, contract)
3. **How**: Explain the exploitation mechanism step-by-step
4. **Impact**: Describe potential consequences and affected parties

Example:
```
The withdraw function performs an external call to transfer Ether before updating the user's balance, creating a classic reentrancy vulnerability. An attacker can deploy a malicious contract with a fallback function that recursively calls withdraw() during the external call execution, bypassing the balance check and potentially draining all contract funds. This vulnerability affects all Ethereum-compatible networks and poses a critical risk to user funds.
```

### 4. Location Information

Always provide comprehensive location data:

```json
"location": {
  "line": 125,
  "line_end": 129,
  "function": "withdraw", 
  "contract": "VulnerableBank",
  "file": "contracts/VulnerableBank.sol",
  "code_snippet": "function withdraw(uint amount) public {\n    require(balances[msg.sender] >= amount);\n    msg.sender.call{value: amount}(\"\");\n    balances[msg.sender] -= amount;\n}",
  "affected_variables": ["balances", "amount"],
  "control_flow": {
    "entry_points": ["withdraw"],
    "execution_path": [...],
    "conditions": ["User has sufficient balance", "External call succeeds"]
  }
}
```

### 5. Recommendation Quality

Structure recommendations with these components:

#### Immediate Action
Choose appropriate response:
- `PAUSE_CONTRACT`: Critical vulnerabilities requiring immediate shutdown
- `PATCH_IMMEDIATELY`: High-severity issues needing urgent fixes
- `DISABLE_FUNCTION`: Specific function poses immediate risk
- `MONITOR_CLOSELY`: Increased monitoring until fix deployed
- `NO_ACTION`: Gas optimizations or info findings

#### Detailed Steps
Provide 3-7 concrete, verifiable steps:

```json
"detailed_steps": [
  {
    "step": 1,
    "action": "Install OpenZeppelin's ReentrancyGuard contract",
    "code_example": "npm install @openzeppelin/contracts",
    "verification": "Check package.json for dependency"
  },
  {
    "step": 2, 
    "action": "Add nonReentrant modifier to vulnerable functions",
    "code_example": "function withdraw(uint amount) public nonReentrant {",
    "verification": "Function signature includes modifier"
  }
]
```

### 6. Risk Assessment

Provide comprehensive risk quantification:

```json
"risk_metrics": {
  "cvss_v3": {
    "score": 8.1,
    "vector": "CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:N/I:H/A:H"
  },
  "exploitability": {
    "ease": "EASY",
    "prerequisites": ["Deploy malicious contract", "Sufficient balance"],
    "attack_complexity": "LOW",
    "cost_to_exploit": "LOW"
  },
  "business_impact": {
    "financial": {
      "direct_loss": "HIGH",
      "loss_estimate": "All funds in contract could be drained",
      "affected_funds": "USER_FUNDS"
    },
    "operational": "SEVERE",
    "reputation": "CRITICAL"
  }
}
```

## Category Guidelines

### OWASP Top 10 2021
Use for general web application security issues:
- `A01:2021-Broken Access Control`
- `A02:2021-Cryptographic Failures`
- `A03:2021-Injection`
- etc.

### SWC Registry
Use for known smart contract weaknesses:
- `SWC-107-Reentrancy`
- `SWC-101-Integer Overflow and Underflow`
- `SWC-115-Authorization through tx.origin`
- etc.

### Blockchain-Specific Categories
Use for modern blockchain vulnerabilities:
- `DEFI-001-Oracle Manipulation`
- `DEFI-002-Flash Loan Attack`
- `NFT-001-Metadata Manipulation`
- `GAS-001-Inefficient Storage Access`
- `PROXY-001-Uninitialized Implementation`

## AI Metadata Requirements

Always include comprehensive AI analysis metadata:

```json
"ai_metadata": {
  "model": "gpt-4",
  "analysis_version": "4.1.0", 
  "detection_method": "LLM_ANALYSIS",
  "prompt_engineering": {
    "prompt_version": "v4.0",
    "prompt_type": "COMPREHENSIVE",
    "context_window": 8192,
    "temperature": 0.1
  },
  "confidence_scoring": {
    "base_confidence": 0.95,
    "false_positive_probability": 0.02,
    "validation_score": 0.98,
    "contextual_factors": [
      {
        "factor": "Classic reentrancy pattern",
        "impact": 0.4,
        "weight": 0.4
      }
    ]
  }
}
```

## Blockchain Context

Provide comprehensive blockchain-specific information:

```json
"blockchain_context": {
  "networks": ["ETHEREUM", "POLYGON", "BSC"],
  "evm_specifics": {
    "solidity_version": "0.8.19",
    "optimization_enabled": true,
    "optimization_runs": 200
  },
  "gas_analysis": {
    "vulnerability_gas_cost": 21000,
    "fix_gas_impact": 2400,
    "optimization_potential": 0
  },
  "defi_context": {
    "protocol_type": ["AMM", "LENDING"],
    "tvl_impact": "COMPLETE",
    "liquidity_risk": true,
    "oracle_dependency": false
  }
}
```

## Validation and Quality Assurance

### Quality Score Components
The schema includes automated quality scoring based on:
- Title completeness and clarity (10%)
- Description depth and technical accuracy (20%) 
- Location information comprehensiveness (15%)
- Recommendation quality and actionability (25%)
- Risk assessment completeness (15%)
- AI metadata completeness (10%)
- Blockchain context relevance (5%)

### Common Validation Errors

1. **Missing Required Fields**: Ensure all required fields are present
2. **Invalid Enums**: Use exact enum values (case-sensitive)
3. **ID Format**: Must start with "FIND-" followed by UUID format
4. **Line Numbers**: Must be positive integers
5. **String Lengths**: Respect minimum/maximum length requirements

## Examples by Category

### Reentrancy Vulnerability
```json
{
  "severity": "HIGH",
  "title": "Reentrancy in withdraw function enables fund drainage through recursive calls",
  "category": "SWC-107-Reentrancy",
  "description": "External call before state change creates reentrancy vulnerability...",
  "recommendation": {
    "immediate_action": "PATCH_IMMEDIATELY",
    "secure_pattern": {
      "name": "Checks-Effects-Interactions with ReentrancyGuard",
      "implementation": "// Safe implementation code"
    }
  }
}
```

### Gas Optimization
```json
{
  "severity": "GAS_OPTIMIZATION", 
  "title": "Inefficient storage access pattern in loop wastes gas through repeated SSTORE operations",
  "category": "GAS-001-Inefficient Storage Access",
  "recommendation": {
    "immediate_action": "NO_ACTION",
    "secure_pattern": {
      "name": "Optimized Batch Processing",
      "implementation": "// Gas-efficient implementation"
    }
  }
}
```

### DeFi Oracle Manipulation
```json
{
  "severity": "CRITICAL",
  "title": "Single oracle price feed manipulation enables arbitrage attacks draining AMM liquidity pools",
  "category": "DEFI-001-Oracle Manipulation", 
  "recommendation": {
    "immediate_action": "PAUSE_CONTRACT",
    "secure_pattern": {
      "name": "Multi-Oracle Price Validation with Circuit Breakers",
      "implementation": "// Multi-oracle secure implementation"
    }
  }
}
```

## Integration with Analysis Services

### OWASPSecurityAnalyzer Integration

```php
// Update the analyzer to use v4 schema
$analyzer = new OWASPSecurityAnalyzer();
$findings = $analyzer->analyzeContract($sourceCode);

// Validate with v4 schema
$validator = new SecurityFindingSchemaValidator('v4');
$validation = $validator->validateFindings($findings);
```

### SecurityFindingValidator Usage

```php
// Create enhanced validator
$validator = new SecurityFindingSchemaValidator('v4');

// Validate single finding
$result = $validator->validateFinding($finding);
if ($result['valid']) {
    echo "Quality Score: " . $result['quality_score'];
    echo "Completeness: " . $result['completeness'];
}

// Generate optimized template
$template = $validator->createPromptOptimizedTemplate([
    'severity' => 'HIGH',
    'category' => 'SWC-107-Reentrancy'
]);
```

## Best Practices for AI Models

### Do's
- ✅ Always provide specific line numbers and code snippets
- ✅ Include step-by-step exploitation scenarios  
- ✅ Give concrete, verifiable remediation steps
- ✅ Use appropriate severity levels based on actual impact
- ✅ Include comprehensive risk assessment
- ✅ Provide secure implementation examples
- ✅ Reference authoritative sources (OWASP, SWC Registry)

### Don'ts  
- ❌ Use vague titles like "Security issue found"
- ❌ Provide generic recommendations without specifics
- ❌ Assign severity without proper risk assessment
- ❌ Skip location information or code snippets
- ❌ Use incorrect enum values or formats
- ❌ Provide recommendations without verification steps

## Testing and Validation

### Schema Validation Commands

```bash
# Validate findings with new schema
php artisan security:validate-findings --schema=v4

# Test schema with example findings
php artisan security:test-schema --examples

# Generate schema statistics
php artisan security:schema-stats --version=v4
```

### Quality Metrics

The system automatically tracks:
- **Validation Success Rate**: Percentage of findings passing schema validation
- **Quality Score Distribution**: Histogram of quality scores across findings
- **Completeness Metrics**: Average completeness percentage
- **Processing Performance**: Validation time per finding

## Continuous Improvement

### Feedback Loop
1. **Collection**: Gather validation results and quality metrics
2. **Analysis**: Identify common validation failures and quality issues
3. **Schema Updates**: Enhance schema based on real-world usage
4. **Prompt Refinement**: Update AI prompts for better compliance
5. **Training Data**: Use high-quality findings for model fine-tuning

### Version Management
- v4.0: Current version with enhanced AI optimization
- v3.0: Previous version with basic blockchain support
- v2.0: Legacy version with OWASP focus
- v1.0: Initial implementation

## Support and Resources

### Documentation
- Schema file: `schemas/security-finding-v4-prompt-optimized.json`
- Examples: `schemas/security-finding-examples-v4.json` 
- Validator: `app/Services/SecurityFindingSchemaValidator.php`

### Integration Support
- Validation service with quality scoring
- Template generation for consistent findings
- Batch processing with performance optimization
- Error reporting with actionable suggestions

### Community
- Submit schema improvements via GitHub issues
- Share high-quality finding examples
- Report validation bugs and edge cases
- Contribute to prompt engineering best practices

This schema represents the cutting edge of AI-powered security analysis, providing the structure and guidance needed for consistently high-quality smart contract vulnerability detection and reporting.