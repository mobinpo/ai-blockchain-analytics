# 🎯 Prompt Engineering: JSON Schema for Security Findings

## 🎯 **Overview**

The Solidity Cleaner now includes a specialized JSON schema designed for AI prompt engineering and security analysis. This schema follows OWASP standards while being optimized for LLM consumption and actionable security findings.

## 📋 **JSON Schema Design**

### **Core Schema Structure**
```json
{
  "severity": "CRITICAL|HIGH|MEDIUM|LOW|INFO",
  "title": "Re-entrancy in withdraw function", 
  "line": 125,
  "recommendation": "Implement checks-effects-interactions pattern...",
  "category": "Re-entrancy",
  "function": "withdraw",
  "contract": "VulnerableBank",
  "code_snippet": "vulnerable code here",
  "fix_snippet": "secure code here"
}
```

### **Key Design Principles**

1. **🎯 Prompt-Optimized**: Compact, AI-friendly field names and structure
2. **📊 OWASP Compliant**: Follows security industry standards
3. **🔧 Actionable**: Every finding includes clear remediation steps
4. **📈 Trackable**: Includes metadata for analysis pipeline
5. **🤖 AI-Ready**: Schema designed for LLM generation and consumption

## 🚀 **Usage Examples**

### **Generate AI-Ready Prompt**
```bash
# Generate findings prompt for demo contract
php artisan solidity:clean --demo --findings

# Generate for real contract with output
php artisan solidity:clean --contract=0xAddress --findings --output=analysis-prompt.txt

# Generate for local file
php artisan solidity:clean --file=contract.sol --level=prompt --findings
```

### **Expected AI Response Format**
```json
{
  "findings": [
    {
      "id": "SC-0001",
      "severity": "HIGH",
      "title": "Re-entrancy in withdraw function",
      "category": "Re-entrancy", 
      "line": 45,
      "function": "withdraw",
      "contract": "VulnerableBank",
      "recommendation": "Implement checks-effects-interactions pattern by moving state changes before external calls",
      "description": "External call executed before state update allows recursive calls",
      "impact": "FUND_DRAINAGE",
      "exploitability": "EASY",
      "confidence": "HIGH",
      "code_snippet": "msg.sender.call{value: amount}(\"\");\nbalances[msg.sender] -= amount;",
      "fix_snippet": "balances[msg.sender] -= amount;\n(bool success, ) = msg.sender.call{value: amount}(\"\");"
    }
  ],
  "summary": {
    "total_findings": 1,
    "critical_count": 0,
    "high_count": 1,
    "medium_count": 0, 
    "low_count": 0,
    "overall_risk": "HIGH",
    "gas_optimizations": 0
  }
}
```

## 📊 **Schema Categories**

### **Security Categories**
- **Re-entrancy**: External call vulnerabilities
- **Access Control**: Authorization and permission issues
- **Integer Overflow/Underflow**: Arithmetic vulnerabilities
- **Input Validation**: Data sanitization issues
- **Business Logic**: Logic flaws and design issues
- **Cryptographic Issues**: Hash, signature, randomness problems
- **Information Disclosure**: Data exposure vulnerabilities
- **Denial of Service**: Resource exhaustion attacks
- **Front-running/MEV**: Transaction ordering vulnerabilities
- **Oracle Manipulation**: Price and data feed attacks
- **Flash Loan Attack**: Instant liquidity exploits
- **Gas Optimization**: Efficiency improvements
- **Code Quality**: Best practice violations

### **Severity Levels**
- **CRITICAL**: Immediate threat to funds or protocol
- **HIGH**: Significant security risk with likely exploitation
- **MEDIUM**: Moderate risk requiring attention
- **LOW**: Minor issues or best practice violations  
- **INFO**: Informational findings for awareness

### **Impact Types**
- **FUND_LOSS**: Direct financial impact
- **FUND_DRAINAGE**: Complete protocol drainage
- **UNAUTHORIZED_ACCESS**: Permission bypass
- **SERVICE_DISRUPTION**: Availability impact
- **DATA_EXPOSURE**: Information disclosure
- **GOVERNANCE_COMPROMISE**: Control mechanism bypass
- **GAS_INEFFICIENCY**: Cost optimization opportunity
- **MINIMAL**: Negligible impact

## 🎪 **Demo Example**

### **Sample Prompt Generated**
```
# SOLIDITY SECURITY ANALYSIS - FINDINGS GENERATION

## Contract Context
- **Contracts**: SampleToken
- **Functions**: 5
- **Original Size**: 64 lines → 29 lines (54.69% reduction)
- **Token Estimate**: 317 tokens

## Cleaned Contract Code
```solidity
pragma solidity ^0.8.0;
import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
contract SampleToken is ERC20, Ownable{
    uint256 public constant MAX_SUPPLY = 1000000 * 10**18;
    mapping(address => bool) public frozenAccounts;
    constructor() ERC20("SampleToken", "STK"){
        _mint(msg.sender, MAX_SUPPLY);
    }
    function freezeAccount(address account) public onlyOwner{
        frozenAccounts[account] = true;
        emit AccountFrozen(account);
    }
    function unfreezeAccount(address account) public onlyOwner{
        frozenAccounts[account] = false;
        emit AccountUnfrozen(account);
    }
    event AccountFrozen(address indexed account);
    event AccountUnfrozen(address indexed account);
    function transfer(address to, uint256 amount) public override returns(bool){
        require(!frozenAccounts[msg.sender], "Account is frozen");
        require(!frozenAccounts[to], "Recipient account is frozen");
        return super.transfer(to, amount);
    }
    function emergencyMint(address to, uint256 amount) public onlyOwner{
        require(totalSupply() + amount <= MAX_SUPPLY, "Would exceed max supply");
        _mint(to, amount);
    }
}
```

## Analysis Requirements
Analyze the above Solidity contract for security vulnerabilities, gas optimizations, and code quality issues.

**Return findings in this exact JSON format:**
[JSON template provided]

## Analysis Focus Areas
1. **Critical Security Issues**: Re-entrancy, access control, integer overflow
2. **High-Risk Vulnerabilities**: Logic flaws, external call safety
3. **Medium-Risk Issues**: Input validation, state management
4. **Low-Risk Items**: Code quality, best practices
5. **Gas Optimizations**: Loop efficiency, storage usage

## Important Notes
- Use line numbers from the cleaned code above
- Provide actionable recommendations
- Include code snippets showing the issue and fix
- Set appropriate severity levels
- Ensure all findings are real security concerns
```

## 🔧 **Service Integration**

### **PHP Service Usage**
```php
use App\Services\SolidityCleanerService;

$cleaner = new SolidityCleanerService();

// Clean contract and generate findings prompt
$result = $cleaner->cleanWithLevel($sourceCode, 'prompt');
$findingsPrompt = $cleaner->createFindingsPrompt($result);

// Create findings structure from AI response
$aiFindings = [
    ['severity' => 'HIGH', 'title' => 'Re-entrancy', 'line' => 45, 'recommendation' => '...']
];
$structuredFindings = $cleaner->createFindingsStructure($result, $aiFindings);

// Export as JSON
$json = $cleaner->exportFindings($result, $aiFindings);

// Validate findings
$errors = $cleaner->validateFindings($aiFindings);
```

### **API Integration Example**
```php
// Controller method
public function analyzeContract(Request $request)
{
    $cleaner = app(SolidityCleanerService::class);
    
    // Clean the contract
    $cleaned = $cleaner->cleanWithLevel($request->source_code, 'prompt');
    
    // Generate AI prompt
    $prompt = $cleaner->createFindingsPrompt($cleaned);
    
    // Send to AI service (pseudo-code)
    $aiResponse = $this->callAI($prompt);
    $findings = json_decode($aiResponse, true);
    
    // Structure findings with metadata
    $structuredFindings = $cleaner->createFindingsStructure($cleaned, $findings['findings']);
    
    return response()->json([
        'cleaned_contract' => $cleaned['cleaned_code'],
        'analysis_prompt' => $prompt,
        'findings' => $structuredFindings,
        'token_estimate' => $cleaner->estimateTokens($cleaned['cleaned_code'])
    ]);
}
```

## 📈 **Schema Benefits**

### **For AI Models**
- **🎯 Clear Structure**: Consistent field names and types
- **📋 Guided Output**: Schema enforces proper formatting
- **🔍 Context Aware**: Includes contract and code context
- **⚡ Token Efficient**: Optimized for minimal token usage

### **For Developers**
- **🛡️ Actionable**: Every finding includes fix recommendations
- **📊 Trackable**: Severity and impact classification
- **🔧 Implementable**: Code snippets for vulnerabilities and fixes
- **📈 Analyzable**: Structured data for reporting and metrics

### **For Security Teams**
- **🎯 Prioritized**: Clear severity and impact levels
- **📋 Standardized**: OWASP-compliant categorization
- **🔍 Detailed**: Technical descriptions and attack vectors
- **📊 Reportable**: Summary statistics for risk assessment

## 🎯 **Best Practices**

### **AI Prompt Engineering**
```bash
# Always use prompt optimization for AI analysis
php artisan solidity:clean --file=contract.sol --level=prompt --findings

# Include contract context in prompts
php artisan solidity:clean --contract=0xAddress --network=ethereum --findings

# Export findings for further processing
php artisan solidity:clean --file=contract.sol --findings --output=analysis-prompt.txt
```

### **Schema Compliance**
1. **Required Fields**: Always include severity, title, line, recommendation
2. **Line Numbers**: Use line numbers from cleaned code
3. **Actionable Recommendations**: Provide specific fix instructions
4. **Code Snippets**: Include vulnerable and secure code examples
5. **Confidence Levels**: Indicate AI analysis confidence

### **Quality Assurance**
```php
// Validate findings before processing
$errors = $cleaner->validateFindings($findings);
if (!empty($errors)) {
    throw new ValidationException('Invalid findings: ' . implode(', ', $errors));
}

// Check finding quality
foreach ($findings as $finding) {
    if (strlen($finding['recommendation']) < 20) {
        log_warning("Short recommendation for finding: {$finding['title']}");
    }
}
```

## 📋 **Complete Schema Reference**

### **Required Fields**
- `severity`: Risk level (CRITICAL/HIGH/MEDIUM/LOW/INFO)
- `title`: Concise vulnerability description
- `line`: Line number in cleaned code
- `recommendation`: Actionable fix guidance

### **Core Fields**
- `category`: Security category classification
- `function`: Function containing vulnerability
- `contract`: Contract name
- `description`: Technical vulnerability description
- `impact`: Primary impact type
- `exploitability`: Exploitation difficulty

### **Code Context**
- `code_snippet`: Vulnerable code excerpt
- `fix_snippet`: Secure code example
- `attack_vector`: Brief exploit description

### **Metadata Fields**
- `confidence`: AI analysis confidence
- `cvss_score`: CVSS v3.1 base score
- `swc_id`: Smart Contract Weakness ID
- `remediation.effort`: Development effort required
- `ai_context`: AI model and token usage
- `source_context`: Cleaning statistics

## 🎉 **Summary**

The prompt-engineering optimized JSON schema provides:

- **🎯 AI-Optimized Structure** for consistent LLM output
- **📊 OWASP Compliance** for industry standard security analysis
- **🔧 Actionable Findings** with clear fix recommendations  
- **📈 Rich Metadata** for analysis pipeline integration
- **⚡ Token Efficient** design for cost-effective AI usage
- **🛡️ Security Focused** categories and impact assessment

Perfect for automated security analysis, AI-powered auditing, and scalable smart contract security assessment! 🚀✨