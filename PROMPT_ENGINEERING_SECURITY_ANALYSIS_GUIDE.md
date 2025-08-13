# 🎯 AI Prompt Engineering for Security Analysis

## 📋 **Table of Contents**
- [Overview](#overview)
- [JSON Schema Design](#json-schema-design)
- [Prompt Engineering Best Practices](#prompt-engineering-best-practices)
- [Schema Comparison](#schema-comparison)
- [Implementation Examples](#implementation-examples)
- [Testing & Validation](#testing--validation)

## 🎯 **Overview**

This guide covers the design and implementation of AI-optimized JSON schemas for smart contract security analysis. Our approach focuses on **prompt engineering excellence** to maximize AI accuracy while minimizing token usage.

### **Schema Hierarchy**

```
📁 schemas/
├── 🔹 security-finding-simple.json          # Basic format (user example)
├── 🚀 security-finding-prompt-engineered.json  # AI-optimized (recommended)
├── 📊 security-finding-owasp-style.json     # OWASP compliance
├── 🔧 security-finding-v3.json              # Legacy comprehensive
└── 💾 solidity-cleaner-findings-schema.json # Integration format
```

## 🔬 **JSON Schema Design**

### **1. Simple Format** 
*Perfect for basic use cases and quick analysis*

```json
{
  "severity": "HIGH",
  "title": "Re-entrancy", 
  "line": 125,
  "recommendation": "Implement checks-effects-interactions pattern..."
}
```

**Use Cases:**
- ✅ Quick vulnerability scanning
- ✅ Basic security reports
- ✅ Learning/training environments
- ✅ Proof-of-concept analysis

### **2. AI-Optimized Format** 
*Designed for maximum AI accuracy and efficiency*

```json
{
  "severity": "CRITICAL",
  "title": "Reentrancy Vulnerability",
  "line": 42,
  "recommendation": "Implement checks-effects-interactions pattern by moving state changes before external calls. Add OpenZeppelin's nonReentrant modifier.",
  "category": "Reentrancy",
  "function": "withdraw",
  "contract": "VulnerableBank",
  "description": "External call occurs before balance update, allowing recursive calls to drain funds.",
  "impact": "FUND_LOSS",
  "exploitability": "EASY",
  "confidence": "HIGH",
  "code_snippet": "msg.sender.call{value: amount}(\"\");\\nbalances[msg.sender] -= amount;",
  "fix_example": "balances[msg.sender] -= amount;\\n(bool success, ) = msg.sender.call{value: amount}(\"\");",
  "attack_vector": "Attacker deploys malicious contract with fallback function that recursively calls withdraw()",
  "swc_id": "SWC-107",
  "remediation_effort": "LOW",
  "remediation_priority": 1,
  "tags": ["reentrancy", "external-call", "critical"]
}
```

**Key Features:**
- 🎯 **AI Guidance**: Built-in instructions for AI models
- 🔍 **Precise Categories**: Standardized vulnerability classifications  
- ⚡ **Token Efficient**: Optimized field lengths and structures
- 🛡️ **Security Focus**: Emphasis on exploitability and impact
- 📊 **Actionable**: Clear remediation guidance with priorities

### **3. OWASP Compliance Format**
*Enterprise-grade format aligned with security standards*

```json
{
  "severity": "HIGH",
  "title": "Re-entrancy",
  "line": 125, 
  "recommendation": "...",
  "owasp_category": "A01-Broken Access Control",
  "cvss_score": 8.1,
  "historical_incidents": [
    {
      "name": "The DAO Hack",
      "amount_lost": "$50 million", 
      "year": 2016
    }
  ],
  "blockchain_networks": ["ALL_EVM"],
  "defi_category": "LENDING"
}
```

## 🚀 **Prompt Engineering Best Practices**

### **1. Schema Selection Strategy**

```php
// Choose schema based on use case
match ($analysisType) {
    'quick' => 'security-finding-simple.json',
    'production' => 'security-finding-prompt-engineered.json', 
    'audit' => 'security-finding-owasp-style.json',
    'research' => 'security-finding-v3.json'
}
```

### **2. AI Instructions Integration**

Our prompt-engineered schema includes built-in AI guidance:

```json
{
  "ai_instructions": {
    "analysis_approach": "1. Scan code for security patterns 2. Identify exact vulnerability location 3. Assess severity based on impact + exploitability 4. Provide actionable remediation",
    "response_format": "Always return valid JSON array of findings. Each finding must have required fields. Use consistent terminology.",
    "severity_guidelines": {
      "CRITICAL": "Immediate fund loss/theft possible (reentrancy, flash loans, major access control bypass)",
      "HIGH": "Significant financial/security risk (overflow in financial calculations, privilege escalation)",
      "MEDIUM": "Moderate risk requiring attention (DoS conditions, information disclosure)",
      "LOW": "Minor security concern (gas optimization, code quality)",
      "INFO": "Best practice recommendation (documentation, style)"
    }
  }
}
```

### **3. Token Optimization Techniques**

#### **Field Length Limits**
```json
{
  "title": {
    "maxLength": 60,        // Concise but descriptive
    "ai_guidance": "Use precise, searchable terms"
  },
  "recommendation": {
    "maxLength": 800,       // Detailed but focused
    "ai_guidance": "Structure as: 1) Action 2) Implementation 3) Best practices"
  },
  "code_snippet": {
    "maxLength": 400,       // Minimal context
    "ai_guidance": "Include only problematic lines plus 1-2 lines context"
  }
}
```

#### **Enum Standardization**
```json
{
  "severity": {
    "enum": ["CRITICAL", "HIGH", "MEDIUM", "LOW", "INFO"],
    "ai_guidance": "Always classify based on potential financial/security impact"
  },
  "exploitability": {
    "enum": ["TRIVIAL", "EASY", "MODERATE", "DIFFICULT", "THEORETICAL"],
    "ai_guidance": "TRIVIAL=single transaction, EASY=simple contract interaction"
  }
}
```

### **4. Prompt Structure Template**

```markdown
# SOLIDITY SECURITY ANALYSIS

## Contract Code
```solidity
{CLEANED_CODE}
```

## Analysis Requirements
Analyze for security vulnerabilities following this EXACT JSON schema:

```json
{SCHEMA_EXAMPLE}
```

## AI Instructions
- **Severity**: Base on financial impact + exploitability
- **Title**: Use standard terms (max 60 chars)
- **Line Numbers**: Use exact line from provided code
- **Recommendations**: Start with action verb, be specific
- **Confidence**: HIGH for clear patterns, MEDIUM for likely issues

## Output Format
Return ONLY valid JSON:
```json
{
  "findings": [
    // Array of finding objects
  ]
}
```

## Focus Areas
1. **CRITICAL**: Reentrancy, flash loans, major access control bypass
2. **HIGH**: Integer overflow in calculations, privilege escalation  
3. **MEDIUM**: DoS conditions, information disclosure
4. **LOW**: Gas optimization, code quality improvements
```

## 📊 **Schema Comparison**

| Feature | Simple | AI-Optimized | OWASP Style | Legacy v3 |
|---------|--------|--------------|-------------|-----------|
| **Required Fields** | 4 | 4 | 4 | 6 |
| **Total Fields** | 11 | 25 | 35 | 45+ |
| **AI Guidance** | ❌ | ✅ | ⚠️ | ❌ |
| **Token Efficiency** | ✅ | ✅ | ⚠️ | ❌ |
| **Enterprise Ready** | ❌ | ✅ | ✅ | ✅ |
| **Validation** | Basic | Comprehensive | Advanced | Complete |
| **Use Case** | Quick | Production | Audit | Research |

### **Token Usage Estimates**

| Schema Type | Avg Tokens/Finding | Example Usage |
|-------------|-------------------|---------------|
| **Simple** | 150-200 | Quick scans, learning |
| **AI-Optimized** | 300-400 | Production analysis |
| **OWASP Style** | 500-600 | Security audits |
| **Legacy v3** | 700+ | Research, compliance |

## 🛠️ **Implementation Examples**

### **1. Basic Security Scan**

```php
use App\Services\SecurityFindingValidator;

$validator = new SecurityFindingValidator();
$findings = [
    [
        "severity" => "HIGH",
        "title" => "Reentrancy Vulnerability", 
        "line" => 42,
        "recommendation" => "Add nonReentrant modifier and implement CEI pattern"
    ]
];

$result = $validator->validateFindings($findings);
```

### **2. AI Analysis Integration**

```php
use App\Services\SolidityCleanerService;

$cleaner = new SolidityCleanerService();
$prompt = $cleaner->createOptimizedFindingsPrompt($contractCode, [
    'schema_type' => 'prompt-engineered',
    'focus_areas' => ['reentrancy', 'access_control', 'overflow'],
    'max_findings' => 10
]);
```

### **3. Schema Loading**

```php
// Load specific schema
$schemaContent = file_get_contents(base_path('schemas/security-finding-simple.json'));
$schema = json_decode($schemaContent, true);

// Validate against schema
$validator = new SecurityFindingValidator($schema);
$result = $validator->validate($finding);
```

## ✅ **Testing & Validation**

### **1. Schema Validation Tests**

```php
public function testSimpleSchemaValidation()
{
    $finding = [
        'severity' => 'HIGH',
        'title' => 'Reentrancy',
        'line' => 125,
        'recommendation' => 'Add nonReentrant modifier'
    ];
    
    $validator = new SecurityFindingValidator('simple');
    $result = $validator->validate($finding);
    
    $this->assertTrue($result['valid']);
}
```

### **2. AI Response Validation**

```php
public function testAiOptimizedResponse()
{
    $mockResponse = [
        'findings' => [
            [
                'severity' => 'CRITICAL',
                'title' => 'Flash Loan Attack Vector',
                'line' => 234,
                'recommendation' => 'Implement flash loan protection...',
                'category' => 'Flash Loan Attack',
                'confidence' => 'HIGH'
            ]
        ]
    ];
    
    $validator = new SecurityFindingValidator('prompt-engineered');
    $result = $validator->validateFindings($mockResponse['findings']);
    
    $this->assertGreaterThan(0, $result['valid_count']);
}
```

### **3. Performance Benchmarks**

```bash
# Run schema validation benchmarks
php artisan test tests/Unit/SecuritySchemaPerformanceTest

# Expected results:
# Simple Schema:         ~1ms per finding
# AI-Optimized Schema:   ~2ms per finding  
# OWASP Style Schema:    ~3ms per finding
# Legacy v3 Schema:      ~5ms per finding
```

## 🎯 **Best Practices Summary**

### **✅ DO**
- Use **AI-Optimized schema** for production analysis
- Include **specific line numbers** from cleaned code
- Provide **actionable recommendations** with implementation details
- Use **standard vulnerability terminology** (SWC, OWASP)
- Implement **confidence scoring** for AI findings
- Structure prompts with **clear examples** and **strict formatting**

### **❌ DON'T**
- Use verbose descriptions (wastes tokens)
- Mix different schema formats in same analysis
- Ignore AI guidance fields in schemas
- Skip validation of AI responses
- Use deprecated severity levels
- Provide vague or generic recommendations

## 🚀 **Integration Commands**

```bash
# Generate findings with AI-optimized schema
php artisan solidity:analyze contract.sol --schema=prompt-engineered

# Validate existing findings against schema
php artisan security:validate-findings findings.json

# Compare schema performance
php artisan security:benchmark-schemas

# Generate prompt template
php artisan security:generate-prompt --schema=simple --output=prompt.txt
```

## 📈 **Future Enhancements**

1. **Dynamic Schema Selection**: Auto-select optimal schema based on contract complexity
2. **AI Model Specific Schemas**: Tailored schemas for GPT-4, Claude, Gemini
3. **Severity Auto-Calibration**: Machine learning based severity classification
4. **Real-time Validation**: Live validation during AI streaming responses
5. **Multi-language Support**: Extend schemas for other blockchain languages

---

**🎉 Result**: AI-optimized security analysis with maximum accuracy, minimal token usage, and enterprise-grade compliance!