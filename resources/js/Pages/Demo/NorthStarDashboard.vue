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
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-panel"
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

// Live statistics (would be fetched from API in real app)
const stats = reactive({
    contractsAnalyzed: 1247,
    contractsChange: 23,
    securityIssues: 89,
    securityChange: -12,
    sentimentScore: 0.743,
    sentimentChange: 0.045,
    apiRequests: 15420,
    apiChange: 8.7
})

// Demo data
const recentAnalyses = ref([
    {
        id: 1,
        contract_address: '0x1234...5678',
        risk_level: 'high',
        issues_found: 5,
        timestamp: new Date(),
        status: 'completed'
    },
    {
        id: 2,
        contract_address: '0xabcd...efgh',
        risk_level: 'medium',
        issues_found: 2,
        timestamp: new Date(Date.now() - 300000),
        status: 'analyzing'
    }
])

const liveThreatFeed = ref([
    {
        id: 1,
        type: 'reentrancy',
        severity: 'critical',
        contract: '0x9876...5432',
        timestamp: new Date(),
        chain: 'ethereum'
    },
    {
        id: 2,
        type: 'flash_loan',
        severity: 'high',
        contract: '0x4567...8901',
        timestamp: new Date(Date.now() - 180000),
        chain: 'bsc'
    }
])

const processingQueue = ref({
    active_jobs: 12,
    pending_jobs: 8,
    completed_today: 1247,
    average_processing_time: 4.2
})

const performanceMetrics = ref({
    accuracy: 98.7,
    uptime: 99.9,
    response_time: 1.2,
    throughput: 450
})

const explorerMetrics = ref(['15+ Networks', '24/7 Monitoring', '99.9% Uptime'])
const auditorMetrics = ref(['98.7% Accuracy', '4.2s Avg Speed', 'OWASP Compliant'])
const socialMetrics = ref(['5M+ Sources', 'Real-time', '50+ Languages'])

const recentActivities = ref([
    {
        id: 1,
        type: 'security_scan',
        message: 'High-risk vulnerability detected in DeFi protocol',
        timestamp: new Date(),
        severity: 'high'
    },
    {
        id: 2,
        type: 'sentiment_alert',
        message: 'Bitcoin sentiment spike detected (+15%)',
        timestamp: new Date(Date.now() - 120000),
        severity: 'info'
    },
    {
        id: 3,
        type: 'api_milestone',
        message: '1M API requests processed today',
        timestamp: new Date(Date.now() - 240000),
        severity: 'success'
    }
])

// Methods
const refreshAllData = () => {
    // Simulate data refresh
    stats.contractsAnalyzed += Math.floor(Math.random() * 10)
    stats.securityIssues += Math.floor(Math.random() * 5) - 2
    stats.sentimentScore = Math.max(0, Math.min(1, stats.sentimentScore + (Math.random() - 0.5) * 0.1))
    stats.apiRequests += Math.floor(Math.random() * 100)
    
    console.log('Demo data refreshed')
}

const updateSentimentChart = () => {
    if (sentimentWidget.value) {
        sentimentWidget.value.selectedCoin = selectedCoin.value
    }
}

const handleContractAnalysis = (contractData) => {
    console.log('Analyzing contract:', contractData)
    // Add to recent analyses
    recentAnalyses.value.unshift({
        id: Date.now(),
        contract_address: contractData.address,
        risk_level: 'analyzing',
        issues_found: 0,
        timestamp: new Date(),
        status: 'analyzing'
    })
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

// Auto-refresh simulation for live demo
let refreshInterval

onMounted(() => {
    // Start auto-refresh for live demo effect
    refreshInterval = setInterval(() => {
        if (isLive.value) {
            // Add new activity occasionally
            if (Math.random() < 0.3) {
                const activities = [
                    'New smart contract deployed on Ethereum',
                    'Price alert triggered for SOL',
                    'Suspicious transaction detected',
                    'Sentiment threshold exceeded',
                    'API rate limit adjusted'
                ]
                
                recentActivities.value.unshift({
                    id: Date.now(),
                    type: 'live_update',
                    message: activities[Math.floor(Math.random() * activities.length)],
                    timestamp: new Date(),
                    severity: 'info'
                })
                
                // Keep only last 10 activities
                if (recentActivities.value.length > 10) {
                    recentActivities.value = recentActivities.value.slice(0, 10)
                }
            }
        }
    }, 8000) // Update every 8 seconds for demo effect
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