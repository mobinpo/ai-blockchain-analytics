<template>
    <div class="space-y-6">
        <!-- Demo Steps -->
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center space-x-4">
                <div v-for="(step, index) in demoSteps" :key="index" class="flex items-center">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium" 
                         :class="getStepClass(index)">
                        {{ index + 1 }}
                    </div>
                    <div v-if="index < demoSteps.length - 1" class="w-8 h-0.5 bg-gray-300"></div>
                </div>
            </div>
        </div>

        <!-- Upload Section -->
        <div v-if="currentStep === 0" class="space-y-4">
            <h4 class="text-lg font-semibold text-gray-900">Upload Smart Contract</h4>
            
            <!-- Sample Contracts Tabs -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button 
                        v-for="sample in sampleContracts" 
                        :key="sample.id"
                        @click="selectedSample = sample.id"
                        class="py-2 px-1 border-b-2 font-medium text-sm"
                        :class="selectedSample === sample.id ? 'border-indigo-500 text-brand-500' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    >
                        {{ sample.name }}
                    </button>
                </nav>
            </div>
            
            <!-- Contract Code Display -->
            <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-sm max-h-64 overflow-y-auto">
                <pre>{{ getCurrentContract().code }}</pre>
            </div>
            
            <!-- Contract Info -->
            <div class="bg-blue-50 rounded-lg p-4">
                <h5 class="font-medium text-blue-900 mb-2">{{ getCurrentContract().name }}</h5>
                <p class="text-sm text-blue-700">{{ getCurrentContract().description }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span v-for="vuln in getCurrentContract().vulnerabilities" :key="vuln" 
                          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        {{ vuln }}
                    </span>
                </div>
            </div>
            
            <button 
                @click="startAnalysis"
                class="w-full flex items-center justify-center px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Analyze Contract Security
            </button>
        </div>

        <!-- Analysis Progress -->
        <div v-else-if="currentStep === 1" class="space-y-6">
            <h4 class="text-lg font-semibold text-gray-900">AI Security Analysis in Progress</h4>
            
            <!-- Analysis Steps -->
            <div class="space-y-4">
                <div v-for="(stage, index) in analysisStages" :key="index" class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div v-if="currentAnalysisStep > index" class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div v-else-if="currentAnalysisStep === index" class="w-6 h-6 border-2 border-indigo-500 rounded-full flex items-center justify-center">
                            <div class="w-3 h-3 bg-indigo-500 rounded-full animate-pulse"></div>
                        </div>
                        <div v-else class="w-6 h-6 border-2 border-gray-300 rounded-full"></div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">{{ stage.name }}</p>
                        <p class="text-xs text-gray-500">{{ stage.description }}</p>
                    </div>
                    <div v-if="currentAnalysisStep === index" class="text-xs text-brand-500 font-medium">
                        {{ stage.duration }}
                    </div>
                </div>
            </div>
            
            <!-- Overall Progress -->
            <div class="bg-gray-200 rounded-full h-2">
                <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500" 
                     :style="{ width: analysisProgress + '%' }"></div>
            </div>
            <div class="text-center text-sm text-gray-600">
                {{ analysisProgress }}% Complete
            </div>
        </div>

        <!-- Results -->
        <div v-else-if="currentStep === 2" class="space-y-6">
            <div class="flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-900">Security Analysis Results</h4>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                    High Risk
                </span>
            </div>
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-red-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-red-600">{{ results.critical }}</div>
                    <div class="text-xs text-red-700">Critical Issues</div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ results.medium }}</div>
                    <div class="text-xs text-yellow-700">Medium Issues</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ results.low }}</div>
                    <div class="text-xs text-green-700">Low Issues</div>
                </div>
            </div>
            
            <!-- Detailed Findings -->
            <div class="space-y-3">
                <h5 class="font-medium text-gray-900">Detected Vulnerabilities</h5>
                <div v-for="finding in results.findings" :key="finding.id" 
                     class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                      :class="getSeverityClass(finding.severity)">
                                    {{ finding.severity.toUpperCase() }}
                                </span>
                                <h6 class="font-medium text-gray-900">{{ finding.title }}</h6>
                            </div>
                            <p class="text-sm text-gray-600 mb-2">{{ finding.description }}</p>
                            <div class="text-xs text-gray-500">
                                Line {{ finding.line }} | {{ finding.category }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="flex space-x-3">
                <button 
                    @click="downloadReport"
                    class="flex-1 flex items-center justify-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download Report
                </button>
                <button 
                    @click="analyzeAnother"
                    class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-panel"
                >
                    Analyze Another
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

// Demo state
const currentStep = ref(0)
const currentAnalysisStep = ref(0)
const analysisProgress = ref(0)
const selectedSample = ref('vulnerable')

const demoSteps = ['Upload', 'Analyze', 'Results']

const sampleContracts = [
    {
        id: 'vulnerable',
        name: 'Vulnerable DeFi',
        description: 'A DeFi smart contract with multiple security vulnerabilities including reentrancy and overflow issues.',
        vulnerabilities: ['Reentrancy', 'Integer Overflow', 'Access Control'],
        code: `pragma solidity ^0.8.0;

contract VulnerableBank {
    mapping(address => uint256) private balances;
    
    function deposit() public payable {
        balances[msg.sender] += msg.value;
    }
    
    // VULNERABLE: Reentrancy attack possible
    function withdraw(uint256 amount) public {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        (bool success, ) = msg.sender.call{value: amount}("");
        require(success, "Transfer failed");
        
        balances[msg.sender] -= amount; // State change after external call
    }
    
    // VULNERABLE: Integer overflow not checked
    function multiply(uint256 a, uint256 b) public pure returns (uint256) {
        return a * b; // No overflow protection
    }
    
    function getBalance() public view returns (uint256) {
        return balances[msg.sender];
    }
}`
    },
    {
        id: 'flash_loan',
        name: 'Flash Loan Attack',
        description: 'Contract susceptible to flash loan price manipulation attacks.',
        vulnerabilities: ['Price Manipulation', 'Flash Loan Attack', 'Oracle Manipulation'],
        code: `pragma solidity ^0.8.0;

interface IPriceOracle {
    function getPrice() external view returns (uint256);
}

contract FlashLoanVulnerable {
    IPriceOracle public oracle;
    mapping(address => uint256) public deposits;
    
    constructor(address _oracle) {
        oracle = IPriceOracle(_oracle);
    }
    
    // VULNERABLE: Price manipulation through flash loans
    function liquidate(address user) public {
        uint256 price = oracle.getPrice(); // Manipulable price
        uint256 collateralValue = deposits[user] * price;
        
        if (collateralValue < getDebt(user)) {
            // Liquidation logic using manipulated price
            _liquidateUser(user);
        }
    }
    
    function getDebt(address user) internal pure returns (uint256) {
        return 1000 ether; // Simplified
    }
    
    function _liquidateUser(address user) internal {
        deposits[user] = 0;
    }
}`
    }
]

const analysisStages = [
    { name: 'Code Parsing', description: 'Analyzing contract structure and syntax', duration: '2s' },
    { name: 'Vulnerability Scanning', description: 'Checking for known security patterns', duration: '3s' },
    { name: 'OWASP Compliance', description: 'Validating against security standards', duration: '2s' },
    { name: 'AI Risk Assessment', description: 'Machine learning risk evaluation', duration: '3s' },
    { name: 'Report Generation', description: 'Compiling findings and recommendations', duration: '1s' }
]

const results = ref({
    critical: 3,
    medium: 2,
    low: 1,
    findings: [
        {
            id: 1,
            severity: 'critical',
            title: 'Reentrancy Vulnerability',
            description: 'External call made before state change, allowing recursive calls to drain contract funds.',
            line: 12,
            category: 'Access Control'
        },
        {
            id: 2,
            severity: 'critical',
            title: 'Integer Overflow',
            description: 'Arithmetic operation without overflow protection could lead to unexpected behavior.',
            line: 21,
            category: 'Arithmetic'
        },
        {
            id: 3,
            severity: 'medium',
            title: 'Unchecked External Call',
            description: 'External call result not properly validated, potential for failed transactions.',
            line: 14,
            category: 'Error Handling'
        }
    ]
})

// Computed
const getCurrentContract = () => {
    return sampleContracts.find(c => c.id === selectedSample.value) || sampleContracts[0]
}

// Methods
const getStepClass = (index) => {
    if (index < currentStep.value) return 'bg-green-500 text-white'
    if (index === currentStep.value) return 'bg-indigo-600 text-white'
    return 'bg-gray-300 text-gray-600'
}

const getSeverityClass = (severity) => {
    const classes = {
        critical: 'bg-red-100 text-red-800',
        medium: 'bg-yellow-100 text-yellow-800',
        low: 'bg-blue-100 text-blue-800'
    }
    return classes[severity] || classes.medium
}

const startAnalysis = () => {
    currentStep.value = 1
    simulateAnalysis()
}

const simulateAnalysis = () => {
    const totalDuration = 11000 // 11 seconds total
    const stageInterval = totalDuration / analysisStages.length
    
    const progressInterval = setInterval(() => {
        analysisProgress.value += 2
        if (analysisProgress.value >= 100) {
            clearInterval(progressInterval)
            currentStep.value = 2
        }
    }, totalDuration / 50)
    
    // Advance through analysis stages
    analysisStages.forEach((stage, index) => {
        setTimeout(() => {
            currentAnalysisStep.value = index + 1
        }, stageInterval * index)
    })
}

const downloadReport = () => {
    const reportData = {
        contract: getCurrentContract().name,
        timestamp: new Date().toISOString(),
        summary: results.value,
        findings: results.value.findings
    }
    
    const blob = new Blob([JSON.stringify(reportData, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `security-analysis-${Date.now()}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
}

const analyzeAnother = () => {
    currentStep.value = 0
    currentAnalysisStep.value = 0
    analysisProgress.value = 0
}
</script>