<template>
    <AppLayout title="Sentiment vs Price Timeline Demo">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg mb-8">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                    📈 Sentiment vs Price Timeline
                                </h1>
                                <p class="mt-2 text-lg text-gray-600 dark:text-gray-400">
                                    Interactive chart showing cryptocurrency sentiment correlation with price movements
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Data Source</div>
                                    <div class="text-lg font-semibold" :class="dataSource === 'live' ? 'text-green-600' : 'text-blue-600'">
                                        {{ dataSource === 'live' ? '🟢 Live Data' : '🔵 Demo Data' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Feature Highlights -->
                        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-blue-900 dark:text-blue-100">Real-time Data</div>
                                        <div class="text-xs text-blue-600 dark:text-blue-400">Coingecko API</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-green-900 dark:text-green-100">Correlation Analysis</div>
                                        <div class="text-xs text-green-600 dark:text-green-400">Statistical insights</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m-9 0h10m-9 0a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2m-9 0V4"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-purple-900 dark:text-purple-100">Interactive Controls</div>
                                        <div class="text-xs text-purple-600 dark:text-purple-400">Multiple timeframes</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-orange-900 dark:text-orange-100">Auto Refresh</div>
                                        <div class="text-xs text-orange-600 dark:text-orange-400">Live updates</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Chart -->
                <div class="mb-8">
                    <EnhancedSentimentPriceTimeline
                        ref="mainChart"
                        :initial-coin="selectedCoin"
                        :initial-timeframe="selectedTimeframe"
                        :height="500"
                        :show-volume="showVolume"
                        :auto-refresh="autoRefresh"
                        :refresh-interval="300000"
                        @data-loaded="onDataLoaded"
                        @error="onChartError"
                        @coin-changed="onCoinChanged"
                        @timeframe-changed="onTimeframeChanged"
                    />
                </div>

                <!-- Demo Controls -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Chart Settings -->
                    <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            ⚙️ Chart Settings
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="showVolume"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    >
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Show Volume Data</span>
                                </label>
                            </div>
                            
                            <div>
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="autoRefresh"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    >
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Auto Refresh (5min)</span>
                                </label>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Data Source
                                </label>
                                <div class="flex gap-2">
                                    <button
                                        @click="switchToLiveData"
                                        :class="dataSource === 'live' 
                                            ? 'bg-green-500 text-white' 
                                            : 'bg-ink text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                    >
                                        🟢 Live Data
                                    </button>
                                    <button
                                        @click="switchToDemoData"
                                        :class="dataSource === 'demo' 
                                            ? 'bg-blue-500 text-white' 
                                            : 'bg-ink text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                    >
                                        🔵 Demo Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart Statistics -->
                    <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            📊 Current Analysis
                        </h3>
                        
                        <div v-if="chartStats" class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Correlation:</span>
                                <span class="font-medium" :class="getCorrelationColor(chartStats.correlation)">
                                    {{ chartStats.correlation?.toFixed(3) || 'N/A' }}
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Avg Sentiment:</span>
                                <span class="font-medium" :class="getSentimentColor(chartStats.avgSentiment)">
                                    {{ chartStats.avgSentiment?.toFixed(3) || 'N/A' }}
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Price Change:</span>
                                <span class="font-medium" :class="chartStats.priceChange >= 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ chartStats.priceChange?.toFixed(2) || 'N/A' }}%
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Data Points:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ chartStats.dataPoints || 0 }}
                                </span>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-4">
                            <div class="text-gray-400 dark:text-gray-500">
                                Load chart data to see statistics
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Examples -->
                <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        🔌 API Usage Examples
                    </h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-2">Live Data Endpoint</h4>
                            <div class="bg-panel dark:bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <code class="text-sm text-gray-800 dark:text-gray-200">
                                    GET /api/sentiment-price-timeline<br>
                                    ?coin={{ selectedCoin }}<br>
                                    &days={{ selectedTimeframe }}<br>
                                    &include_volume={{ showVolume }}
                                </code>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-2">Demo Data Endpoint</h4>
                            <div class="bg-panel dark:bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <code class="text-sm text-gray-800 dark:text-gray-200">
                                    GET /api/sentiment-price-timeline/demo<br>
                                    ?coin={{ selectedCoin }}<br>
                                    &days={{ selectedTimeframe }}<br>
                                    &include_volume={{ showVolume }}
                                </code>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button
                            @click="copyApiExample"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-sm"
                        >
                            📋 Copy Current API Call
                        </button>
                        <span v-if="copied" class="ml-2 text-sm text-green-600">Copied!</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import EnhancedSentimentPriceTimeline from '@/Components/Charts/EnhancedSentimentPriceTimeline.vue'

// Reactive state
const mainChart = ref(null)
const selectedCoin = ref('bitcoin')
const selectedTimeframe = ref(30)
const showVolume = ref(false)
const autoRefresh = ref(false)
const dataSource = ref('demo') // 'live' or 'demo'
const chartStats = ref(null)
const copied = ref(false)

// Methods
const onDataLoaded = (data) => {
    chartStats.value = {
        correlation: data.correlation,
        avgSentiment: data.sentiment?.length ? 
            data.sentiment.reduce((sum, item) => sum + item.sentiment, 0) / data.sentiment.length : null,
        priceChange: data.price?.length >= 2 ? 
            ((data.price[data.price.length - 1].price - data.price[0].price) / data.price[0].price) * 100 : null,
        dataPoints: Math.min(data.sentiment?.length || 0, data.price?.length || 0)
    }
}

const onChartError = (error) => {
    console.error('Chart error:', error)
    // Could show a toast notification here
}

const onCoinChanged = (newCoin) => {
    selectedCoin.value = newCoin
}

const onTimeframeChanged = (newTimeframe) => {
    selectedTimeframe.value = newTimeframe
}

const switchToLiveData = () => {
    dataSource.value = 'live'
    if (mainChart.value) {
        mainChart.value.useCoingecko = true
        mainChart.value.refreshData()
    }
}

const switchToDemoData = () => {
    dataSource.value = 'demo'
    if (mainChart.value) {
        mainChart.value.useCoingecko = false
        mainChart.value.refreshData()
    }
}

const getCorrelationColor = (correlation) => {
    if (correlation === null) return 'text-gray-500'
    const abs = Math.abs(correlation)
    if (abs >= 0.8) return 'text-purple-600'
    if (abs >= 0.6) return 'text-blue-600'
    if (abs >= 0.4) return 'text-green-600'
    if (abs >= 0.2) return 'text-yellow-600'
    return 'text-gray-600'
}

const getSentimentColor = (sentiment) => {
    if (sentiment === null) return 'text-gray-500'
    if (sentiment >= 0.5) return 'text-green-600'
    if (sentiment >= 0.1) return 'text-green-500'
    if (sentiment >= -0.1) return 'text-gray-600'
    if (sentiment >= -0.5) return 'text-red-500'
    return 'text-red-600'
}

const copyApiExample = () => {
    const endpoint = dataSource.value === 'live' 
        ? '/api/sentiment-price-timeline'
        : '/api/sentiment-price-timeline/demo'
    
    const params = new URLSearchParams({
        coin: selectedCoin.value,
        days: selectedTimeframe.value.toString(),
        include_volume: showVolume.value.toString()
    })
    
    const fullUrl = `${window.location.origin}${endpoint}?${params.toString()}`
    
    navigator.clipboard.writeText(fullUrl).then(() => {
        copied.value = true
        setTimeout(() => {
            copied.value = false
        }, 2000)
    })
}

// Watch for data source changes
watch(dataSource, (newSource) => {
    if (mainChart.value) {
        if (newSource === 'live') {
            switchToLiveData()
        } else {
            switchToDemoData()
        }
    }
})

// Initialize with demo data
dataSource.value = 'demo'
</script>

<style scoped>
/* Custom scrollbar for code blocks */
code {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

/* Smooth transitions */
.transition-colors {
    transition-property: color, background-color, border-color;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>