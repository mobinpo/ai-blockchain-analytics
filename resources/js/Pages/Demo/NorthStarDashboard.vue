<template>
    <Head title="AI Blockchain Analytics - Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        🚀 AI Blockchain Analytics Platform
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">
                        Real-time security analysis, sentiment monitoring, and blockchain intelligence
                    </p>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Live Status Indicator - only when there's actual activity -->
                    <div v-if="dashboardData && dashboardData.totals.activeAnalyses > 0" class="flex items-center space-x-2">
                        <div class="h-3 w-3 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-sm font-medium text-green-700">{{ dashboardData.totals.activeAnalyses }} Active</span>
                    </div>
                    <div v-else class="flex items-center space-x-2">
                        <div class="h-3 w-3 bg-gray-400 rounded-full"></div>
                        <span class="text-sm font-medium text-gray-600">System Idle</span>
                    </div>
                    
                    <!-- Refresh Controls -->
                    <button 
                        @click="loadDashboardData"
                        :disabled="loading"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="-ml-0.5 mr-2 h-4 w-4" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh Data
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Error Message Display -->
                <div v-if="error" class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">{{ error }}</p>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button @click="error = null" class="inline-flex bg-red-50 rounded-md p-1.5 text-red-500 hover:bg-red-100">
                                    <span class="sr-only">Dismiss</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div v-if="loading" class="mb-6 bg-blue-50 border border-blue-200 rounded-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">Loading dashboard data...</p>
                        </div>
                    </div>
                </div>

                <!-- Hero Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Projects -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total Projects</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ dashboardData?.totals?.projects || 0 }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Analyses -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div v-if="dashboardData?.totals?.activeAnalyses > 0" class="h-6 w-6 bg-green-400 rounded-full animate-pulse"></div>
                                    <div v-else class="h-6 w-6 bg-gray-400 rounded-full"></div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Active Analyses</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ dashboardData?.totals?.activeAnalyses || 0 }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Critical Findings -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Critical Findings</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ dashboardData?.totals?.criticalFindings || 0 }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Avg Sentiment -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-6 w-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Avg Sentiment</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ dashboardData?.sentiment?.avg !== null ? `${dashboardData.sentiment.avg}%` : '–' }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Real-time Monitoring -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Real-time Monitoring</h3>
                            <div v-if="dashboardData?.realtime" class="space-y-4">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Active Analyses</span>
                                    <span class="text-sm font-medium">{{ dashboardData.realtime.active }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Analyses Today</span>
                                    <span class="text-sm font-medium">{{ dashboardData.realtime.analysesToday }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Avg Time</span>
                                    <span class="text-sm font-medium">{{ dashboardData.realtime.avgTimeSec }}s</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Findings Today</span>
                                    <span class="text-sm font-medium">{{ dashboardData.realtime.findingsToday }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">System Load</span>
                                    <span class="text-sm font-medium">{{ dashboardData.realtime.systemLoadPct }}%</span>
                                </div>
                            </div>
                            <div v-else class="text-center text-gray-500 py-8">
                                No monitoring data available
                            </div>
                        </div>
                    </div>

                    <!-- API Usage -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">API Usage</h3>
                            <div v-if="dashboardData?.apiUsage" class="space-y-4">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Total Requests</span>
                                    <span class="text-sm font-medium">{{ dashboardData.apiUsage.totalRequests }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Success Rate</span>
                                    <span class="text-sm font-medium">{{ dashboardData.apiUsage.successRate !== null ? `${dashboardData.apiUsage.successRate}%` : '–' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Avg Response</span>
                                    <span class="text-sm font-medium">{{ dashboardData.apiUsage.avgResponseMs !== null ? `${dashboardData.apiUsage.avgResponseMs}ms` : '–' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Rate Limit</span>
                                    <span class="text-sm font-medium">{{ dashboardData.apiUsage.rateLimitPct !== null ? `${dashboardData.apiUsage.rateLimitPct}%` : '–' }}</span>
                                </div>
                            </div>
                            <div v-else class="text-center text-gray-500 py-8">
                                No API usage data available
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Network Status -->
                <div v-if="dashboardData?.networkStatus?.items?.length > 0" class="bg-white shadow rounded-lg mb-8">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Network Status</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div v-for="network in dashboardData.networkStatus.items" :key="network.chain" class="border rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium text-gray-900">{{ network.label }}</h4>
                                    <span :class="[
                                        'px-2 py-1 text-xs rounded-full',
                                        network.status === 'Active' ? 'bg-green-100 text-green-800' : 
                                        network.status === 'Down' ? 'bg-red-100 text-red-800' : 
                                        'bg-gray-100 text-gray-800'
                                    ]">
                                        {{ network.status }}
                                    </span>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">
                                    <div>Latency: {{ network.latencyMs !== null ? `${network.latencyMs}ms` : '–' }}</div>
                                    <div>Requests: {{ network.requests !== null ? network.requests : '–' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Projects -->
                <div v-if="dashboardData?.recentProjects?.length > 0" class="bg-white shadow rounded-lg mb-8">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Projects</h3>
                        <div class="space-y-3">
                            <div v-for="project in dashboardData.recentProjects" :key="project.id" class="border rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <h4 class="font-medium text-gray-900">{{ project.name }}</h4>
                                    <div class="text-sm text-gray-600">{{ project.findings }} findings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critical Findings Table -->
                <div v-if="dashboardData?.criticalTable?.length > 0" class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Critical Security Findings</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CVSS</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Impact</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="finding in dashboardData.criticalTable" :key="finding.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ finding.contract }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                {{ finding.severity }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ finding.cvss || '–' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ finding.impact }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Empty State when no data -->
                <div v-if="!loading && !error && (!dashboardData || (dashboardData.totals.projects === 0 && dashboardData.totals.activeAnalyses === 0))" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No data available</h3>
                    <p class="mt-1 text-sm text-gray-500">Start by creating a project or running an analysis.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { getDashboardSummary } from '@/composables/useDashboard'

// Reactive state
const dashboardData = ref(null)
const loading = ref(false)
const error = ref(null)
let pollingInterval = null

// Load dashboard data from real API
const loadDashboardData = async () => {
    loading.value = true
    error.value = null
    
    try {
        dashboardData.value = await getDashboardSummary()
    } catch (err) {
        error.value = 'Failed to load dashboard data. Please try again.'
        console.error('Dashboard error:', err)
    } finally {
        loading.value = false
    }
}

// Start polling only if there are active analyses
const startPolling = () => {
    if (pollingInterval) return
    
    pollingInterval = setInterval(async () => {
        if (dashboardData.value?.totals?.activeAnalyses > 0) {
            // Poll every 30 seconds when active
            await loadDashboardData()
        } else {
            // Stop polling when idle
            stopPolling()
        }
    }, 30000)
}

const stopPolling = () => {
    if (pollingInterval) {
        clearInterval(pollingInterval)
        pollingInterval = null
    }
}

onMounted(async () => {
    await loadDashboardData()
    
    // Start polling if there are active analyses
    if (dashboardData.value?.totals?.activeAnalyses > 0) {
        startPolling()
    }
})

onUnmounted(() => {
    stopPolling()
})
</script>