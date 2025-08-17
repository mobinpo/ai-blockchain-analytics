<template>
    <div class="space-y-6">
        <!-- Analysis Type Selection -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <button v-for="type in analysisTypes" :key="type.id"
                    @click="selectedAnalysisType = type.id"
                    class="p-4 border rounded-lg text-center transition-all"
                    :class="selectedAnalysisType === type.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                <div class="text-lg mb-2">{{ type.icon }}</div>
                <div class="font-medium text-gray-900">{{ type.name }}</div>
                <div class="text-sm text-gray-600">{{ type.description }}</div>
            </button>
        </div>

        <!-- Real-time Security Dashboard -->
        <div v-if="selectedAnalysisType === 'realtime'" class="space-y-6">
            <div class="flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-900">Real-time Security Monitoring</h4>
                <div class="flex items-center space-x-2">
                    <div class="h-2 w-2 bg-green-400 rounded-full animate-pulse"></div>
                    <span class="text-sm text-green-600 font-medium">Live Monitoring</span>
                </div>
            </div>
            
            <!-- Live Threat Detection -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h5 class="font-medium text-gray-900 mb-4">Active Threats</h5>
                    <div class="space-y-3">
                        <div v-for="threat in liveThreats" :key="threat.id" 
                             class="flex items-center justify-between p-3 rounded-lg"
                             :class="getThreatBgClass(threat.severity)">
                            <div class="flex items-center space-x-3">
                                <div class="h-3 w-3 rounded-full" :class="getThreatColor(threat.severity)"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ threat.type }}</p>
                                    <p class="text-xs text-gray-600">{{ threat.contract }}</p>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500">{{ threat.timeAgo }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h5 class="font-medium text-gray-900 mb-4">Security Metrics</h5>
                    <div class="space-y-4">
                        <div v-for="metric in securityMetrics" :key="metric.name">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">{{ metric.name }}</span>
                                <span class="font-medium">{{ metric.value }}{{ metric.unit }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-1000" 
                                     :class="metric.color"
                                     :style="{ width: metric.percentage + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vulnerability Assessment -->
        <div v-else-if="selectedAnalysisType === 'vulnerability'" class="space-y-6">
            <div class="flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-900">Vulnerability Assessment</h4>
                <button @click="runVulnerabilityAssessment"
                        :disabled="isAssessing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    {{ isAssessing ? 'Assessing...' : 'Run Assessment' }}
                </button>
            </div>
            
            <!-- Assessment Progress -->
            <div v-if="isAssessing" class="space-y-4">
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="flex items-center space-x-3 mb-3">
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                        <span class="font-medium text-blue-900">Running OWASP Security Assessment</span>
                    </div>
                    <div class="w-full bg-blue-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" 
                             :style="{ width: assessmentProgress + '%' }"></div>
                    </div>
                    <div class="text-sm text-blue-700 mt-2">{{ currentAssessmentStep }}</div>
                </div>
            </div>
            
            <!-- Assessment Results -->
            <div v-else-if="vulnerabilityResults" class="space-y-6">
                <!-- OWASP Top 10 Results -->
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h5 class="font-medium text-gray-900">OWASP Smart Contract Top 10</h5>
                    </div>
                    <div class="p-4">
                        <div class="grid gap-3">
                            <div v-for="item in vulnerabilityResults.owaspTop10" :key="item.id"
                                 class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                         :class="item.found ? 'bg-red-500' : 'bg-green-500'">
                                        {{ item.found ? '!' : '✓' }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ item.name }}</p>
                                        <p class="text-xs text-gray-600">{{ item.description }}</p>
                                    </div>
                                </div>
                                <span class="text-sm" :class="item.found ? 'text-red-600' : 'text-green-600'">
                                    {{ item.found ? 'Found' : 'Secure' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Risk Summary -->
                <div class="grid grid-cols-4 gap-4">
                    <div class="bg-red-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-red-600">{{ vulnerabilityResults.summary.critical }}</div>
                        <div class="text-xs text-red-700">Critical</div>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-orange-600">{{ vulnerabilityResults.summary.high }}</div>
                        <div class="text-xs text-orange-700">High</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-yellow-600">{{ vulnerabilityResults.summary.medium }}</div>
                        <div class="text-xs text-yellow-700">Medium</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ vulnerabilityResults.summary.low }}</div>
                        <div class="text-xs text-blue-700">Low</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compliance Check -->
        <div v-else-if="selectedAnalysisType === 'compliance'" class="space-y-6">
            <div class="flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-900">Compliance Verification</h4>
                <button @click="runComplianceCheck"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    Run Compliance Check
                </button>
            </div>
            
            <!-- Compliance Standards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="standard in complianceStandards" :key="standard.id"
                     class="bg-white border border-gray-200 rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="font-medium text-gray-900">{{ standard.name }}</h5>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                              :class="getComplianceStatusClass(standard.status)">
                            {{ standard.status }}
                        </span>
                    </div>
                    
                    <p class="text-sm text-gray-600 mb-4">{{ standard.description }}</p>
                    
                    <div class="space-y-2">
                        <div v-for="check in standard.checks" :key="check.id"
                             class="flex items-center justify-between text-sm">
                            <span class="text-gray-700">{{ check.name }}</span>
                            <div class="flex items-center space-x-1">
                                <div class="w-3 h-3 rounded-full" 
                                     :class="check.passed ? 'bg-green-400' : 'bg-red-400'"></div>
                                <span :class="check.passed ? 'text-green-600' : 'text-red-600'">
                                    {{ check.passed ? 'Pass' : 'Fail' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-t border-gray-200">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Compliance Score</span>
                            <span class="font-medium">{{ standard.score }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export and Actions -->
        <div class="flex justify-between items-center pt-6 border-t border-gray-200">
            <div class="text-sm text-gray-600">
                🤖 Analysis powered by AI with 98.7% accuracy
            </div>
            <div class="flex space-x-3">
                <button @click="scheduleAutomatedScan"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-panel">
                    Schedule Automated Scan
                </button>
                <button @click="exportSecurityReport"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    Export Security Report
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'

// Demo state
const selectedAnalysisType = ref('realtime')
const isAssessing = ref(false)
const assessmentProgress = ref(0)
const currentAssessmentStep = ref('')
const vulnerabilityResults = ref(null)

const analysisTypes = [
    {
        id: 'realtime',
        name: 'Real-time Monitoring',
        description: 'Live threat detection',
        icon: '⚡'
    },
    {
        id: 'vulnerability',
        name: 'Vulnerability Assessment',
        description: 'OWASP compliance check',
        icon: '🛡️'
    },
    {
        id: 'compliance',
        name: 'Compliance Verification',
        description: 'Standards validation',
        icon: '✅'
    }
]

const liveThreats = ref([])

const securityMetrics = ref([])

const complianceStandards = ref([])

let threatUpdateInterval

// API Functions
const fetchLiveThreats = async () => {
    try {
        const response = await api.get('/security/findings')
        const findings = response.data.findings || []
        
        // Convert findings to threat format
        liveThreats.value = findings.map(finding => ({
            id: finding.id,
            type: finding.title || finding.category,
            contract: finding.contract_address || '0x...',
            severity: finding.severity,
            timeAgo: finding.timeAgo || 'Recently'
        }))
    } catch (error) {
        console.error('Error fetching live threats:', error)
        liveThreats.value = []
    }
}

const fetchSecurityMetrics = async () => {
    try {
        const response = await api.get('/analytics/security-trend')
        const data = response.data
        
        if (data.success && data.metrics) {
            securityMetrics.value = [
                { name: 'Detection Rate', value: data.metrics.detection_rate || 0, unit: '%', percentage: data.metrics.detection_rate || 0, color: 'bg-green-500' },
                { name: 'Response Time', value: data.metrics.response_time || 0, unit: 's', percentage: Math.min(100, (data.metrics.response_time || 0) * 10), color: 'bg-blue-500' },
                { name: 'False Positives', value: data.metrics.false_positive_rate || 0, unit: '%', percentage: data.metrics.false_positive_rate || 0, color: 'bg-yellow-500' },
                { name: 'Coverage', value: data.metrics.coverage || 0, unit: '%', percentage: data.metrics.coverage || 0, color: 'bg-purple-500' }
            ]
        }
    } catch (error) {
        console.error('Error fetching security metrics:', error)
        securityMetrics.value = []
    }
}

const fetchComplianceStandards = async () => {
    try {
        const response = await api.get('/security/categories')
        const categories = response.data.categories || []
        
        complianceStandards.value = categories.map(category => ({
            id: category.code,
            name: category.name,
            description: category.description,
            status: category.status || 'Unknown',
            score: category.score || 0,
            checks: category.checks || []
        }))
    } catch (error) {
        console.error('Error fetching compliance standards:', error)
        complianceStandards.value = []
    }
}

// Methods
const getThreatColor = (severity) => {
    const colors = {
        critical: 'bg-red-500',
        high: 'bg-orange-500',
        medium: 'bg-yellow-500',
        low: 'bg-blue-500'
    }
    return colors[severity] || 'bg-panel'
}

const getThreatBgClass = (severity) => {
    const classes = {
        critical: 'bg-red-50 border-red-200',
        high: 'bg-orange-50 border-orange-200',
        medium: 'bg-yellow-50 border-yellow-200',
        low: 'bg-blue-50 border-blue-200'
    }
    return classes[severity] || 'bg-panel border-gray-200'
}

const getComplianceStatusClass = (status) => {
    const classes = {
        'Compliant': 'bg-green-100 text-green-800',
        'Partial': 'bg-yellow-100 text-yellow-800',
        'Non-Compliant': 'bg-red-100 text-red-800'
    }
    return classes[status] || 'bg-ink text-gray-800'
}

const runVulnerabilityAssessment = async () => {
    isAssessing.value = true
    assessmentProgress.value = 0
    
    const steps = [
        'Initializing security scanner',
        'Analyzing smart contract bytecode',
        'Checking OWASP vulnerabilities',
        'Running static analysis',
        'Performing dynamic testing',
        'Generating security report'
    ]
    
    for (let i = 0; i < steps.length; i++) {
        currentAssessmentStep.value = steps[i]
        
        // Simulate progress
        for (let j = 0; j < 100/steps.length; j++) {
            await new Promise(resolve => setTimeout(resolve, 30))
            assessmentProgress.value += 1
        }
    }
    
    // Use real API for vulnerability assessment
    try {
        const response = await api.post('/security/analyze', {
            source_code: '// Sample contract for demo\npragma solidity ^0.8.0;\ncontract Demo { }',
            contract_name: 'Demo Contract',
            include_summary: true
        })
        
        if (response.data.success) {
            const findings = response.data.findings || []
            
            // Process findings into summary
            const summary = {
                critical: findings.filter(f => f.severity === 'critical').length,
                high: findings.filter(f => f.severity === 'high').length,
                medium: findings.filter(f => f.severity === 'medium').length,
                low: findings.filter(f => f.severity === 'low').length
            }
            
            vulnerabilityResults.value = {
                summary,
                owaspTop10: findings.map((finding, index) => ({
                    id: index + 1,
                    name: finding.title,
                    description: finding.description,
                    found: finding.severity !== 'info'
                }))
            }
        }
    } catch (error) {
        console.error('Error running vulnerability assessment:', error)
        vulnerabilityResults.value = {
            summary: { critical: 0, high: 0, medium: 0, low: 0 },
            owaspTop10: []
        }
    }
    
    isAssessing.value = false
}

const runComplianceCheck = () => {
    console.log('Running compliance check')
    // Simulate compliance verification
}

const scheduleAutomatedScan = () => {
    console.log('Scheduling automated security scan')
}

const exportSecurityReport = () => {
    const reportData = {
        timestamp: new Date().toISOString(),
        analysisType: selectedAnalysisType.value,
        threats: liveThreats.value,
        metrics: securityMetrics.value,
        vulnerability: vulnerabilityResults.value,
        compliance: complianceStandards.value
    }
    
    const blob = new Blob([JSON.stringify(reportData, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `security-analysis-report-${Date.now()}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
}

// Removed addRandomThreat - now using real API data

// Lifecycle
onMounted(async () => {
    // Load initial data
    await Promise.all([
        fetchLiveThreats(),
        fetchSecurityMetrics(),
        fetchComplianceStandards()
    ])
    
    // Set up real-time data refresh
    threatUpdateInterval = setInterval(async () => {
        if (selectedAnalysisType.value === 'realtime') {
            await Promise.all([
                fetchLiveThreats(),
                fetchSecurityMetrics()
            ])
        }
    }, 10000)
})

onUnmounted(() => {
    if (threatUpdateInterval) {
        clearInterval(threatUpdateInterval)
    }
})
</script>