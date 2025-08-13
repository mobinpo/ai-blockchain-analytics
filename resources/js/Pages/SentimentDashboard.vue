<template>
    <AppLayout title="Sentiment Shield - Price Analysis">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                🛡️ Sentiment Shield - Price Analysis Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8">
                        <!-- Dashboard Header -->
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    🛡️ Sentiment Shield Analytics
                                </h1>
                                <p class="mt-2 text-gray-600 dark:text-gray-400">
                                    AI-powered sentiment analysis with blockchain security correlation
                                </p>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-2">
                                    <input 
                                        type="checkbox" 
                                        id="autoRefresh" 
                                        v-model="autoRefresh"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                                    >
                                    <label for="autoRefresh" class="text-sm text-gray-700 dark:text-gray-300">
                                        Auto-refresh
                                    </label>
                                </div>
                                
                                <button 
                                    @click="refreshAllData"
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors"
                                >
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Refresh All
                                </button>
                            </div>
                        </div>

                        <!-- Main Chart Component -->
                        <div class="mb-8">
                            <SentimentPriceTimeline 
                                :auto-refresh="autoRefresh"
                                :refresh-interval="300000"
                                @data-updated="onDataUpdated"
                            />
                        </div>

                        <!-- Additional Analytics Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                            <!-- Correlation Analysis -->
                            <div class="bg-panel dark:bg-gray-900 rounded-lg p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                    Correlation Analysis
                                </h3>
                                
                                <div v-if="correlationData" class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Current Correlation</span>
                                        <span class="font-bold text-lg" :class="getCorrelationColor(correlationData.value)">
                                            {{ correlationData.value?.toFixed(3) || 'N/A' }}
                                        </span>
                                    </div>
                                    
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div 
                                            class="h-2 rounded-full transition-all duration-500"
                                            :class="getCorrelationBarColor(correlationData.value)"
                                            :style="{ width: Math.abs(correlationData.value || 0) * 100 + '%' }"
                                        ></div>
                                    </div>
                                    
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ correlationData.interpretation }}
                                    </p>
                                    
                                    <div class="grid grid-cols-2 gap-4 mt-4">
                                        <div class="text-center p-3 bg-white dark:bg-panel rounded">
                                            <div class="text-sm text-gray-500">Strength</div>
                                            <div class="font-semibold capitalize">{{ correlationData.strength }}</div>
                                        </div>
                                        <div class="text-center p-3 bg-white dark:bg-panel rounded">
                                            <div class="text-sm text-gray-500">Data Points</div>
                                            <div class="font-semibold">{{ correlationData.data_points }}</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-else class="flex items-center justify-center h-32">
                                    <div class="text-gray-500">Loading correlation data...</div>
                                </div>
                            </div>

                            <!-- Sentiment Summary -->
                            <div class="bg-panel dark:bg-gray-900 rounded-lg p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                    Sentiment Summary
                                </h3>
                                
                                <div v-if="sentimentSummary" class="space-y-4">
                                    <div class="text-center p-4 bg-white dark:bg-panel rounded-lg">
                                        <div class="text-2xl font-bold mb-2" :class="getSentimentColor(sentimentSummary.overall_sentiment?.score)">
                                            {{ sentimentSummary.overall_sentiment?.score?.toFixed(2) || 'N/A' }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ sentimentSummary.overall_sentiment?.label || 'Unknown' }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Confidence: {{ (sentimentSummary.overall_sentiment?.confidence * 100)?.toFixed(0) || 0 }}%
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="text-center p-3 bg-white dark:bg-panel rounded">
                                            <div class="text-sm text-gray-500">Trend</div>
                                            <div class="font-semibold capitalize">{{ sentimentSummary.trend }}</div>
                                        </div>
                                        <div class="text-center p-3 bg-white dark:bg-panel rounded">
                                            <div class="text-sm text-gray-500">Top Source</div>
                                            <div class="font-semibold">{{ topSource?.platform || 'N/A' }}</div>
                                        </div>
                                    </div>

                                    <!-- Sentiment Distribution -->
                                    <div v-if="sentimentSummary.sentiment_distribution" class="mt-4">
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Sentiment Distribution
                                        </div>
                                        <div class="space-y-2">
                                            <div v-for="(value, key) in sentimentSummary.sentiment_distribution" :key="key" 
                                                 class="flex items-center justify-between text-sm">
                                                <span class="capitalize">{{ key.replace('_', ' ') }}</span>
                                                <span class="font-medium">{{ value }}%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-else class="flex items-center justify-center h-32">
                                    <div class="text-gray-500">Loading sentiment data...</div>
                                </div>
                            </div>
                        </div>

                        <!-- Key Events Timeline -->
                        <div v-if="sentimentSummary?.key_events" class="bg-panel dark:bg-gray-900 rounded-lg p-6 mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                Recent Key Events
                            </h3>
                            
                            <div class="space-y-3">
                                <div v-for="event in sentimentSummary.key_events" :key="event.date" 
                                     class="flex items-start space-x-3 p-3 bg-white dark:bg-panel rounded-lg">
                                    <div class="flex-shrink-0 w-3 h-3 rounded-full mt-1" 
                                         :class="event.impact === 'positive' ? 'bg-green-500' : 'bg-red-500'">
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ event.event }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ event.date }} • Impact: {{ event.sentiment_change }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Sources -->
                        <div v-if="sentimentSummary?.top_sources" class="bg-panel dark:bg-gray-900 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                Sentiment Sources
                            </h3>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div v-for="source in sentimentSummary.top_sources" :key="source.platform"
                                     class="bg-white dark:bg-panel rounded-lg p-4 text-center">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ source.platform }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ source.mentions.toLocaleString() }} mentions
                                    </div>
                                    <div class="mt-2" :class="getSentimentColor(source.avg_sentiment)">
                                        {{ source.avg_sentiment?.toFixed(2) || '0.00' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import SentimentPriceTimeline from '@/Components/SentimentPriceTimeline.vue'
import { ref, onMounted, onUnmounted, computed } from 'vue'

export default {
    name: 'SentimentDashboard',
    components: {
        AppLayout,
        SentimentPriceTimeline
    },
    setup() {
        const autoRefresh = ref(false)
        const correlationData = ref(null)
        const sentimentSummary = ref(null)
        const loading = ref(false)
        const refreshTimer = ref(null)

        const topSource = computed(() => {
            if (!sentimentSummary.value?.top_sources) return null
            return sentimentSummary.value.top_sources.reduce((max, source) => 
                source.mentions > (max?.mentions || 0) ? source : max, null
            )
        })

        const loadCorrelationData = async (token = 'ethereum') => {
            try {
                const response = await fetch(`/api/sentiment-timeline/correlation?token=${token}`)
                if (response.ok) {
                    const data = await response.json()
                    correlationData.value = data.data
                }
            } catch (error) {
                console.error('Failed to load correlation data:', error)
            }
        }

        const loadSentimentSummary = async (token = 'ethereum', timeframe = '30d') => {
            try {
                // Convert timeframe to date range for the API
                const days = timeframe === '7d' ? 7 : timeframe === '30d' ? 30 : timeframe === '90d' ? 90 : 30
                const endDate = new Date().toISOString().split('T')[0] // Today in YYYY-MM-DD format
                const startDate = new Date(Date.now() - (days * 24 * 60 * 60 * 1000)).toISOString().split('T')[0]
                
                const response = await fetch(`/api/sentiment/summary?start_date=${startDate}&end_date=${endDate}&platforms=all&categories=all`)
                if (response.ok) {
                    const data = await response.json()
                    sentimentSummary.value = data
                } else {
                    console.error('Failed to load sentiment summary, response:', response.status, response.statusText)
                }
            } catch (error) {
                console.error('Failed to load sentiment summary:', error)
            }
        }

        const refreshAllData = async () => {
            loading.value = true
            try {
                await Promise.all([
                    loadCorrelationData(),
                    loadSentimentSummary()
                ])
            } finally {
                loading.value = false
            }
        }

        const onDataUpdated = (data) => {
            // Handle data updates from the chart component
            console.log('Chart data updated:', data)
        }

        const getCorrelationColor = (value) => {
            if (value > 0.5) return 'text-green-600'
            if (value > 0) return 'text-green-400'
            if (value > -0.5) return 'text-yellow-500'
            return 'text-red-500'
        }

        const getCorrelationBarColor = (value) => {
            if (value > 0.5) return 'bg-green-500'
            if (value > 0) return 'bg-green-400'
            if (value > -0.5) return 'bg-yellow-400'
            return 'bg-red-500'
        }

        const getSentimentColor = (value) => {
            if (value > 0.3) return 'text-green-600'
            if (value > 0) return 'text-green-400'
            if (value > -0.3) return 'text-yellow-500'
            return 'text-red-500'
        }

        const startAutoRefresh = () => {
            refreshTimer.value = setInterval(refreshAllData, 300000) // 5 minutes
        }

        const stopAutoRefresh = () => {
            if (refreshTimer.value) {
                clearInterval(refreshTimer.value)
                refreshTimer.value = null
            }
        }

        onMounted(() => {
            refreshAllData()
        })

        onUnmounted(() => {
            stopAutoRefresh()
        })

        // Watch auto-refresh toggle
        const toggleAutoRefresh = () => {
            if (autoRefresh.value) {
                startAutoRefresh()
            } else {
                stopAutoRefresh()
            }
        }

        return {
            autoRefresh,
            correlationData,
            sentimentSummary,
            loading,
            topSource,
            refreshAllData,
            onDataUpdated,
            getCorrelationColor,
            getCorrelationBarColor,
            getSentimentColor,
            toggleAutoRefresh
        }
    },
    watch: {
        autoRefresh(newVal) {
            if (newVal) {
                this.startAutoRefresh()
            } else {
                this.stopAutoRefresh()
            }
        }
    }
}
</script>

<style scoped>
/* Additional custom styles if needed */
</style>
