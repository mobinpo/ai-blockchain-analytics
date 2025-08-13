<template>
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">
                    🛡️ Real-time Security Analysis
                </h3>
                <div class="flex items-center space-x-2">
                    <div class="h-2 w-2 bg-green-400 rounded-full animate-pulse"></div>
                    <span class="text-sm text-green-600 font-medium">Scanning</span>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <!-- Quick Upload Section -->
            <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-medium text-blue-900">Analyze Smart Contract</h4>
                        <p class="text-sm text-blue-700 mt-1">Upload Solidity code for instant security analysis</p>
                    </div>
                    <button 
                        @click="triggerAnalysis"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Upload & Scan
                    </button>
                </div>
            </div>

            <!-- Recent Analyses -->
            <div class="space-y-4">
                <h4 class="font-medium text-gray-900 mb-3">Recent Analyses</h4>
                
                <div v-for="analysis in recentAnalyses" :key="analysis.id" class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <!-- Risk Level Badge -->
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getRiskBadgeClass(analysis.risk_level)">
                                    {{ analysis.risk_level?.toUpperCase() || 'ANALYZING' }}
                                </span>
                            </div>
                            
                            <!-- Contract Info -->
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ analysis.contract_address }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ formatTimestamp(analysis.timestamp) }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Issues Found -->
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900">
                                {{ analysis.issues_found }} issues
                            </p>
                            <div class="flex items-center space-x-2 mt-1">
                                <div v-if="analysis.status === 'analyzing'" class="flex items-center">
                                    <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
                                    <span class="text-xs text-blue-600 ml-1">Analyzing...</span>
                                </div>
                                <button v-else class="text-xs text-blue-600 hover:text-blue-800">
                                    View Report
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar for Analyzing -->
                    <div v-if="analysis.status === 'analyzing'" class="mt-3">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full animate-pulse" :style="{ width: getAnalysisProgress(analysis) + '%' }"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Demo Analysis Button -->
                <div class="mt-6 p-4 border-2 border-dashed border-gray-300 rounded-lg text-center hover:border-blue-400 transition-colors cursor-pointer" @click="runDemoAnalysis">
                    <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <p class="text-sm font-medium text-gray-700">Run Demo Analysis</p>
                    <p class="text-xs text-gray-500">Click to see our AI in action</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    recentAnalyses: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['analyze-contract'])

// Methods
const triggerAnalysis = () => {
    emit('analyze-contract', {
        address: '0x' + Math.random().toString(16).substr(2, 8) + '...' + Math.random().toString(16).substr(2, 4),
        type: 'upload'
    })
}

const runDemoAnalysis = () => {
    // Trigger a demo analysis with a known vulnerable contract
    emit('analyze-contract', {
        address: '0xDEMO...VULN',
        type: 'demo',
        vulnerabilities: ['reentrancy', 'overflow', 'access_control']
    })
}

const getRiskBadgeClass = (riskLevel) => {
    const classes = {
        'high': 'bg-red-100 text-red-800',
        'medium': 'bg-yellow-100 text-yellow-800',
        'low': 'bg-green-100 text-green-800',
        'analyzing': 'bg-blue-100 text-blue-800'
    }
    return classes[riskLevel] || classes.analyzing
}

const formatTimestamp = (timestamp) => {
    const now = new Date()
    const diff = now - new Date(timestamp)
    const minutes = Math.floor(diff / 60000)
    
    if (minutes < 1) return 'Just now'
    if (minutes < 60) return `${minutes}m ago`
    
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    
    const days = Math.floor(hours / 24)
    return `${days}d ago`
}

const getAnalysisProgress = (analysis) => {
    // Simulate progress based on time since creation
    const elapsed = Date.now() - new Date(analysis.timestamp).getTime()
    const progress = Math.min(90, (elapsed / 10000) * 100) // 10 seconds = 90%
    return Math.floor(progress)
}
</script>