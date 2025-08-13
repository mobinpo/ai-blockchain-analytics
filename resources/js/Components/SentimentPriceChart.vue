<template>
    <div class="sentiment-price-chart-container">
        <!-- Header -->
        <div class="chart-header mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        {{ chartTitle }}
                    </h2>
                    <p class="text-gray-600">
                        {{ dateRange }}
                        <span v-if="correlationCoefficient !== null" class="ml-4">
                            Correlation: {{ formatCorrelation(correlationCoefficient) }}
                        </span>
                    </p>
                </div>
                
                <!-- PDF Export Button -->
                <div v-if="!pdfMode" class="flex space-x-2">
                    <PdfExportButton
                        component-route="charts.sentiment-price"
                        :data="exportData"
                        :has-charts="true"
                        :default-options="{
                            orientation: 'landscape',
                            title: chartTitle,
                            filename: `sentiment-price-${coinSymbol.toLowerCase()}-${formatDateForFilename(new Date())}`
                        }"
                        variant="outline"
                        size="sm"
                    />
                    
                    <button
                        @click="refreshData"
                        :disabled="isLoading"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-panel disabled:opacity-50"
                    >
                        <Icon name="refresh" class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center h-96">
            <div class="text-center">
                <Icon name="spinner" class="w-8 h-8 animate-spin mx-auto mb-4 text-blue-600" />
                <p class="text-gray-600">Loading chart data...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center h-96">
            <div class="text-center">
                <Icon name="x-circle" class="w-8 h-8 mx-auto mb-4 text-red-600" />
                <p class="text-red-600 mb-4">{{ error }}</p>
                <button
                    @click="refreshData"
                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
                >
                    Retry
                </button>
            </div>
        </div>

        <!-- Chart Container -->
        <div v-else class="chart-content">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border p-4">
                    <div class="text-sm text-gray-500 mb-1">Average Sentiment</div>
                    <div class="text-2xl font-bold" :class="sentimentColorClass(avgSentiment)">
                        {{ formatSentiment(avgSentiment) }}
                    </div>
                </div>
                
                <div class="bg-white rounded-lg border p-4">
                    <div class="text-sm text-gray-500 mb-1">Price Change</div>
                    <div class="text-2xl font-bold" :class="priceChangeColorClass(priceChange)">
                        {{ formatPriceChange(priceChange) }}
                    </div>
                </div>
                
                <div class="bg-white rounded-lg border p-4">
                    <div class="text-sm text-gray-500 mb-1">Volatility</div>
                    <div class="text-2xl font-bold text-gray-900">
                        {{ formatVolatility(volatility) }}
                    </div>
                </div>
                
                <div class="bg-white rounded-lg border p-4">
                    <div class="text-sm text-gray-500 mb-1">Data Points</div>
                    <div class="text-2xl font-bold text-gray-900">
                        {{ totalDataPoints }}
                    </div>
                </div>
            </div>

            <!-- Main Chart -->
            <div class="bg-white rounded-lg border p-6 mb-6">
                <canvas
                    ref="chartCanvas"
                    :id="chartId"
                    class="w-full"
                    :style="{ height: chartHeight + 'px' }"
                ></canvas>
            </div>

            <!-- Data Table (for PDF mode) -->
            <div v-if="pdfMode || showDataTable" class="bg-white rounded-lg border overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold">Raw Data</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-panel">
                            <tr>
                                <th class="px-6 py-3 text-left">Date</th>
                                <th class="px-6 py-3 text-left">Sentiment Score</th>
                                <th class="px-6 py-3 text-left">Price (USD)</th>
                                <th class="px-6 py-3 text-left">Price Change %</th>
                                <th class="px-6 py-3 text-left">Volume</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(item, index) in tableData" :key="index">
                                <td class="px-6 py-4">{{ formatDate(item.date) }}</td>
                                <td class="px-6 py-4" :class="sentimentColorClass(item.sentiment)">
                                    {{ formatSentiment(item.sentiment) }}
                                </td>
                                <td class="px-6 py-4">${{ formatPrice(item.price) }}</td>
                                <td class="px-6 py-4" :class="priceChangeColorClass(item.priceChange)">
                                    {{ formatPriceChange(item.priceChange) }}
                                </td>
                                <td class="px-6 py-4">{{ formatVolume(item.volume) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Toggle Data Table Button -->
            <div v-if="!pdfMode" class="text-center mt-4">
                <button
                    @click="showDataTable = !showDataTable"
                    class="text-blue-600 hover:text-blue-800 text-sm"
                >
                    {{ showDataTable ? 'Hide' : 'Show' }} Data Table
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { Chart, registerables } from 'chart.js'
import 'chartjs-adapter-date-fns'
import PdfExportButton from './PdfExportButton.vue'
import Icon from './Icon.vue'

Chart.register(...registerables)

const props = defineProps({
    coinSymbol: {
        type: String,
        default: 'BTC'
    },
    chartData: {
        type: Object,
        default: () => ({})
    },
    pdfMode: {
        type: Boolean,
        default: false
    },
    height: {
        type: Number,
        default: 400
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

const emit = defineEmits(['data-updated', 'chart-ready'])

// Reactive state
const chartCanvas = ref(null)
const chartInstance = ref(null)
const isLoading = ref(false)
const error = ref('')
const showDataTable = ref(false)
const sentimentData = ref([])
const priceData = ref([])
const correlationCoefficient = ref(null)

// Computed properties
const chartId = computed(() => `sentiment-price-chart-${Math.random().toString(36).substr(2, 9)}`)
const chartHeight = computed(() => props.pdfMode ? 500 : props.height)

const chartTitle = computed(() => 
    `${props.coinSymbol.toUpperCase()} Sentiment vs Price Analysis`
)

const dateRange = computed(() => {
    if (sentimentData.value.length === 0) return ''
    
    const firstDate = new Date(sentimentData.value[0].date)
    const lastDate = new Date(sentimentData.value[sentimentData.value.length - 1].date)
    
    return `${formatDate(firstDate)} - ${formatDate(lastDate)}`
})

const avgSentiment = computed(() => {
    if (sentimentData.value.length === 0) return 0
    const sum = sentimentData.value.reduce((acc, item) => acc + item.sentiment, 0)
    return sum / sentimentData.value.length
})

const priceChange = computed(() => {
    if (priceData.value.length < 2) return 0
    const first = priceData.value[0].price
    const last = priceData.value[priceData.value.length - 1].price
    return ((last - first) / first) * 100
})

const volatility = computed(() => {
    if (priceData.value.length < 2) return 0
    
    const prices = priceData.value.map(item => item.price)
    const mean = prices.reduce((a, b) => a + b) / prices.length
    const variance = prices.reduce((acc, price) => acc + Math.pow(price - mean, 2), 0) / prices.length
    
    return Math.sqrt(variance) / mean * 100
})

const totalDataPoints = computed(() => sentimentData.value.length)

const tableData = computed(() => {
    return sentimentData.value.map((sentiment, index) => {
        const price = priceData.value[index] || {}
        const prevPrice = priceData.value[index - 1]?.price || price.price
        const priceChange = prevPrice ? ((price.price - prevPrice) / prevPrice) * 100 : 0
        
        return {
            date: sentiment.date,
            sentiment: sentiment.sentiment,
            price: price.price || 0,
            priceChange,
            volume: price.volume || 0
        }
    })
})

const exportData = computed(() => ({
    coin_symbol: props.coinSymbol,
    chart_data: {
        sentiment_timeline: sentimentData.value,
        price_timeline: priceData.value
    },
    correlation_coefficient: correlationCoefficient.value,
    summary: {
        avg_sentiment: avgSentiment.value,
        price_change: priceChange.value,
        volatility: volatility.value,
        total_data_points: totalDataPoints.value
    }
}))

// Methods
const initializeChart = async () => {
    await nextTick()
    
    if (!chartCanvas.value) return

    const ctx = chartCanvas.value.getContext('2d')
    
    // Destroy existing chart
    if (chartInstance.value) {
        chartInstance.value.destroy()
    }

    chartInstance.value = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Sentiment Score',
                    data: sentimentData.value.map(item => ({
                        x: item.date,
                        y: item.sentiment
                    })),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    yAxisID: 'sentiment',
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6
                },
                {
                    label: `${props.coinSymbol.toUpperCase()} Price`,
                    data: priceData.value.map(item => ({
                        x: item.date,
                        y: item.price
                    })),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    yAxisID: 'price',
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6
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
                title: {
                    display: false
                },
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: (context) => {
                            return formatDate(new Date(context[0].parsed.x))
                        },
                        label: (context) => {
                            if (context.datasetIndex === 0) {
                                return `Sentiment: ${formatSentiment(context.parsed.y)}`
                            } else {
                                return `Price: $${formatPrice(context.parsed.y)}`
                            }
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day',
                        displayFormats: {
                            day: 'MMM dd'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                sentiment: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    min: -1,
                    max: 1,
                    title: {
                        display: true,
                        text: 'Sentiment Score'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                },
                price: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: `${props.coinSymbol.toUpperCase()} Price (USD)`
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + formatPrice(value)
                        }
                    }
                }
            },
            animation: {
                duration: props.pdfMode ? 0 : 1000
            }
        }
    })

    emit('chart-ready', chartInstance.value)
}

const loadData = async () => {
    isLoading.value = true
    error.value = ''

    try {
        // If chart data is provided as prop, use it
        if (props.chartData && Object.keys(props.chartData).length > 0) {
            sentimentData.value = props.chartData.sentiment_timeline || []
            priceData.value = props.chartData.price_timeline || []
            correlationCoefficient.value = props.chartData.correlation_coefficient || null
        } else {
            // Fetch data from API
            await fetchDataFromApi()
        }

        await initializeChart()
        emit('data-updated', { sentimentData: sentimentData.value, priceData: priceData.value })

    } catch (err) {
        console.error('Error loading chart data:', err)
        error.value = err.message || 'Failed to load chart data'
    } finally {
        isLoading.value = false
    }
}

const fetchDataFromApi = async () => {
    // Convert coinSymbol to coin_id (BTC -> bitcoin, ETH -> ethereum, etc.)
    const coinIdMap = {
        'BTC': 'bitcoin',
        'ETH': 'ethereum', 
        'ADA': 'cardano',
        'SOL': 'solana',
        'MATIC': 'polygon',
        'AVAX': 'avalanche-2',
        'DOT': 'polkadot',
        'LINK': 'chainlink',
        'UNI': 'uniswap',
        'AAVE': 'aave'
    }
    const coinId = coinIdMap[props.coinSymbol.toUpperCase()] || 'bitcoin'
    
    // Calculate date range
    const endDate = new Date()
    const startDate = new Date()
    startDate.setDate(endDate.getDate() - 30)
    
    // Get CSRF token with multiple fallback methods
    let csrfToken = null
    
    // Method 1: Try meta tag
    const metaTag = document.querySelector('meta[name="csrf-token"]')
    if (metaTag) {
        csrfToken = metaTag.getAttribute('content')
    }
    
    // Method 2: Try window._token (if set by Laravel)
    if (!csrfToken && window._token) {
        csrfToken = window._token
    }
    
    // Method 3: Try Laravel.csrfToken (if using Laravel Mix)
    if (!csrfToken && window.Laravel && window.Laravel.csrfToken) {
        csrfToken = window.Laravel.csrfToken
    }
    
    // If no CSRF token, try GET request instead of POST
    if (!csrfToken) {
        console.warn('CSRF token not found, falling back to GET request')
        
        const params = new URLSearchParams({
            coin_id: coinId,
            start_date: startDate.toISOString().split('T')[0],
            end_date: endDate.toISOString().split('T')[0],
            'platforms[]': 'all',
            'categories[]': 'all'
        })
        
        const response = await fetch(`/api/sentiment-charts/data?${params}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        
        if (!response.ok) {
            // If GET also fails, use demo data
            return await fetchDemoData()
        }
        
        const data = await response.json()
        
        if (data.success) {
            sentimentData.value = data.sentiment_data || []
            priceData.value = data.price_data || []
            correlationCoefficient.value = data.correlation || null
        } else {
            await fetchDemoData()
        }
        return
    }
    
    // Create request payload with arrays
    const requestData = {
        coin_id: coinId,
        start_date: startDate.toISOString().split('T')[0],
        end_date: endDate.toISOString().split('T')[0],
        platforms: ['all'],
        categories: ['all']
    }
    
    try {
        // Use the authenticated web route with CSRF token
        const response = await fetch('/api/sentiment-charts/data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestData)
        })
        
        if (!response.ok) {
            console.warn('Authenticated API failed, falling back to demo data')
            return await fetchDemoData()
        }
        
        const data = await response.json()
        
        if (data.success) {
            sentimentData.value = data.sentiment_data || []
            priceData.value = data.price_data || []
            correlationCoefficient.value = data.correlation || null
        } else {
            throw new Error(data.message || 'Failed to fetch chart data')
        }
    } catch (error) {
        console.warn('API request failed:', error.message)
        return await fetchDemoData()
    }
}

const fetchDemoData = async () => {
    // Generate demo data locally as fallback
    const days = 30
    const demoSentimentData = []
    const demoPriceData = []
    
    let basePrice = 50000 // Default crypto price
    
    for (let i = days - 1; i >= 0; i--) {
        const date = new Date()
        date.setDate(date.getDate() - i)
        
        // Generate correlated sentiment and price data
        const sentiment = (Math.sin(i * 0.1) * 0.3) + ((Math.random() - 0.5) * 0.4)
        const clampedSentiment = Math.max(-1, Math.min(1, sentiment))
        
        const priceChange = (clampedSentiment * 0.05) + ((Math.random() - 0.5) * 0.02)
        basePrice = basePrice * (1 + priceChange)
        
        demoSentimentData.push({
            date: date.toISOString().split('T')[0],
            sentiment: Math.round(clampedSentiment * 1000) / 1000
        })
        
        demoPriceData.push({
            date: date.toISOString().split('T')[0],
            price: Math.round(basePrice * 100) / 100
        })
    }
    
    sentimentData.value = demoSentimentData
    priceData.value = demoPriceData
    correlationCoefficient.value = 0.65 // Demo correlation
}

const refreshData = async () => {
    await loadData()
}

// Formatting methods
const formatDate = (date) => {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    }).format(new Date(date))
}

const formatDateForFilename = (date) => {
    return date.toISOString().split('T')[0]
}

const formatSentiment = (value) => {
    return (value || 0).toFixed(3)
}

const formatPrice = (value) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value || 0)
}

const formatPriceChange = (value) => {
    const sign = value >= 0 ? '+' : ''
    return `${sign}${(value || 0).toFixed(2)}%`
}

const formatVolatility = (value) => {
    return `${(value || 0).toFixed(2)}%`
}

const formatVolume = (value) => {
    if (value >= 1000000000) {
        return `${(value / 1000000000).toFixed(2)}B`
    } else if (value >= 1000000) {
        return `${(value / 1000000).toFixed(2)}M`
    } else if (value >= 1000) {
        return `${(value / 1000).toFixed(2)}K`
    }
    return value?.toString() || '0'
}

const formatCorrelation = (value) => {
    if (value === null) return 'N/A'
    return (value >= 0 ? '+' : '') + value.toFixed(3)
}

// Color classes
const sentimentColorClass = (value) => {
    if (value > 0.2) return 'text-green-600'
    if (value < -0.2) return 'text-red-600'
    return 'text-gray-600'
}

const priceChangeColorClass = (value) => {
    if (value > 0) return 'text-green-600'
    if (value < 0) return 'text-red-600'
    return 'text-gray-600'
}

// Watchers
watch(() => props.chartData, async () => {
    if (props.chartData && Object.keys(props.chartData).length > 0) {
        await loadData()
    }
}, { deep: true })

// Lifecycle
onMounted(async () => {
    await loadData()
    
    // Setup auto-refresh
    if (props.autoRefresh && !props.pdfMode) {
        setInterval(refreshData, props.refreshInterval)
    }
})
</script>

<style scoped>
.sentiment-price-chart-container {
    @apply w-full;
}

@media print {
    .sentiment-price-chart-container {
        break-inside: avoid;
    }
}

/* PDF-specific styles */
.pdf-mode .chart-content {
    @apply space-y-4;
}

.pdf-mode .chart-header {
    @apply mb-4;
}
</style>