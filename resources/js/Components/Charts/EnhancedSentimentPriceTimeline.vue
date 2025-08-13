<template>
    <div class="enhanced-sentiment-price-timeline">
        <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <!-- Header with Controls -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ chartTitle }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Real-time sentiment analysis correlated with price movements
                        </p>
                    </div>
                    
                    <!-- Control Panel -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Coin Selector -->
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Coin:</label>
                            <select
                                v-model="selectedCoin"
                                @change="onCoinChange"
                                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option v-for="coin in availableCoins" :key="coin.id" :value="coin.id">
                                    {{ coin.name }} ({{ coin.symbol.toUpperCase() }})
                                </option>
                            </select>
                        </div>
                        
                        <!-- Timeframe Selector -->
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Period:</label>
                            <select
                                v-model="selectedTimeframe"
                                @change="onTimeframeChange"
                                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option v-for="timeframe in timeframes" :key="timeframe.value" :value="timeframe.value">
                                    {{ timeframe.label }}
                                </option>
                            </select>
                        </div>
                        
                        <!-- Data Source Toggle -->
                        <div class="flex items-center gap-2">
                            <button
                                @click="toggleDataSource"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200"
                                :class="useCoingecko 
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 border-2 border-green-200 dark:border-green-800' 
                                    : 'bg-ink text-gray-700 dark:bg-gray-700 dark:text-gray-300 border-2 border-gray-200 dark:border-gray-600'"
                            >
                                <span class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" :class="useCoingecko ? 'bg-green-500' : 'bg-panel'"></div>
                                    {{ useCoingecko ? 'Live Data' : 'Demo Data' }}
                                </span>
                            </button>
                        </div>
                        
                        <!-- Refresh Button -->
                        <button
                            @click="refreshData"
                            :disabled="loading"
                            class="px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 disabled:opacity-50 transition-all duration-200"
                        >
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                {{ loading ? 'Loading...' : 'Refresh' }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Chart Area -->
            <div class="p-6">
                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center h-96">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                        <p class="text-gray-600 dark:text-gray-400">Loading chart data...</p>
                    </div>
                </div>
                
                <!-- Error State -->
                <div v-else-if="error" class="flex items-center justify-center h-96">
                    <div class="text-center">
                        <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Failed to load data</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ error }}</p>
                        <button
                            @click="refreshData"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
                        >
                            Try Again
                        </button>
                    </div>
                </div>
                
                <!-- Chart -->
                <div v-else-if="hasData" class="relative">
                    <!-- Chart Canvas -->
                    <div class="relative" :style="{ height: chartHeight + 'px' }">
                        <canvas ref="chartCanvas" class="w-full h-full"></canvas>
                    </div>
                    
                    <!-- Chart Statistics -->
                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-panel dark:bg-gray-700 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Correlation</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ correlation !== null ? correlation.toFixed(3) : 'N/A' }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                {{ getCorrelationLabel(correlation) }}
                            </div>
                        </div>
                        
                        <div class="bg-panel dark:bg-gray-700 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Sentiment</div>
                            <div class="text-2xl font-bold" :class="avgSentiment >= 0 ? 'text-green-600' : 'text-red-600'">
                                {{ avgSentiment !== null ? avgSentiment.toFixed(3) : 'N/A' }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                {{ getSentimentLabel(avgSentiment) }}
                            </div>
                        </div>
                        
                        <div class="bg-panel dark:bg-gray-700 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Price Change</div>
                            <div class="text-2xl font-bold" :class="priceChange >= 0 ? 'text-green-600' : 'text-red-600'">
                                {{ priceChange !== null ? (priceChange > 0 ? '+' : '') + priceChange.toFixed(2) + '%' : 'N/A' }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                {{ selectedTimeframe }} day period
                            </div>
                        </div>
                        
                        <div class="bg-panel dark:bg-gray-700 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Data Points</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ totalDataPoints }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                Sentiment & Price pairs
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chart Legend -->
                    <div class="mt-4 flex flex-wrap justify-center gap-6 text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-0.5 bg-blue-500 rounded"></div>
                            <span class="text-gray-700 dark:text-gray-300">Sentiment Score</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-0.5 bg-green-500 rounded"></div>
                            <span class="text-gray-700 dark:text-gray-300">Price (USD)</span>
                        </div>
                        <div v-if="showVolume" class="flex items-center gap-2">
                            <div class="w-4 h-0.5 bg-purple-500 rounded opacity-60"></div>
                            <span class="text-gray-700 dark:text-gray-300">Volume</span>
                        </div>
                    </div>
                </div>
                
                <!-- No Data State -->
                <div v-else class="flex items-center justify-center h-96">
                    <div class="text-center">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No data available</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">Select a cryptocurrency and timeframe to view the analysis</p>
                        <button
                            @click="refreshData"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
                        >
                            Load Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch, onUnmounted } from 'vue'
import { Chart, registerables } from 'chart.js'
import 'chartjs-adapter-date-fns'
import axios from 'axios'

Chart.register(...registerables)

// Props
const props = defineProps({
    initialCoin: {
        type: String,
        default: 'bitcoin'
    },
    initialTimeframe: {
        type: Number,
        default: 30
    },
    height: {
        type: Number,
        default: 400
    },
    showVolume: {
        type: Boolean,
        default: false
    },
    autoRefresh: {
        type: Boolean,
        default: false
    },
    refreshInterval: {
        type: Number,
        default: 300000 // 5 minutes
    }
})

// Emits
const emit = defineEmits(['data-loaded', 'error', 'coin-changed', 'timeframe-changed'])

// Reactive state
const chartCanvas = ref(null)
const chartInstance = ref(null)
const loading = ref(false)
const error = ref('')
const useCoingecko = ref(true)
const selectedCoin = ref(props.initialCoin)
const selectedTimeframe = ref(props.initialTimeframe)
const sentimentData = ref([])
const priceData = ref([])
const correlation = ref(null)
const refreshTimer = ref(null)

// Available coins
const availableCoins = ref([
    { id: 'bitcoin', name: 'Bitcoin', symbol: 'BTC' },
    { id: 'ethereum', name: 'Ethereum', symbol: 'ETH' },
    { id: 'cardano', name: 'Cardano', symbol: 'ADA' },
    { id: 'solana', name: 'Solana', symbol: 'SOL' },
    { id: 'polygon', name: 'Polygon', symbol: 'MATIC' },
    { id: 'chainlink', name: 'Chainlink', symbol: 'LINK' },
    { id: 'polkadot', name: 'Polkadot', symbol: 'DOT' },
    { id: 'avalanche-2', name: 'Avalanche', symbol: 'AVAX' }
])

// Timeframe options
const timeframes = ref([
    { value: 7, label: '7 Days' },
    { value: 14, label: '14 Days' },
    { value: 30, label: '30 Days' },
    { value: 90, label: '3 Months' },
    { value: 180, label: '6 Months' },
    { value: 365, label: '1 Year' }
])

// Computed properties
const chartTitle = computed(() => {
    const coin = availableCoins.value.find(c => c.id === selectedCoin.value)
    return `${coin?.name || 'Cryptocurrency'} Sentiment vs Price Timeline`
})

const chartHeight = computed(() => props.height)

const hasData = computed(() => 
    sentimentData.value.length > 0 && priceData.value.length > 0
)

const avgSentiment = computed(() => {
    if (sentimentData.value.length === 0) return null
    const sum = sentimentData.value.reduce((acc, item) => acc + item.sentiment, 0)
    return sum / sentimentData.value.length
})

const priceChange = computed(() => {
    if (priceData.value.length < 2) return null
    const firstPrice = priceData.value[0]?.price || 0
    const lastPrice = priceData.value[priceData.value.length - 1]?.price || 0
    if (firstPrice === 0) return null
    return ((lastPrice - firstPrice) / firstPrice) * 100
})

const totalDataPoints = computed(() => 
    Math.min(sentimentData.value.length, priceData.value.length)
)

// Methods
const loadData = async () => {
    loading.value = true
    error.value = ''
    
    try {
        const endpoint = useCoingecko.value 
            ? '/api/sentiment-price-timeline'
            : '/api/sentiment-price-timeline/demo'
            
        const params = {
            coin: selectedCoin.value,
            days: selectedTimeframe.value,
            include_volume: props.showVolume
        }
        
        const response = await axios.get(endpoint, { params })
        
        if (response.data.success) {
            const data = response.data.data
            
            // Process sentiment data
            sentimentData.value = data.sentiment_data || []
            
            // Process price data
            priceData.value = data.price_data || []
            
            // Calculate correlation
            correlation.value = calculateCorrelation(sentimentData.value, priceData.value)
            
            // Update chart
            await nextTick()
            updateChart()
            
            emit('data-loaded', {
                sentiment: sentimentData.value,
                price: priceData.value,
                correlation: correlation.value
            })
        } else {
            throw new Error(response.data.message || 'Failed to load data')
        }
    } catch (err) {
        console.error('Error loading data:', err)
        error.value = err.response?.data?.message || err.message || 'Failed to load chart data'
        emit('error', error.value)
        
        // Load demo data as fallback
        if (useCoingecko.value) {
            console.log('Loading demo data as fallback...')
            useCoingecko.value = false
            await loadData()
        }
    } finally {
        loading.value = false
    }
}

const updateChart = () => {
    if (!chartCanvas.value || !hasData.value) return
    
    const ctx = chartCanvas.value.getContext('2d')
    
    // Destroy existing chart
    if (chartInstance.value) {
        chartInstance.value.destroy()
    }
    
    // Prepare datasets
    const datasets = [
        {
            label: 'Sentiment Score',
            data: sentimentData.value.map(item => ({
                x: new Date(item.date),
                y: item.sentiment
            })),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            yAxisID: 'sentiment',
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 5,
            borderWidth: 2
        },
        {
            label: `${selectedCoin.value.toUpperCase()} Price`,
            data: priceData.value.map(item => ({
                x: new Date(item.date),
                y: item.price
            })),
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            yAxisID: 'price',
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 5,
            borderWidth: 2
        }
    ]
    
    // Add volume dataset if enabled
    if (props.showVolume && priceData.value.some(item => item.volume)) {
        datasets.push({
            label: 'Volume',
            data: priceData.value.map(item => ({
                x: new Date(item.date),
                y: item.volume || 0
            })),
            borderColor: 'rgba(147, 51, 234, 0.6)',
            backgroundColor: 'rgba(147, 51, 234, 0.1)',
            yAxisID: 'volume',
            tension: 0.4,
            pointRadius: 1,
            pointHoverRadius: 3,
            borderWidth: 1
        })
    }
    
    // Create chart
    chartInstance.value = new Chart(ctx, {
        type: 'line',
        data: { datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: false // We have custom legend
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: 'white',
                    bodyColor: 'white',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    callbacks: {
                        title: (context) => {
                            return new Date(context[0].parsed.x).toLocaleDateString()
                        },
                        label: (context) => {
                            const label = context.dataset.label
                            const value = context.parsed.y
                            
                            if (label.includes('Price')) {
                                return `${label}: $${value.toLocaleString()}`
                            } else if (label.includes('Volume')) {
                                return `${label}: ${(value / 1000000).toFixed(2)}M`
                            } else {
                                return `${label}: ${value.toFixed(3)}`
                            }
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: selectedTimeframe.value <= 30 ? 'day' : 'week'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                sentiment: {
                    type: 'linear',
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Sentiment Score'
                    },
                    grid: {
                        color: 'rgba(59, 130, 246, 0.1)'
                    },
                    min: -1,
                    max: 1
                },
                price: {
                    type: 'linear',
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Price (USD)'
                    },
                    grid: {
                        display: false
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString()
                        }
                    }
                },
                volume: props.showVolume ? {
                    type: 'linear',
                    position: 'right',
                    display: false,
                    grid: {
                        display: false
                    }
                } : undefined
            },
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            }
        }
    })
}

const calculateCorrelation = (sentimentData, priceData) => {
    if (!sentimentData.length || !priceData.length) return null
    
    // Align data by date
    const alignedData = []
    sentimentData.forEach(sentiment => {
        const pricePoint = priceData.find(price => price.date === sentiment.date)
        if (pricePoint) {
            alignedData.push({
                sentiment: sentiment.sentiment,
                price: pricePoint.price
            })
        }
    })
    
    if (alignedData.length < 2) return null
    
    const n = alignedData.length
    const sentiments = alignedData.map(d => d.sentiment)
    const prices = alignedData.map(d => d.price)
    
    const sumSentiment = sentiments.reduce((a, b) => a + b, 0)
    const sumPrice = prices.reduce((a, b) => a + b, 0)
    const sumSentimentSq = sentiments.reduce((a, b) => a + b * b, 0)
    const sumPriceSq = prices.reduce((a, b) => a + b * b, 0)
    const sumSentimentPrice = alignedData.reduce((a, d) => a + d.sentiment * d.price, 0)
    
    const numerator = n * sumSentimentPrice - sumSentiment * sumPrice
    const denominator = Math.sqrt((n * sumSentimentSq - sumSentiment * sumSentiment) * (n * sumPriceSq - sumPrice * sumPrice))
    
    return denominator === 0 ? 0 : numerator / denominator
}

const getCorrelationLabel = (correlation) => {
    if (correlation === null) return 'No data'
    const abs = Math.abs(correlation)
    if (abs >= 0.8) return 'Very Strong'
    if (abs >= 0.6) return 'Strong'
    if (abs >= 0.4) return 'Moderate'
    if (abs >= 0.2) return 'Weak'
    return 'Very Weak'
}

const getSentimentLabel = (sentiment) => {
    if (sentiment === null) return 'No data'
    if (sentiment >= 0.5) return 'Very Positive'
    if (sentiment >= 0.1) return 'Positive'
    if (sentiment >= -0.1) return 'Neutral'
    if (sentiment >= -0.5) return 'Negative'
    return 'Very Negative'
}

const toggleDataSource = () => {
    useCoingecko.value = !useCoingecko.value
    loadData()
}

const refreshData = () => {
    loadData()
}

const onCoinChange = () => {
    emit('coin-changed', selectedCoin.value)
    loadData()
}

const onTimeframeChange = () => {
    emit('timeframe-changed', selectedTimeframe.value)
    loadData()
}

const setupAutoRefresh = () => {
    if (props.autoRefresh && props.refreshInterval > 0) {
        refreshTimer.value = setInterval(() => {
            if (!loading.value) {
                loadData()
            }
        }, props.refreshInterval)
    }
}

const clearAutoRefresh = () => {
    if (refreshTimer.value) {
        clearInterval(refreshTimer.value)
        refreshTimer.value = null
    }
}

// Lifecycle hooks
onMounted(async () => {
    await loadData()
    setupAutoRefresh()
})

onUnmounted(() => {
    clearAutoRefresh()
    if (chartInstance.value) {
        chartInstance.value.destroy()
    }
})

// Watchers
watch(() => props.autoRefresh, (newValue) => {
    if (newValue) {
        setupAutoRefresh()
    } else {
        clearAutoRefresh()
    }
})

// Expose methods for parent components
defineExpose({
    loadData,
    refreshData,
    toggleDataSource,
    selectedCoin,
    selectedTimeframe,
    useCoingecko
})
</script>

<style scoped>
.enhanced-sentiment-price-timeline {
    @apply w-full;
}

/* Custom scrollbar for select elements */
select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Animation for loading state */
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
</style>