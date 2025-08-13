<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OWASPSecurityAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

final class OWASPSecurityController extends Controller
{
    public function __construct(
        private readonly OWASPSecurityAnalyzer $analyzer
    ) {}

    /**
     * Analyze smart contract code using OWASP-style security finding schema.
     */
    public function analyzeContract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_code' => 'required|string|min:10|max:50000',
            'contract_name' => 'sometimes|string|max:100',
            'focus_areas' => 'sometimes|array|max:10',
            'focus_areas.*' => [
                'string',
                Rule::in(OWASPSecurityAnalyzer::getSupportedCategories())
            ],
            'include_summary' => 'sometimes|boolean',
            'include_metadata' => 'sometimes|boolean'
        ]);

        try {
            $sourceCode = $validated['source_code'];
            $contractName = $validated['contract_name'] ?? 'Contract';
            $focusAreas = $validated['focus_areas'] ?? ['Re-entrancy', 'Access Control', 'Integer Overflow'];
            $includeSummary = $validated['include_summary'] ?? true;
            $includeMetadata = $validated['include_metadata'] ?? false;

            // Perform security analysis
            $findings = $this->analyzer->analyzeContract($sourceCode, $contractName, $focusAreas);
            
            $response = [
                'success' => true,
                'contract_name' => $contractName,
                'analysis_timestamp' => now()->toISOString(),
                'findings_count' => count($findings),
                'findings' => $findings
            ];

            // Add summary if requested
            if ($includeSummary) {
                $response['summary'] = $this->analyzer->generateSummary($findings);
            }

            // Add metadata if requested
            if ($includeMetadata) {
                $response['metadata'] = [
                    'schema_version' => '1.0.0',
                    'analysis_engine' => 'OWASP Security Analyzer',
                    'focus_areas' => $focusAreas,
                    'supported_categories' => OWASPSecurityAnalyzer::getSupportedCategories(),
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Analysis failed',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Get supported vulnerability categories.
     */
    public function getSupportedCategories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'categories' => OWASPSecurityAnalyzer::getSupportedCategories(),
            'count' => count(OWASPSecurityAnalyzer::getSupportedCategories())
        ]);
    }

    /**
     * Validate OWASP-style security finding.
     */
    public function validateFinding(Request $request): JsonResponse
    {
        $rules = [
            // Required fields
            'severity' => 'required|string|in:CRITICAL,HIGH,MEDIUM,LOW,INFO',
            'title' => 'required|string|min:5|max:100',
            'line' => 'required|integer|min:1',
            'recommendation' => 'required|string|min:20|max:1000',
            
            // Optional fields
            'id' => 'sometimes|string|max:50',
            'category' => [
                'sometimes',
                'string',
                Rule::in(OWASPSecurityAnalyzer::getSupportedCategories())
            ],
            'function' => 'sometimes|string|max:100',
            'contract' => 'sometimes|string|max:100',
            'file' => 'sometimes|string|max:200',
            'description' => 'sometimes|string|max:800',
            'impact' => 'sometimes|string|in:FINANCIAL_LOSS,FUND_DRAINAGE,UNAUTHORIZED_ACCESS,SERVICE_DISRUPTION,DATA_EXPOSURE,REPUTATION_DAMAGE,GOVERNANCE_COMPROMISE,MINIMAL',
            'exploitability' => 'sometimes|string|in:TRIVIAL,EASY,MODERATE,DIFFICULT,THEORETICAL',
            'confidence' => 'sometimes|string|in:HIGH,MEDIUM,LOW',
            'false_positive_risk' => 'sometimes|string|in:LOW,MEDIUM,HIGH',
            'cvss_score' => 'sometimes|numeric|min:0.0|max:10.0',
            'code_snippet' => 'sometimes|string|max:500',
            'fix_example' => 'sometimes|string|max:500',
            'attack_vector' => 'sometimes|string|max:300',
            'swc_id' => 'sometimes|string|regex:/^SWC-\d{3}$/',
            'blockchain_networks' => 'sometimes|array',
            'blockchain_networks.*' => 'string|in:ETHEREUM,POLYGON,BSC,ARBITRUM,OPTIMISM,AVALANCHE,FANTOM,ALL_EVM',
            'token_standard' => 'sometimes|string|in:ERC20,ERC721,ERC1155,ERC777,ERC4626,BEP20,N/A',
            'defi_category' => 'sometimes|string|in:AMM,LENDING,YIELD_FARMING,STAKING,DERIVATIVES,DAO,BRIDGE,N/A',
            'remediation_effort' => 'sometimes|string|in:TRIVIAL,LOW,MEDIUM,HIGH,EXTENSIVE',
            'remediation_priority' => 'sometimes|integer|min:1|max:5',
            'tags' => 'sometimes|array|max:10',
            'tags.*' => 'string|regex:/^[a-z0-9_-]+$/|max:30',
            'status' => 'sometimes|string|in:OPEN,REVIEWING,CONFIRMED,FALSE_POSITIVE,FIXED,ACCEPTED,DUPLICATE',
            'assignee' => 'sometimes|string|max:100',
            'created_at' => 'sometimes|date_format:Y-m-d\TH:i:s\Z',
            'ai_model' => 'sometimes|string|max:50',
            'analysis_version' => 'sometimes|string|max:20',
            'tokens_used' => 'sometimes|integer|min:0'
        ];

        try {
            $validated = $request->validate($rules);
            
            return response()->json([
                'success' => true,
                'message' => 'Finding is valid according to OWASP-style schema',
                'validated_data' => $validated
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Finding validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Get OWASP-style schema documentation.
     */
    public function getSchemaInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'schema' => [
                'name' => 'OWASP-Style Security Finding Schema',
                'version' => '1.0.0',
                'description' => 'Streamlined OWASP-compliant schema for blockchain security findings',
                'required_fields' => ['severity', 'title', 'line', 'recommendation'],
                'severity_levels' => ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO'],
                'supported_categories' => OWASPSecurityAnalyzer::getSupportedCategories(),
                'impact_types' => [
                    'FINANCIAL_LOSS',
                    'FUND_DRAINAGE',
                    'UNAUTHORIZED_ACCESS',
                    'SERVICE_DISRUPTION',
                    'DATA_EXPOSURE',
                    'REPUTATION_DAMAGE',
                    'GOVERNANCE_COMPROMISE',
                    'MINIMAL'
                ],
                'exploitability_levels' => [
                    'TRIVIAL',
                    'EASY',
                    'MODERATE',
                    'DIFFICULT',
                    'THEORETICAL'
                ],
                'blockchain_networks' => [
                    'ETHEREUM',
                    'POLYGON',
                    'BSC',
                    'ARBITRUM',
                    'OPTIMISM',
                    'AVALANCHE',
                    'FANTOM',
                    'ALL_EVM'
                ]
            ],
            'example_finding' => [
                'severity' => 'HIGH',
                'title' => 'Re-entrancy',
                'line' => 125,
                'recommendation' => 'Implement checks-effects-interactions pattern with ReentrancyGuard modifier',
                'category' => 'Re-entrancy',
                'function' => 'withdraw',
                'contract' => 'VulnerableBank',
                'confidence' => 'HIGH'
            ]
        ]);
    }

    /**
     * Get example vulnerable contract for testing.
     */
    public function getExampleContract(): JsonResponse
    {
        $vulnerableContract = <<<SOLIDITY
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract VulnerableExample {
    mapping(address => uint256) public balances;
    address public owner;

    constructor() {
        owner = msg.sender;
    }

    function deposit() public payable {
        balances[msg.sender] += msg.value;
    }

    function withdraw(uint256 amount) public {
        require(balances[msg.sender] >= amount);
        msg.sender.call{value: amount}(""); // Re-entrancy vulnerability
        balances[msg.sender] -= amount;
    }

    function adminWithdraw() public {
        require(tx.origin == owner); // tx.origin vulnerability
        payable(owner).transfer(address(this).balance);
    }
}
SOLIDITY;

        return response()->json([
            'success' => true,
            'contract_name' => 'VulnerableExample',
            'source_code' => $vulnerableContract,
            'known_vulnerabilities' => [
                'Re-entrancy in withdraw function',
                'tx.origin usage in adminWithdraw function',
                'Missing return value check for external call'
            ],
            'suggested_focus_areas' => ['Re-entrancy', 'Access Control', 'Input Validation']
        ]);
    }
}