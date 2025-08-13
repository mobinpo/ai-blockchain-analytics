<template>
    <Head title="Sentiment vs Price Analysis" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Sentiment vs Price Analysis
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Analyze the correlation between social sentiment and cryptocurrency prices
                    </p>
                </div>
                
                <!-- Header Actions -->
                <div class="flex items-center space-x-3">
                    <!-- Export Dropdown -->
                    <div class="relative" ref="exportDropdown">
                        <button 
                            @click="toggleExportDropdown"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
                        >
                            <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export
                            <svg class="ml-2 -mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div 
                            v-show="showExportDropdown"
                            class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                        >
                            <div class="py-1">
                                <button 
                                    @click="exportAsJSON"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-ink"
                                >
                                    <div class="flex items-center">
                                        <svg class="mr-3 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Export as JSON
                                    </div>
                                    <div class="text-xs text-gray-500 ml-7">Complete data with metadata</div>
                                </button>
                                
                                <button 
                                    @click="exportAsCSV"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-ink"
                                >
                                    <div class="flex items-center">
                                        <svg class="mr-3 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Export as CSV
                                    </div>
                                    <div class="text-xs text-gray-500 ml-7">Spreadsheet compatible format</div>
                                </button>
                                
                                <button 
                                    @click="exportChartImage"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-ink"
                                >
                                    <div class="flex items-center">
                                        <svg class="mr-3 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 002 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Export Chart as PNG
                                    </div>
                                    <div class="text-xs text-gray-500 ml-7">High-quality image download</div>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <Link 
                        :href="route('sentiment-analysis.index')"
                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
                    >
                        <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Dashboard
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <!-- Main Chart Component -->
                <SentimentPriceChart 
                    :initial-coin="initialCoin"
                    :initial-days="initialDays"
                    :chart-data="chartData"
                    :available-coins="availableCoins"
                    ref="chartComponent"
                />

                <!-- Additional Analysis Panels -->
                <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Correlation Insights -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                Correlation Insights
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="bg-blue-50 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-blue-900 mb-2">
                                        How to Interpret Correlation
                                    </h4>
                                    <ul class="text-sm text-blue-800 space-y-1">
                                        <li>• <strong>+0.6 to +1.0:</strong> Strong positive correlation</li>
                                        <li>• <strong>+0.3 to +0.6:</strong> Moderate positive correlation</li>
                                        <li>• <strong>-0.3 to +0.3:</strong> Weak or no correlation</li>
                                        <li>• <strong>-0.6 to -0.3:</strong> Moderate negative correlation</li>
                                        <li>• <strong>-1.0 to -0.6:</strong> Strong negative correlation</li>
                                    </ul>
                                </div>

                                <div class="bg-amber-50 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-amber-900 mb-2">
                                        Analysis Tips
                                    </h4>
                                    <ul class="text-sm text-amber-800 space-y-1">
                                        <li>• Correlation doesn't imply causation</li>
                                        <li>• Consider external market factors</li>
                                        <li>• Use multiple timeframes for confirmation</li>
                                        <li>• Monitor sentiment volume and volatility</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                Quick Actions
                            </h3>
                            
                            <div class="space-y-3">
                                <Link 
                                    :href="route('sentiment-analysis.platform', { platform: 'twitter', days: 30 })"
                                    class="block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-panel transition-colors"
                                >
                                    <div class="font-medium text-gray-900">Twitter Sentiment Analysis</div>
                                    <div class="text-sm text-gray-500">Deep dive into Twitter sentiment trends</div>
                                </Link>

                                <Link 
                                    :href="route('sentiment-analysis.platform', { platform: 'reddit', days: 30 })"
                                    class="block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-panel transition-colors"
                                >
                                    <div class="font-medium text-gray-900">Reddit Sentiment Analysis</div>
                                    <div class="text-sm text-gray-500">Analyze Reddit discussion sentiment</div>
                                </Link>

                                <Link 
                                    :href="route('sentiment-analysis.trends', { timeframe: '90d' })"
                                    class="block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-panel transition-colors"
                                >
                                    <div class="font-medium text-gray-900">Long-term Trends</div>
                                    <div class="text-sm text-gray-500">View 90-day sentiment trends</div>
                                </Link>

                                <Link 
                                    :href="route('sentiment-analysis.correlations')"
                                    class="block w-full text-left px-4 py-3 border border-gray-200 rounded-lg hover:bg-panel transition-colors"
                                >
                                    <div class="font-medium text-gray-900">Multi-Coin Correlations</div>
                                    <div class="text-sm text-gray-500">Compare multiple cryptocurrencies</div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="mt-8 bg-panel rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Understanding Sentiment vs Price Analysis
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Sentiment Score</h4>
                            <p class="text-sm text-gray-600">
                                Ranges from -1 (very negative) to +1 (very positive). Calculated using 
                                Google Cloud Natural Language API on social media posts and news articles.
                            </p>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Price Data</h4>
                            <p class="text-sm text-gray-600">
                                Real-time cryptocurrency prices from CoinGecko API. Shows daily price 
                                changes as percentages for better correlation analysis.
                            </p>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Chart Types</h4>
                            <p class="text-sm text-gray-600">
                                Line chart shows trends over time, scatter plot reveals correlation patterns, 
                                and dual-axis allows direct comparison of sentiment and price movements.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SentimentPriceChart from '@/Components/Charts/SentimentPriceChart.vue'

// Props
const props = defineProps({
    initialCoin: {
        type: String,
        default: 'bitcoin'
    },
    initialDays: {
        type: Number,
        default: 30
    },
    chartData: {
        type: Object,
        default: null
    },
    availableCoins: {
        type: Array,
        default: () => []
    }
})

// Refs
const chartComponent = ref(null)
const exportDropdown = ref(null)
const showExportDropdown = ref(false)

// Methods
const toggleExportDropdown = () => {
    showExportDropdown.value = !showExportDropdown.value
}

const closeExportDropdown = (event) => {
    if (exportDropdown.value && !exportDropdown.value.contains(event.target)) {
        showExportDropdown.value = false
    }
}

const exportAsJSON = () => {
    if (!chartComponent.value) {
        console.warn('Chart component not available for export')
        return
    }

    // Get chart data from the component
    const data = chartComponent.value.chartData
    const statistics = chartComponent.value.statistics
    
    if (!data) {
        console.warn('No chart data available for export')
        return
    }

    // Create export data structure
    const exportData = {
        metadata: {
            coin: chartComponent.value.selectedCoinName,
            coin_id: chartComponent.value.selectedCoin,
            start_date: chartComponent.value.startDate,
            end_date: chartComponent.value.endDate,
            platform: chartComponent.value.selectedPlatform,
            chart_type: chartComponent.value.chartType,
            export_date: new Date().toISOString(),
        },
        statistics: statistics,
        correlation_data: data.correlation_data || [],
        raw_data: {
            sentiment_data: data.sentiment_data || [],
            price_data: data.price_data || []
        }
    }

    // Export as JSON file
    const blob = new Blob([JSON.stringify(exportData, null, 2)], { 
        type: 'application/json' 
    })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `sentiment-price-analysis-${chartComponent.value.selectedCoin}-${chartComponent.value.startDate}-to-${chartComponent.value.endDate}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)

    showExportDropdown.value = false
    console.log('Chart data exported as JSON successfully')
}

const exportAsCSV = () => {
    if (chartComponent.value?.exportToCSV) {
        chartComponent.value.exportToCSV()
        showExportDropdown.value = false
    } else {
        console.warn('CSV export not available')
    }
}

const exportChartImage = () => {
    if (chartComponent.value?.exportChartImage) {
        chartComponent.value.exportChartImage()
        showExportDropdown.value = false
    } else {
        console.warn('Chart image export not available')
    }
}

// Route helper (assuming you have a route function available)
const route = (name, params = {}) => {
    const routes = {
        'sentiment-analysis.index': '/sentiment-analysis',
        'sentiment-analysis.platform': '/sentiment-analysis/platform',
        'sentiment-analysis.trends': '/sentiment-analysis/trends',
        'sentiment-analysis.correlations': '/sentiment-analysis/correlations'
    }
    
    let url = routes[name] || '#'
    
    if (Object.keys(params).length > 0) {
        const searchParams = new URLSearchParams(params)
        url += '?' + searchParams.toString()
    }
    
    return url
}

// Lifecycle hooks
onMounted(() => {
    document.addEventListener('click', closeExportDropdown)
})

onUnmounted(() => {
    document.removeEventListener('click', closeExportDropdown)
})
</script>

<style scoped>
/* Custom styles for the sentiment analysis page */
.max-w-8xl {
    max-width: 88rem;
}

/* Ensure proper spacing for chart container */
:deep(.sentiment-price-chart-container) {
    min-height: 600px;
}

/* Animation for loading states */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .max-w-8xl {
        max-width: 100%;
        padding-left: 1rem;
        padding-right: 1rem;
    }
}
</style>