<template>
    <div class="sentiment-price-timeline-chart">
        <div class="bg-white dark:bg-panel rounded-lg shadow-lg p-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ title }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ subtitle }}
                    </p>
                </div>
                
                <!-- Controls -->
                <div class="flex flex-wrap items-center gap-2 mt-4 sm:mt-0">
                    <select
                        v-model="selectedCoin"
                        @change="loadData"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                        :disabled="loading"
                    >
                        <option v-for="coin in availableCoins" :key="coin.id" :value="coin.id">
                            {{ coin.name }} ({{ coin.symbol }})
                        </option>
                    </select>
                    
                    <select
                        v-model="selectedTimeframe"
                        @change="loadData"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                        :disabled="loading"
                    >
                        <option value="7">7 Days</option>
                        <option value="30">30 Days</option>
                        <option value="90">90 Days</option>
                        <option value="365">1 Year</option>
                    </select>
                    
                    <button
                        @click="toggleDataSource"
                        class="px-3 py-2 text-sm font-medium rounded-md transition-colors"
                        :class="useCoingecko ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-ink text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                        :disabled="loading"
                    >
                        <div class="flex items-center space-x-1">
                            <svg v-if="useCoingecko" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ useCoingecko ? 'Live Data' : 'Demo Data' }}</span>
                        </div>
                    </button>
                    
                    <PdfExportButton
                        v-if="!loading && chartData && stats"
                        component-route="pdf.sentiment-price-chart"
                        :data="pdfExportData"
                        :has-charts="true"
                        :default-options="{
                            orientation: 'landscape',
                            title: chartTitle,
                            filename: `sentiment-price-${selectedCoin}-${selectedTimeframe}d-${formatDateForFilename(new Date())}`
                        }"
                        variant="outline"
                        size="sm"
                        @export-started="onPdfExportStarted"
                        @export-completed="onPdfExportCompleted"
                        @export-failed="onPdfExportFailed"
                    />
                    
                    <button
                        @click="loadData"
                        :disabled="loading"
                        class="px-3 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-md transition-colors flex items-center space-x-1"
                    >
                        <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>{{ loading ? 'Loading...' : 'Refresh' }}</span>
                    </button>
                </div>
            </div>

            <!-- Error Display -->
            <div v-if="error" class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="text-sm text-red-700 dark:text-red-400 font-medium">{{ error.message }}</p>
                        <p v-if="error.details" class="text-xs text-red-600 dark:text-red-500 mt-1">{{ error.details }}</p>
                    </div>
                </div>
            </div>

            <!-- Chart Container -->
            <div class="relative mb-6">
                <canvas
                    ref="chartCanvas"
                    :height="height"
                    class="w-full rounded-lg"
                    :class="{ 'opacity-50': loading }"
                ></canvas>
                
                <!-- Loading Overlay -->
                <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-panel/50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <svg class="w-8 h-8 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-gray-600 dark:text-gray-300">{{ loadingMessage }}</span>
                    </div>
                </div>
                
                <!-- Empty State -->
                <div v-if="!loading && !error && (!chartData?.sentiment?.length && !chartData?.price?.length)" class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <p class="text-lg font-medium">No data available</p>
                        <p class="text-sm">Try selecting a different coin or timeframe</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Summary -->
            <div v-if="stats && !loading" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-blue-900/20 dark:to-indigo-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="text-xs text-blue-600 dark:text-blue-400 uppercase tracking-wide font-medium">Avg Sentiment</div>
                    <div class="text-lg font-bold mt-1" :class="getSentimentColor(stats.avg_sentiment)">
                        {{ formatSentiment(stats.avg_sentiment) }}
                    </div>
                    <div class="text-xs text-blue-500 dark:text-blue-400 mt-1">
                        {{ getSentimentLabel(stats.avg_sentiment) }}
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-green-50 to-emerald-100 dark:from-green-900/20 dark:to-emerald-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                    <div class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wide font-medium">Price Change</div>
                    <div class="text-lg font-bold mt-1" :class="getPriceChangeColor(stats.price_change)">
                        {{ formatPriceChange(stats.price_change) }}
                    </div>
                    <div class="text-xs text-green-500 dark:text-green-400 mt-1">
                        {{ selectedTimeframe }} day period
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-purple-50 to-violet-100 dark:from-purple-900/20 dark:to-violet-900/20 p-4 rounded-lg border border-purple-200 dark:border-purple-800">
                    <div class="text-xs text-purple-600 dark:text-purple-400 uppercase tracking-wide font-medium">Correlation</div>
                    <div class="text-lg font-bold mt-1" :class="getCorrelationColor(stats.correlation)">
                        {{ formatCorrelation(stats.correlation) }}
                    </div>
                    <div class="text-xs text-purple-500 dark:text-purple-400 mt-1">
                        {{ getCorrelationStrength(stats.correlation) }}
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-gray-50 to-slate-100 dark:from-gray-900/20 dark:to-slate-900/20 p-4 rounded-lg border border-gray-200 dark:border-gray-800">
                    <div class="text-xs text-gray-600 dark:text-gray-400 uppercase tracking-wide font-medium">Data Points</div>
                    <div class="text-lg font-bold text-gray-900 dark:text-white mt-1">
                        {{ stats.data_points }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ useCoingecko ? 'Live data' : 'Demo data' }}
                    </div>
                </div>
            </div>

            <!-- Data Source Info -->
            <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-center">
                <div v-if="metadata">
                    Data from {{ metadata.start_date }} to {{ metadata.end_date }}
                    <span v-if="useCoingecko" class="ml-2">• Powered by CoinGecko API</span>
                    <span v-else class="ml-2">• Demo Mode</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue'
import PdfExportButton from './PdfExportButton.vue'

// Props
const props = defineProps({
    title: {
        type: String,
        default: 'Sentiment vs Price Timeline'
    },
    subtitle: {
        type: String,
        default: 'Real-time correlation between social sentiment and cryptocurrency prices'
    },
    height: {
        type: Number,
        default: 400
    },
    defaultCoin: {
        type: String,
        default: 'bitcoin'
    },
    defaultTimeframe: {
        type: String,
        default: '30'
    },
    autoRefresh: {
        type: Boolean,
        default: false
    },
    refreshInterval: {
        type: Number,
        default: 300000 // 5 minutes
    },
    theme: {
        type: String,
        default: 'auto' // 'light', 'dark', 'auto'
    }
})

// Emits
const emit = defineEmits(['data-loaded', 'error', 'coin-changed', 'timeframe-changed', 'pdf-export-started', 'pdf-export-completed', 'pdf-export-failed'])

// Reactive data
const chartCanvas = ref(null)
const chart = ref(null)
const loading = ref(false)
const error = ref(null)
const selectedCoin = ref(props.defaultCoin)
const selectedTimeframe = ref(props.defaultTimeframe)
const useCoingecko = ref(true)
const stats = ref(null)
const chartData = ref(null)
const metadata = ref(null)
const refreshTimer = ref(null)
const loadingMessage = ref('Loading chart data...')

// Available coins (loaded from API)
const availableCoins = ref([
    { id: 'bitcoin', name: 'Bitcoin', symbol: 'BTC' },
    { id: 'ethereum', name: 'Ethereum', symbol: 'ETH' },
    { id: 'cardano', name: 'Cardano', symbol: 'ADA' },
    { id: 'solana', name: 'Solana', symbol: 'SOL' },
    { id: 'polygon', name: 'Polygon', symbol: 'MATIC' },
])

// Chart.js will be imported dynamically
let Chart = null

// Dark mode detection
const isDarkMode = computed(() => {
    if (props.theme === 'dark') return true
    if (props.theme === 'light') return false
    return document.documentElement.classList.contains('dark')
})

// Chart title for PDF
const chartTitle = computed(() => {
    const coinName = getSelectedCoinName()
    return `${coinName} Sentiment vs Price Analysis`
})

// PDF export data
const pdfExportData = computed(() => {
    if (!chartData.value || !stats.value) return {}
    
    return {
        chartData: chartData.value,
        stats: stats.value,
        metadata: metadata.value,
        coinSymbol: getSelectedCoinSymbol(),
        title: chartTitle.value,
        showDataTable: true,
        pdf_mode: true
    }
})

// Initialize Chart.js
const initChart = async () => {
    try {
        // Dynamic import of Chart.js
        const chartModule = await import('chart.js/auto')
        Chart = chartModule.Chart
        
        // Register date adapter
        await import('chartjs-adapter-date-fns')
        
    } catch (err) {
        console.warn('Chart.js not available:', err.message)
        error.value = { 
            message: 'Chart library not available', 
            details: 'Please install Chart.js to display charts' 
        }
    }
}

// Create Chart.js chart
const createChart = async (data) => {
    if (!Chart || !chartCanvas.value || !data) {
        return
    }
    
    // Destroy existing chart
    if (chart.value) {
        chart.value.destroy()
    }
    
    const ctx = chartCanvas.value.getContext('2d')
    const colors = getThemeColors()
    
    chart.value = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Sentiment Score',
                    data: data.sentiment || [],
                    borderColor: colors.sentiment.border,
                    backgroundColor: colors.sentiment.background,
                    borderWidth: 2,
                    fill: false,
                    yAxisID: 'sentiment',
                    tension: 0.4,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: colors.sentiment.border,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                },
                {
                    label: `${getSelectedCoinSymbol()} Price`,
                    data: data.price || [],
                    borderColor: colors.price.border,
                    backgroundColor: colors.price.background,
                    borderWidth: 2,
                    fill: false,
                    yAxisID: 'price',
                    tension: 0.4,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: colors.price.border,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        color: colors.text
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: colors.tooltip.background,
                    titleColor: colors.tooltip.title,
                    bodyColor: colors.tooltip.body,
                    borderColor: colors.tooltip.border,
                    borderWidth: 1,
                    callbacks: {
                        title: function(context) {
                            return new Date(context[0].parsed.x).toLocaleDateString()
                        },
                        afterLabel: function(context) {
                            if (context.datasetIndex === 0) {
                                return `${getSentimentLabel(context.parsed.y)}`
                            }
                            return null
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: selectedTimeframe.value <= 7 ? 'day' : selectedTimeframe.value <= 30 ? 'day' : 'week',
                        displayFormats: {
                            day: 'MMM dd',
                            week: 'MMM dd'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Date',
                        color: colors.text
                    },
                    ticks: {
                        color: colors.text
                    },
                    grid: {
                        color: colors.grid
                    }
                },
                sentiment: {
                    type: 'linear',
                    position: 'left',
                    min: -1,
                    max: 1,
                    title: {
                        display: true,
                        text: 'Sentiment Score',
                        color: colors.text
                    },
                    ticks: {
                        color: colors.text,
                        callback: function(value) {
                            return formatSentiment(value)
                        }
                    },
                    grid: {
                        color: colors.grid
                    }
                },
                price: {
                    type: 'linear',
                    position: 'right',
                    title: {
                        display: true,
                        text: `Price (${(data.currency || 'USD').toUpperCase()})`,
                        color: colors.text
                    },
                    ticks: {
                        color: colors.text,
                        callback: function(value) {
                            return new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: data.currency || 'USD',
                                minimumFractionDigits: value < 1 ? 4 : 2,
                                maximumFractionDigits: value < 1 ? 4 : 2
                            }).format(value)
                        }
                    },
                    grid: {
                        drawOnChartArea: false,
                        color: colors.grid
                    },
                }
            }
        }
    })

    emit('data-loaded', { data, stats: stats.value, metadata: metadata.value })
}

// Get theme colors
const getThemeColors = () => {
    const dark = isDarkMode.value
    
    return {
        sentiment: {
            border: '#3b82f6', // blue-500
            background: dark ? 'rgba(59, 130, 246, 0.1)' : 'rgba(59, 130, 246, 0.1)'
        },
        price: {
            border: '#10b981', // emerald-500
            background: dark ? 'rgba(16, 185, 129, 0.1)' : 'rgba(16, 185, 129, 0.1)'
        },
        text: dark ? '#f3f4f6' : '#374151',
        grid: dark ? '#374151' : '#e5e7eb',
        tooltip: {
            background: dark ? '#1f2937' : '#ffffff',
            title: dark ? '#f9fafb' : '#111827',
            body: dark ? '#d1d5db' : '#6b7280',
            border: dark ? '#4b5563' : '#d1d5db'
        }
    }
}

// Load available coins
const loadAvailableCoins = async () => {
    try {
        const response = await fetch('/api/sentiment-price-timeline/coins')
        if (response.ok) {
            const result = await response.json()
            if (result.success) {
                availableCoins.value = result.coins
            }
        }
    } catch (err) {
        console.warn('Failed to load available coins:', err.message)
        // Keep default coins
    }
}

// Load data from API
const loadData = async () => {
    loading.value = true
    error.value = null
    loadingMessage.value = useCoingecko.value ? 'Fetching live data...' : 'Generating demo data...'
    
    try {
        const endpoint = useCoingecko.value ? '/api/sentiment-price-timeline' : '/api/sentiment-price-timeline/demo'
        const params = new URLSearchParams({
            coin: selectedCoin.value,
            days: selectedTimeframe.value,
            currency: 'usd'
        })
        
        const response = await fetch(`${endpoint}?${params}`)
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`)
        }
        
        const result = await response.json()
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to load data')
        }
        
        // Update state
        chartData.value = result.data
        stats.value = result.stats
        metadata.value = result.metadata
        
        // Create chart
        await nextTick()
        await createChart(result.data)
        
    } catch (err) {
        console.error('Error loading chart data:', err)
        error.value = { 
            message: err.message, 
            details: useCoingecko.value ? 'Check your internet connection and try again' : null 
        }
        emit('error', err)
        
    } finally {
        loading.value = false
    }
}

// Toggle between live and demo data
const toggleDataSource = () => {
    useCoingecko.value = !useCoingecko.value
    loadData()
}

// Get selected coin symbol
const getSelectedCoinSymbol = () => {
    const coin = availableCoins.value.find(c => c.id === selectedCoin.value)
    return coin ? coin.symbol : selectedCoin.value.toUpperCase()
}

// Get selected coin name
const getSelectedCoinName = () => {
    const coin = availableCoins.value.find(c => c.id === selectedCoin.value)
    return coin ? coin.name : selectedCoin.value.charAt(0).toUpperCase() + selectedCoin.value.slice(1)
}

// Format date for filename
const formatDateForFilename = (date) => {
    return date.toISOString().split('T')[0]
}

// PDF Export event handlers
const onPdfExportStarted = () => {
    emit('pdf-export-started')
}

const onPdfExportCompleted = (result) => {
    emit('pdf-export-completed', result)
}

const onPdfExportFailed = (error) => {
    emit('pdf-export-failed', error)
}

// Utility functions
const getSentimentColor = (sentiment) => {
    if (sentiment > 0.1) return 'text-green-600 dark:text-green-400'
    if (sentiment < -0.1) return 'text-red-600 dark:text-red-400'
    return 'text-gray-600 dark:text-gray-400'
}

const getPriceChangeColor = (change) => {
    if (change > 0) return 'text-green-600 dark:text-green-400'
    if (change < 0) return 'text-red-600 dark:text-red-400'
    return 'text-gray-600 dark:text-gray-400'
}

const getCorrelationColor = (correlation) => {
    if (correlation === null) return 'text-gray-600 dark:text-gray-400'
    const abs = Math.abs(correlation)
    if (abs > 0.7) return 'text-blue-600 dark:text-blue-400'
    if (abs > 0.4) return 'text-yellow-600 dark:text-yellow-400'
    return 'text-gray-600 dark:text-gray-400'
}

const formatSentiment = (value) => {
    if (value == null) return 'N/A'
    return (value > 0 ? '+' : '') + (value * 100).toFixed(1) + '%'
}

const formatPriceChange = (value) => {
    if (value == null) return 'N/A'
    return (value > 0 ? '+' : '') + value.toFixed(2) + '%'
}

const formatCorrelation = (value) => {
    if (value == null) return 'N/A'
    return value.toFixed(3)
}

const getSentimentLabel = (score) => {
    if (score > 0.25) return 'Very Positive'
    if (score > 0.1) return 'Positive'
    if (score > -0.1) return 'Neutral'
    if (score > -0.25) return 'Negative'
    return 'Very Negative'
}

const getCorrelationStrength = (correlation) => {
    if (correlation === null) return 'No data'
    const abs = Math.abs(correlation)
    if (abs > 0.8) return 'Very Strong'
    if (abs > 0.6) return 'Strong'
    if (abs > 0.4) return 'Moderate'
    if (abs > 0.2) return 'Weak'
    return 'Very Weak'
}

// Setup auto-refresh
const setupAutoRefresh = () => {
    if (props.autoRefresh && props.refreshInterval > 0) {
        refreshTimer.value = setInterval(() => {
            loadData()
        }, props.refreshInterval)
    }
}

const clearAutoRefresh = () => {
    if (refreshTimer.value) {
        clearInterval(refreshTimer.value)
        refreshTimer.value = null
    }
}

// Lifecycle
onMounted(async () => {
    await initChart()
    await loadAvailableCoins()
    await loadData()
    setupAutoRefresh()
})

onUnmounted(() => {
    if (chart.value) {
        chart.value.destroy()
    }
    clearAutoRefresh()
})

// Watch for changes
watch(selectedCoin, (newCoin, oldCoin) => {
    if (newCoin !== oldCoin) {
        emit('coin-changed', newCoin)
        loadData()
    }
})

watch(selectedTimeframe, (newTimeframe, oldTimeframe) => {
    if (newTimeframe !== oldTimeframe) {
        emit('timeframe-changed', newTimeframe)
        loadData()
    }
})

// Watch for dark mode changes
watch(isDarkMode, async () => {
    if (chart.value && chartData.value) {
        await nextTick()
        await createChart(chartData.value)
    }
})
</script>

<style scoped>
.sentiment-price-timeline-chart {
    @apply w-full;
}

.sentiment-price-timeline-chart canvas {
    @apply max-w-full h-auto;
}

/* Custom scrollbar for select elements */
select {
    @apply appearance-none bg-no-repeat bg-right;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Loading animation */
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

/* Gradient backgrounds for stats cards */
.bg-gradient-to-br {
    background-image: linear-gradient(to bottom right, var(--tw-gradient-from), var(--tw-gradient-to));
}
</style>