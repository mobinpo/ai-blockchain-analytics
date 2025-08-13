<template>
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">
                    ⚠️ Live Threat Feed
                </h3>
                <div v-if="isLive" class="flex items-center space-x-2">
                    <div class="h-2 w-2 bg-red-400 rounded-full animate-pulse"></div>
                    <span class="text-sm text-red-600 font-medium">Live</span>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <!-- Threat Summary -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">{{ threats.filter(t => t.severity === 'critical').length }}</div>
                    <div class="text-xs text-gray-600">Critical</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ threats.filter(t => t.severity === 'high').length }}</div>
                    <div class="text-xs text-gray-600">High</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ threats.filter(t => t.severity === 'medium').length }}</div>
                    <div class="text-xs text-gray-600">Medium</div>
                </div>
            </div>

            <!-- Threat List -->
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <div v-for="threat in sortedThreats" :key="threat.id" class="flex items-start space-x-3 p-3 rounded-lg border" :class="getThreatBorderClass(threat.severity)">
                    <!-- Severity Icon -->
                    <div class="flex-shrink-0 mt-0.5">
                        <div class="h-3 w-3 rounded-full" :class="getSeverityColor(threat.severity)"></div>
                    </div>
                    
                    <!-- Threat Details -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">
                                {{ getThreatTitle(threat.type) }}
                            </p>
                            <span class="text-xs text-gray-500">
                                {{ formatTimestamp(threat.timestamp) }}
                            </span>
                        </div>
                        
                        <div class="mt-1 flex items-center space-x-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="getSeverityBadgeClass(threat.severity)">
                                {{ threat.severity.toUpperCase() }}
                            </span>
                            <span class="text-xs text-gray-500">
                                {{ threat.contract }}
                            </span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-ink text-gray-700">
                                {{ getChainLabel(threat.chain) }}
                            </span>
                        </div>
                        
                        <!-- Threat Description -->
                        <p class="mt-2 text-xs text-gray-600">
                            {{ getThreatDescription(threat.type) }}
                        </p>
                        
                        <!-- Action Buttons -->
                        <div class="mt-3 flex space-x-2">
                            <button class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                Investigate
                            </button>
                            <button class="text-xs text-gray-500 hover:text-gray-700">
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Empty State -->
                <div v-if="threats.length === 0" class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Active Threats</h3>
                    <p class="mt-1 text-xs text-gray-500">All systems secure</p>
                </div>
            </div>
            
            <!-- View All Link -->
            <div v-if="threats.length > 0" class="mt-4 text-center">
                <button class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    View All Threats ({{ threats.length }})
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    threats: {
        type: Array,
        default: () => []
    },
    isLive: {
        type: Boolean,
        default: false
    }
})

// Computed
const sortedThreats = computed(() => {
    return [...props.threats].sort((a, b) => {
        // Sort by severity first, then by timestamp
        const severityOrder = { critical: 3, high: 2, medium: 1, low: 0 }
        const severityDiff = severityOrder[b.severity] - severityOrder[a.severity]
        
        if (severityDiff !== 0) return severityDiff
        return new Date(b.timestamp) - new Date(a.timestamp)
    })
})

// Methods
const getSeverityColor = (severity) => {
    const colors = {
        critical: 'bg-red-500',
        high: 'bg-orange-500', 
        medium: 'bg-yellow-500',
        low: 'bg-blue-500'
    }
    return colors[severity] || colors.medium
}

const getSeverityBadgeClass = (severity) => {
    const classes = {
        critical: 'bg-red-100 text-red-800',
        high: 'bg-orange-100 text-orange-800',
        medium: 'bg-yellow-100 text-yellow-800',
        low: 'bg-blue-100 text-blue-800'
    }
    return classes[severity] || classes.medium
}

const getThreatBorderClass = (severity) => {
    const classes = {
        critical: 'border-red-200 bg-red-50',
        high: 'border-orange-200 bg-orange-50',
        medium: 'border-yellow-200 bg-yellow-50',
        low: 'border-blue-200 bg-blue-50'
    }
    return classes[severity] || classes.medium
}

const getThreatTitle = (type) => {
    const titles = {
        reentrancy: 'Reentrancy Attack Detected',
        flash_loan: 'Flash Loan Exploit',
        overflow: 'Integer Overflow',
        access_control: 'Access Control Violation',
        front_running: 'Front-running Attack',
        price_manipulation: 'Price Manipulation'
    }
    return titles[type] || 'Security Threat Detected'
}

const getThreatDescription = (type) => {
    const descriptions = {
        reentrancy: 'Potential reentrancy vulnerability allowing recursive calls',
        flash_loan: 'Suspicious flash loan transaction pattern detected',
        overflow: 'Mathematical operation may cause integer overflow',
        access_control: 'Unauthorized access attempt to protected function',
        front_running: 'Transaction ordering manipulation detected',
        price_manipulation: 'Abnormal price movement suggesting manipulation'
    }
    return descriptions[type] || 'Potential security vulnerability detected'
}

const getChainLabel = (chain) => {
    const labels = {
        ethereum: 'ETH',
        bsc: 'BSC',
        polygon: 'MATIC',
        avalanche: 'AVAX',
        fantom: 'FTM',
        arbitrum: 'ARB'
    }
    return labels[chain] || chain.toUpperCase()
}

const formatTimestamp = (timestamp) => {
    const now = new Date()
    const diff = now - new Date(timestamp)
    const minutes = Math.floor(diff / 60000)
    
    if (minutes < 1) return 'Now'
    if (minutes < 60) return `${minutes}m`
    
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h`
    
    const days = Math.floor(hours / 24)
    return `${days}d`
}
</script>