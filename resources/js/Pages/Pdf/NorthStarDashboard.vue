<template>
    <div class="pdf-dashboard">
        <Head title="AI Blockchain Analytics - Dashboard Report" />

        <!-- PDF Header -->
        <div class="pdf-header mb-8 border-b border-gray-200 pb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        🚀 AI Blockchain Analytics Platform
                    </h1>
                    <p class="text-gray-600 text-lg">
                        Security Analysis Dashboard Report
                    </p>
                    <p class="text-sm text-gray-500 mt-2">
                        Generated on {{ formatDate(new Date()) }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="inline-flex items-center space-x-2 bg-green-50 px-4 py-2 rounded-lg">
                        <div class="h-3 w-3 bg-green-400 rounded-full"></div>
                        <span class="text-sm font-medium text-green-700">Platform Status: Active</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics Grid -->
        <div class="grid grid-cols-4 gap-6 mb-8">
            <div class="bg-white border border-gray-200 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-blue-600 mb-2">
                    {{ data.metrics?.contracts_analyzed || 1247 }}
                </div>
                <div class="text-sm text-gray-600">
                    Contracts Analyzed
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-yellow-600 mb-2">
                    {{ data.metrics?.vulnerabilities_found || 89 }}
                </div>
                <div class="text-sm text-gray-600">
                    Vulnerabilities Found
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-red-600 mb-2">
                    {{ data.metrics?.active_threats || 12 }}
                </div>
                <div class="text-sm text-gray-600">
                    Active Threats
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-green-600 mb-2">
                    {{ data.metrics?.security_score || 94.7 }}%
                </div>
                <div class="text-sm text-gray-600">
                    Security Score
                </div>
            </div>
        </div>

        <!-- Recent Analyses -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Security Analyses</h2>
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-panel">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Contract Address
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Risk Level
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Timestamp
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="analysis in recentAnalyses" :key="analysis.contract">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                {{ analysis.contract }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="getStatusClass(analysis.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                    {{ analysis.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="getRiskClass(analysis.risk_level)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                    {{ analysis.risk_level }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ formatDate(analysis.timestamp) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Threat Intelligence Feed -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Threat Intelligence Feed</h2>
            <div class="space-y-4">
                <div v-for="threat in threatFeed" :key="threat.type + threat.timestamp" 
                     class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span :class="getThreatSeverityClass(threat.severity)" 
                                      class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                    {{ threat.severity.toUpperCase() }}
                                </span>
                                <span class="text-sm font-medium text-gray-900">
                                    {{ formatThreatType(threat.type) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">
                                Target: {{ threat.target }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500">
                                {{ formatDate(threat.timestamp) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PDF Footer -->
        <div class="pdf-footer mt-12 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
            <p>
                AI Blockchain Analytics Platform - Confidential Report
            </p>
            <p class="mt-1">
                Generated {{ formatDate(new Date()) }} | Page 1 of 1
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'

// Props
const props = defineProps({
    data: {
        type: Object,
        default: () => ({})
    },
    pdf_mode: {
        type: Boolean,
        default: false
    },
    demo_mode: {
        type: Boolean,
        default: true
    },
    options: {
        type: Object,
        default: () => ({})
    }
})

// Computed data with fallbacks
const recentAnalyses = computed(() => {
    return props.data.recent_analyses || [
        {
            contract: '0x1234...5678',
            status: 'completed',
            risk_level: 'medium',
            timestamp: new Date(Date.now() - 15 * 60 * 1000).toISOString()
        },
        {
            contract: '0xabcd...efgh',
            status: 'processing',
            risk_level: 'high',
            timestamp: new Date(Date.now() - 5 * 60 * 1000).toISOString()
        }
    ]
})

const threatFeed = computed(() => {
    return props.data.threat_feed || [
        {
            type: 'flash_loan_attack',
            severity: 'high',
            target: 'DeFi Protocol X',
            timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString()
        },
        {
            type: 'reentrancy_vulnerability',
            severity: 'medium',
            target: 'Smart Contract Y',
            timestamp: new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString()
        }
    ]
})

// Helper functions
function formatDate(date) {
    if (!date) return ''
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

function getStatusClass(status) {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800'
        case 'processing':
            return 'bg-blue-100 text-blue-800'
        case 'failed':
            return 'bg-red-100 text-red-800'
        default:
            return 'bg-ink text-gray-800'
    }
}

function getRiskClass(risk) {
    switch (risk) {
        case 'high':
            return 'bg-red-100 text-red-800'
        case 'medium':
            return 'bg-yellow-100 text-yellow-800'
        case 'low':
            return 'bg-green-100 text-green-800'
        default:
            return 'bg-ink text-gray-800'
    }
}

function getThreatSeverityClass(severity) {
    switch (severity) {
        case 'critical':
            return 'bg-red-100 text-red-800'
        case 'high':
            return 'bg-orange-100 text-orange-800'
        case 'medium':
            return 'bg-yellow-100 text-yellow-800'
        case 'low':
            return 'bg-blue-100 text-blue-800'
        default:
            return 'bg-ink text-gray-800'
    }
}

function formatThreatType(type) {
    return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}
</script>

<style scoped>
.pdf-dashboard {
    max-width: 8.5in;
    margin: 0 auto;
    padding: 1in;
    background: white;
    color: black;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    line-height: 1.5;
}

.pdf-header {
    page-break-inside: avoid;
}

.pdf-footer {
    page-break-inside: avoid;
}

/* PDF-specific styles */
@media print {
    .pdf-dashboard {
        padding: 0.5in;
        margin: 0;
        max-width: none;
    }
    
    .no-print {
        display: none !important;
    }
    
    /* Ensure proper page breaks */
    .page-break-before {
        page-break-before: always;
    }
    
    .page-break-after {
        page-break-after: always;
    }
    
    .page-break-inside-avoid {
        page-break-inside: avoid;
    }
}

/* Enhanced table styling for PDF */
table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    border: 1px solid #e5e7eb;
    padding: 8px 12px;
    text-align: left;
}

th {
    background-color: #f9fafb;
    font-weight: 600;
}

/* Status badges */
.status-badge, .risk-badge, .severity-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}
</style>