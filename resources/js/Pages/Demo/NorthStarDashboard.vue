<template>
    <Head title="AI Blockchain Analytics - Live Demo" />

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
                    <!-- Live Status Indicator -->
                    <div class="flex items-center space-x-2">
                        <div class="h-3 w-3 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-sm font-medium text-green-700">Live Demo</span>
                    </div>
                    
                    <!-- Demo Controls -->
                    <button 
                        @click="refreshAllData"
                        :disabled="isLoading"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-panel disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh Demo
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
                                    </path>
                                </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div v-if="isLoading" class="mb-6 bg-blue-50 border border-blue-200 rounded-md p-4">
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
                    <LiveStatCard
                        title="Contracts Analyzed"
                        :value="stats.contractsAnalyzed"
                        :change="stats.contractsChange"
                        icon="shield-check"
                        color="blue"
                        suffix="today"
                    />
                    
                    <LiveStatCard
                        title="Security Issues Found"
                        :value="stats.securityIssues"
                        :change="stats.securityChange"
                        icon="exclamation-triangle"
                        color="red"
                        suffix="critical"
                    />
                    
                    <LiveStatCard
                        title="Sentiment Score"
                        :value="stats.sentimentScore"
                        :change="stats.sentimentChange"
                        icon="heart"
                        color="green"
                        suffix="avg"
                        :is-percentage="false"
                        :decimal-places="2"
                    />
                    
                    <LiveStatCard
                        title="API Requests"
                        :value="stats.apiRequests"
                        :change="stats.apiChange"
                        icon="chart-bar"
                        color="purple"
                        suffix="/hour"
                    />
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- Real-time Security Analysis -->
                    <div class="lg:col-span-2">
                        <RealTimeSecurityPanel 
                            :recent-analyses="recentAnalyses"
                            @analyze-contract="handleContractAnalysis"
                        />
                    </div>
                    
                    <!-- Live Threat Feed -->
                    <div>
                        <LiveThreatFeed 
                            :threats="liveThreatFeed"
                            :is-live="isLive"
                        />
                    </div>
                </div>

                <!-- Market Intelligence Section -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
                    <!-- Sentiment Analysis Chart -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    📊 Market Sentiment vs Price
                                </h3>
                                <select 
                                    v-model="selectedCoin" 
                                    @change="updateSentimentChart"
                                    class="text-sm border border-gray-300 rounded px-3 py-1"
                                >
                                    <option value="bitcoin">Bitcoin</option>
                                    <option value="ethereum">Ethereum</option>
                                    <option value="cardano">Cardano</option>
                                    <option value="solana">Solana</option>
                                </select>
                            </div>
                        </div>
                        <div class="p-6">
                            <DashboardSentimentWidget 
                                :default-coin="selectedCoin"
                                :timeframe="7"
                                ref="sentimentWidget"
                            />
                        </div>
                    </div>
                    
                    <!-- AI Analysis Engine Status -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">
                                🤖 AI Analysis Engine
                            </h3>
                        </div>
                        <div class="p-6">
                            <AIEngineStatus 
                                :processing-queue="processingQueue"
                                :performance-metrics="performanceMetrics"
                            />
                        </div>
                    </div>
                </div>

                <!-- Platform Capabilities Showcase -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
                    <!-- Blockchain Explorer -->
                    <CapabilityShowcase
                        title="Multi-Chain Explorer"
                        description="Real-time blockchain data across 15+ networks"
                        icon="globe-alt"
                        color="blue"
                        :metrics="explorerMetrics"
                        @demo-click="demoBlockchainExplorer"
                    />
                    
                    <!-- Smart Contract Auditor -->
                    <CapabilityShowcase
                        title="AI Security Auditor"
                        description="OWASP-compliant vulnerability detection"
                        icon="shield-check"
                        color="green"
                        :metrics="auditorMetrics"
                        @demo-click="demoSecurityAudit"
                    />
                    
                    <!-- Social Sentiment -->
                    <CapabilityShowcase
                        title="Social Intelligence"
                        description="Real-time sentiment from 5M+ sources"
                        icon="chat-alt-2"
                        color="purple"
                        :metrics="socialMetrics"
                        @demo-click="demoSentimentAnalysis"
                    />
                </div>

                <!-- Recent Activity Stream -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">
                                ⚡ Live Activity Stream
                            </h3>
                            <div class="flex items-center space-x-2">
                                <div class="h-2 w-2 bg-green-400 rounded-full animate-pulse"></div>
                                <span class="text-sm text-gray-600">Auto-updating</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <ActivityStream 
                            :activities="recentActivities"
                            :is-live="isLive"
                        />
                    </div>
                </div>

                <!-- Interactive Demo Triggers -->
                <div class="mt-8 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg overflow-hidden">
                    <div class="p-8 text-white">
                        <div class="text-center">
                            <h3 class="text-2xl font-bold mb-4">
                                🎯 Try Our Platform Live
                            </h3>
                            <p class="text-indigo-100 mb-6 max-w-2xl mx-auto">
                                Experience the power of AI-driven blockchain security and market intelligence. 
                                Click any demo below to see real results.
                            </p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-4xl mx-auto">
                                <DemoTriggerButton
                                    title="Analyze Smart Contract"
                                    description="Upload or paste Solidity code"
                                    icon="code"
                                    @click="triggerContractDemo"
                                />
                                
                                <DemoTriggerButton
                                    title="Market Sentiment"
                                    description="Real-time social analysis"
                                    icon="trending-up"
                                    @click="triggerSentimentDemo"
                                />
                                
                                <DemoTriggerButton
                                    title="Blockchain Explorer"
                                    description="Multi-chain data insights"
                                    icon="search"
                                    @click="triggerExplorerDemo"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demo Modal for Interactive Features -->
        <DemoModal 
            v-if="showDemoModal"
            :demo-type="activeDemoType"
            @close="closeDemoModal"
        />
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DashboardSentimentWidget from '@/Components/Examples/DashboardSentimentWidget.vue'
import api from '@/services/api'

// Import demo components (to be created)
import LiveStatCard from '@/Components/Demo/LiveStatCard.vue'
import RealTimeSecurityPanel from '@/Components/Demo/RealTimeSecurityPanel.vue'
import LiveThreatFeed from '@/Components/Demo/LiveThreatFeed.vue'
import AIEngineStatus from '@/Components/Demo/AIEngineStatus.vue'
import CapabilityShowcase from '@/Components/Demo/CapabilityShowcase.vue'
import ActivityStream from '@/Components/Demo/ActivityStream.vue'
import DemoTriggerButton from '@/Components/Demo/DemoTriggerButton.vue'
import DemoModal from '@/Components/Demo/DemoModal.vue'

// Reactive state
const isLive = ref(true)
const selectedCoin = ref('bitcoin')
const showDemoModal = ref(false)
const activeDemoType = ref('')
const sentimentWidget = ref(null)
const isLoading = ref(false)
const error = ref(null)

// Error handling utility
const handleApiError = (err, context = 'API call') => {
    console.error(`${context} failed:`, err)
    error.value = `Failed to load ${context.toLowerCase()}. Please try again.`
    setTimeout(() => {
        error.value = null
    }, 5000)
}

// Live statistics from API
const stats = reactive({
    contractsAnalyzed: 0,
    contractsChange: 0,
    securityIssues: 0,
    securityChange: 0,
    sentimentScore: 0,
    sentimentChange: 0,
    apiRequests: 0,
    apiChange: 0
})

// Load dashboard stats from API
const loadDashboardStats = async () => {
    try {
        const response = await api.get('/dashboard/stats')
        const data = response.data
        
        stats.contractsAnalyzed = data.contractsAnalyzed || 0
        stats.contractsChange = data.contractsChange || 0
        stats.securityIssues = data.securityIssues || 0
        stats.securityChange = data.securityChange || 0
        stats.sentimentScore = data.sentimentScore || 0
        stats.sentimentChange = data.sentimentChange || 0
        stats.apiRequests = data.apiRequests || 0
        stats.apiChange = data.apiChange || 0
    } catch (err) {
        handleApiError(err, 'dashboard statistics')
    }
}

// Recent analyses data from API
const recentAnalyses = ref([])

// Load recent analyses from API
const loadRecentAnalyses = async () => {
    try {
        const response = await api.get('/analyses/active')
        recentAnalyses.value = response.data || []
    } catch (err) {
        handleApiError(err, 'recent analyses')
        recentAnalyses.value = []
    }
}

// Live threat feed data from API
const liveThreatFeed = ref([])

// Load security findings from API
const loadSecurityFindings = async () => {
    try {
        const response = await api.get('/security/findings')
        liveThreatFeed.value = response.data || []
    } catch (err) {
        handleApiError(err, 'security findings')
        liveThreatFeed.value = []
    }
}

// Processing queue and performance metrics from API
const processingQueue = ref({
    active_jobs: 0,
    pending_jobs: 0,
    completed_today: 0,
    average_processing_time: 0
})

const performanceMetrics = ref({
    accuracy: 0,
    uptime: 0,
    response_time: 0,
    throughput: 0
})

// Load analysis metrics from API
const loadAnalysisMetrics = async () => {
    try {
        const response = await api.get('/analyses/metrics')
        const data = response.data
        
        processingQueue.value = {
            active_jobs: data.active_jobs || 0,
            pending_jobs: data.pending_jobs || 0,
            completed_today: data.completed_today || 0,
            average_processing_time: data.average_processing_time || 0
        }
        
        performanceMetrics.value = {
            accuracy: data.accuracy || 0,
            uptime: data.uptime || 0,
            response_time: data.response_time || 0,
            throughput: data.throughput || 0
        }
    } catch (err) {
        handleApiError(err, 'analysis metrics')
    }
}

// Capability metrics from API
const explorerMetrics = ref([])
const auditorMetrics = ref([])
const socialMetrics = ref([])

// Load network and capability metrics
const loadCapabilityMetrics = async () => {
    try {
        // Load network status for explorer metrics
        const networkResponse = await api.get('/network/status')
        const networkData = networkResponse.data
        explorerMetrics.value = [
            `${networkData.networks_count || 15}+ Networks`,
            '24/7 Monitoring',
            `${networkData.uptime || 99.9}% Uptime`
        ]
        
        // Load AI engine status for auditor metrics
        const aiResponse = await api.get('/ai/components/status')
        const aiData = aiResponse.data
        auditorMetrics.value = [
            `${aiData.accuracy || 98.7}% Accuracy`,
            `${aiData.avg_speed || 4.2}s Avg Speed`,
            'OWASP Compliant'
        ]
        
        // Load sentiment summary for social metrics
        const sentimentResponse = await api.get('/sentiment/current-summary')
        const sentimentData = sentimentResponse.data
        socialMetrics.value = [
            `${sentimentData.sources_count || '5M'}+ Sources`,
            'Real-time',
            `${sentimentData.languages_count || 50}+ Languages`
        ]
    } catch (err) {
        handleApiError(err, 'capability metrics')
        // Set fallback values
        explorerMetrics.value = ['15+ Networks', '24/7 Monitoring', '99.9% Uptime']
        auditorMetrics.value = ['98.7% Accuracy', '4.2s Avg Speed', 'OWASP Compliant']
        socialMetrics.value = ['5M+ Sources', 'Real-time', '50+ Languages']
    }
}

// Recent activities data from API
const recentActivities = ref([])

// Load recent activities from API
const loadRecentActivities = async () => {
    try {
        const response = await api.get('/dashboard/critical-findings')
        recentActivities.value = response.data || []
    } catch (err) {
        handleApiError(err, 'recent activities')
        recentActivities.value = []
    }
}

// Load all data from APIs
const loadAllData = async () => {
    isLoading.value = true
    try {
        await Promise.all([
            loadDashboardStats(),
            loadRecentAnalyses(),
            loadSecurityFindings(),
            loadAnalysisMetrics(),
            loadRecentActivities(),
            loadCapabilityMetrics()
        ])
        console.log('All data loaded from API')
    } catch (err) {
        handleApiError(err, 'data loading')
    } finally {
        isLoading.value = false
    }
}

// Refresh all data
const refreshAllData = async () => {
    console.log('Refreshing all data from API...')
    await loadAllData()
}

const updateSentimentChart = () => {
    if (sentimentWidget.value) {
        sentimentWidget.value.selectedCoin = selectedCoin.value
    }
}

const handleContractAnalysis = async (contractData) => {
    console.log('Analyzing contract:', contractData)
    try {
        // Start new analysis via API
        const response = await api.post('/contracts/analyze', {
            contract_address: contractData.address,
            network: contractData.network || 'ethereum'
        })
        
        // Refresh analyses to show the new one
        await loadRecentAnalyses()
    } catch (err) {
        handleApiError(err, 'contract analysis')
    }
}

const demoBlockchainExplorer = () => {
    activeDemoType.value = 'explorer'
    showDemoModal.value = true
}

const demoSecurityAudit = () => {
    activeDemoType.value = 'security'
    showDemoModal.value = true
}

const demoSentimentAnalysis = () => {
    activeDemoType.value = 'sentiment'
    showDemoModal.value = true
}

const triggerContractDemo = () => {
    activeDemoType.value = 'contract_upload'
    showDemoModal.value = true
}

const triggerSentimentDemo = () => {
    activeDemoType.value = 'sentiment_live'
    showDemoModal.value = true
}

const triggerExplorerDemo = () => {
    activeDemoType.value = 'explorer_search'
    showDemoModal.value = true
}

const closeDemoModal = () => {
    showDemoModal.value = false
    activeDemoType.value = ''
}

// Auto-refresh with real API polling
let refreshInterval

onMounted(async () => {
    // Load initial data
    await loadAllData()
    
    // Start auto-refresh with real API polling
    refreshInterval = setInterval(async () => {
        if (isLive.value && !isLoading.value) {
            try {
                // Refresh specific data that changes frequently
                await Promise.all([
                    loadRecentAnalyses(),
                    loadSecurityFindings(),
                    loadRecentActivities()
                ])
            } catch (err) {
                console.warn('Auto-refresh failed:', err)
            }
        }
    }, 30000) // Update every 30 seconds for live data
})

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval)
    }
})
</script>

<style scoped>
.max-w-8xl {
    max-width: 88rem;
}

/* Smooth animations for demo */
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

/* Gradient background for hero section */
.gradient-bg {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>