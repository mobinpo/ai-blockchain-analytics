<template>
    <div class="sentiment-price-timeline">
        <div class="chart-header mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ title }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ subtitle }}
                    </p>
                </div>
                
                <!-- Controls -->
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <!-- Time Range Selector -->
                    <select 
                        v-model="selectedTimeRange" 
                        @change="updateTimeRange"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="24h">24 Hours</option>
                        <option value="7d">7 Days</option>
                        <option value="30d">30 Days</option>
                        <option value="90d">90 Days</option>
                        <option value="1y">1 Year</option>
                    </select>
                    
                    <!-- Token Selector -->
                    <select 
                        v-model="selectedToken" 
                        @change="updateToken"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="bitcoin">Bitcoin</option>
                        <option value="ethereum">Ethereum</option>
                        <option value="binancecoin">Binance Coin</option>
                        <option value="cardano">Cardano</option>
                        <option value="solana">Solana</option>
                        <option value="polkadot">Polkadot</option>
                        <option value="chainlink">Chainlink</option>
                        <option value="polygon">Polygon</option>
                    </select>
                    
                    <!-- Refresh Button -->
                    <button 
                        @click="refreshData" 
                        :disabled="loading"
                        class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-1"
                    >
                        <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Chart Container -->
        <div class="chart-container relative">
            <!-- Loading Overlay -->
            <div v-if="loading" class="absolute inset-0 bg-white/80 dark:bg-panel/80 flex items-center justify-center z-10 rounded-lg">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Loading data...</p>
                </div>
            </div>

            <!-- Error State -->
            <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6 text-center">
                <div class="text-red-600 dark:text-red-400 mb-2">
                    <svg class="h-8 w-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.966-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <h4 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-2">Error Loading Data</h4>
                <p class="text-sm text-red-600 dark:text-red-400 mb-4">{{ error }}</p>
                <button 
                    @click="refreshData" 
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500"
                >
                    Try Again
                </button>
            </div>

            <!-- Chart Canvas -->
            <div v-if="!loading && !error" class="bg-white dark:bg-panel rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <Line 
                    :data="chartData" 
                    :options="chartOptions" 
                    :key="chartKey"
                    class="max-h-96"
                />
            </div>
        </div>

        <!-- Data Summary -->
        <div v-if="!loading && !error && sentimentData.length > 0" class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <div class="bg-white dark:bg-panel rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Current Price</div>
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                    ${{ formatPrice(currentPrice) }}
                </div>
                <div class="text-sm" :class="priceChange >= 0 ? 'text-green-600' : 'text-red-600'">
                    {{ priceChange >= 0 ? '+' : '' }}{{ priceChange.toFixed(2) }}%
                </div>
            </div>
            
            <div class="bg-white dark:bg-panel rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Sentiment</div>
                <div class="text-2xl font-bold" :class="getSentimentColor(averageSentiment)">
                    {{ averageSentiment.toFixed(2) }}
                </div>
                <div class="text-sm text-gray-500">{{ getSentimentLabel(averageSentiment) }}</div>
            </div>
            
            <div class="bg-white dark:bg-panel rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Correlation</div>
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {{ correlation.toFixed(3) }}
                </div>
                <div class="text-sm text-gray-500">{{ getCorrelationLabel(correlation) }}</div>
            </div>
            
            <div class="bg-white dark:bg-panel rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Data Points</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ sentimentData.length }}
                </div>
                <div class="text-sm text-gray-500">{{ selectedTimeRange.toUpperCase() }}</div>
            </div>
        </div>

        <!-- Legend and Insights -->
        <div v-if="!loading && !error" class="mt-6 bg-panel dark:bg-gray-900 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Chart Insights</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h5 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Legend</h5>
                    <div class="space-y-1 text-sm">
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-1 bg-blue-500"></div>
                            <span>Sentiment Score (-1 to 1)</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-1 bg-green-500"></div>
                            <span>Price (USD)</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h5 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Analysis</h5>
                    <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <div>{{ getInsightText() }}</div>
                        <div class="text-xs text-gray-500">
                            Last updated: {{ lastUpdated }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    TimeScale
} from 'chart.js'
import 'chartjs-adapter-date-fns'

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    TimeScale
)

// Props
const props = defineProps({
    title: {
        type: String,
        default: 'Sentiment vs Price Timeline'
    },
    subtitle: {
        type: String,
        default: 'Real-time correlation between market sentiment and price movements'
    },
    initialToken: {
        type: String,
        default: 'bitcoin'
    },
    initialTimeRange: {
        type: String,
        default: '7d'
    },
    apiEndpoint: {
        type: String,
        default: '/api/sentiment-price-data'
    },
    coingeckoEnabled: {
        type: Boolean,
        default: true
    },
    realTimeUpdates: {
        type: Boolean,
        default: false
    },
    updateInterval: {
        type: Number,
        default: 300000 // 5 minutes
    }
})

// Reactive state
const loading = ref(false)
const error = ref(null)
const selectedToken = ref(props.initialToken)
const selectedTimeRange = ref(props.initialTimeRange)
const sentimentData = ref([])
const priceData = ref([])
const chartKey = ref(0)
const lastUpdated = ref('')
let updateTimer = null

// Computed properties
const currentPrice = computed(() => {
    return priceData.value.length > 0 ? priceData.value[priceData.value.length - 1].y : 0
})

const priceChange = computed(() => {
    if (priceData.value.length < 2) return 0
    const latest = priceData.value[priceData.value.length - 1].y
    const previous = priceData.value[priceData.value.length - 2].y
    return ((latest - previous) / previous) * 100
})

const averageSentiment = computed(() => {
    if (sentimentData.value.length === 0) return 0
    const sum = sentimentData.value.reduce((acc, point) => acc + point.y, 0)
    return sum / sentimentData.value.length
})

const correlation = computed(() => {
    if (sentimentData.value.length < 2 || priceData.value.length < 2) return 0
    
    // Calculate Pearson correlation coefficient
    const n = Math.min(sentimentData.value.length, priceData.value.length)
    const sentimentValues = sentimentData.value.slice(-n).map(d => d.y)
    const priceValues = priceData.value.slice(-n).map(d => d.y)
    
    const sentimentMean = sentimentValues.reduce((a, b) => a + b) / n
    const priceMean = priceValues.reduce((a, b) => a + b) / n
    
    const numerator = sentimentValues.reduce((sum, sentiment, i) => {
        return sum + (sentiment - sentimentMean) * (priceValues[i] - priceMean)
    }, 0)
    
    const sentimentVariance = sentimentValues.reduce((sum, sentiment) => {
        return sum + Math.pow(sentiment - sentimentMean, 2)
    }, 0)
    
    const priceVariance = priceValues.reduce((sum, price) => {
        return sum + Math.pow(price - priceMean, 2)
    }, 0)
    
    const denominator = Math.sqrt(sentimentVariance * priceVariance)
    
    return denominator === 0 ? 0 : numerator / denominator
})

const chartData = computed(() => {
    return {
        datasets: [
            {
                label: 'Sentiment Score',
                data: sentimentData.value,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.3,
                yAxisID: 'y',
                pointRadius: 2,
                pointHoverRadius: 6,
                borderWidth: 2
            },
            {
                label: `${selectedToken.value.charAt(0).toUpperCase() + selectedToken.value.slice(1)} Price (USD)`,
                data: priceData.value,
                borderColor: 'rgb(34, 197, 94)',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.3,
                yAxisID: 'y1',
                pointRadius: 2,
                pointHoverRadius: 6,
                borderWidth: 2
            }
        ]
    }
})

const chartOptions = computed(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            title: {
                display: false
            },
            legend: {
                display: true,
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            },
            tooltip: {
                callbacks: {
                    title: function(context) {
                        if (context[0]) {
                            return new Date(context[0].parsed.x).toLocaleString()
                        }
                        return ''
                    },
                    label: function(context) {
                        let label = context.dataset.label || ''
                        if (label) {
                            label += ': '
                        }
                        if (context.datasetIndex === 0) {
                            // Sentiment score
                            label += context.parsed.y.toFixed(3)
                        } else {
                            // Price
                            label += '$' + formatPrice(context.parsed.y)
                        }
                        return label
                    }
                }
            }
        },
        scales: {
            x: {
                type: 'time',
                time: {
                    displayFormats: {
                        hour: 'MMM d, HH:mm',
                        day: 'MMM d',
                        week: 'MMM d',
                        month: 'MMM yyyy'
                    }
                },
                grid: {
                    display: true,
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Sentiment Score'
                },
                min: -1,
                max: 1,
                grid: {
                    display: true,
                    color: 'rgba(59, 130, 246, 0.1)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Price (USD)'
                },
                grid: {
                    drawOnChartArea: false,
                    color: 'rgba(34, 197, 94, 0.1)'
                },
                ticks: {
                    callback: function(value) {
                        return '$' + formatPrice(value)
                    }
                }
            }
        }
    }
})

// Methods
const fetchData = async () => {
    loading.value = true
    error.value = null
    
    try {
        const response = await fetch(`${props.apiEndpoint}?token=${selectedToken.value}&timeRange=${selectedTimeRange.value}&coingecko=${props.coingeckoEnabled}`)
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }
        
        const data = await response.json()
        
        if (data.success) {
            sentimentData.value = data.data.sentiment.map(point => ({
                x: new Date(point.timestamp),
                y: point.sentiment_score
            }))
            
            priceData.value = data.data.price.map(point => ({
                x: new Date(point.timestamp),
                y: point.price
            }))
            
            lastUpdated.value = new Date().toLocaleString()
            chartKey.value++ // Force chart re-render
        } else {
            throw new Error(data.message || 'Failed to fetch data')
        }
    } catch (err) {
        console.error('Error fetching sentiment/price data:', err)
        error.value = err.message
        
        // Fallback to demo data
        loadDemoData()
    } finally {
        loading.value = false
    }
}

const loadDemoData = () => {
    // Generate demo data for visualization
    const now = new Date()
    const demoSentiment = []
    const demoPrice = []
    
    const basePrice = selectedToken.value === 'bitcoin' ? 45000 : 
                     selectedToken.value === 'ethereum' ? 3000 : 1.0
    
    const dataPoints = selectedTimeRange.value === '24h' ? 24 : 
                      selectedTimeRange.value === '7d' ? 48 : 
                      selectedTimeRange.value === '30d' ? 60 : 90
    
    for (let i = dataPoints; i >= 0; i--) {
        const timestamp = new Date(now - i * 3600000) // Hours ago
        
        // Generate correlated sentiment and price data
        const sentimentBase = Math.sin(i * 0.1) * 0.3
        const sentiment = sentimentBase + (Math.random() - 0.5) * 0.2
        const priceVariation = sentiment * 0.05 + (Math.random() - 0.5) * 0.02
        const price = basePrice * (1 + priceVariation)
        
        demoSentiment.push({
            x: timestamp,
            y: Math.max(-1, Math.min(1, sentiment))
        })
        
        demoPrice.push({
            x: timestamp,
            y: Math.max(0, price)
        })
    }
    
    sentimentData.value = demoSentiment
    priceData.value = demoPrice
    lastUpdated.value = new Date().toLocaleString() + ' (Demo Data)'
    chartKey.value++
}

const refreshData = () => {
    fetchData()
}

const updateTimeRange = () => {
    fetchData()
}

const updateToken = () => {
    fetchData()
}

const formatPrice = (price) => {
    if (price >= 1000) {
        return (price / 1000).toFixed(2) + 'K'
    } else if (price >= 1) {
        return price.toFixed(2)
    } else {
        return price.toFixed(6)
    }
}

const getSentimentColor = (sentiment) => {
    if (sentiment > 0.2) return 'text-green-600 dark:text-green-400'
    if (sentiment < -0.2) return 'text-red-600 dark:text-red-400'
    return 'text-yellow-600 dark:text-yellow-400'
}

const getSentimentLabel = (sentiment) => {
    if (sentiment > 0.2) return 'Positive'
    if (sentiment < -0.2) return 'Negative'
    return 'Neutral'
}

const getCorrelationLabel = (corr) => {
    const abs = Math.abs(corr)
    if (abs > 0.7) return 'Strong'
    if (abs > 0.3) return 'Moderate'
    return 'Weak'
}

const getInsightText = () => {
    const corrAbs = Math.abs(correlation.value)
    const corrDirection = correlation.value > 0 ? 'positive' : 'negative'
    const strength = corrAbs > 0.7 ? 'strong' : corrAbs > 0.3 ? 'moderate' : 'weak'
    
    return `There is a ${strength} ${corrDirection} correlation between sentiment and price over this period.`
}

const startRealTimeUpdates = () => {
    if (props.realTimeUpdates && !updateTimer) {
        updateTimer = setInterval(() => {
            fetchData()
        }, props.updateInterval)
    }
}

const stopRealTimeUpdates = () => {
    if (updateTimer) {
        clearInterval(updateTimer)
        updateTimer = null
    }
}

// Watchers
watch(() => props.realTimeUpdates, (newValue) => {
    if (newValue) {
        startRealTimeUpdates()
    } else {
        stopRealTimeUpdates()
    }
})

// Lifecycle
onMounted(() => {
    fetchData()
    if (props.realTimeUpdates) {
        startRealTimeUpdates()
    }
})

// Cleanup
onUnmounted(() => {
    stopRealTimeUpdates()
})
</script>

<style scoped>
.sentiment-price-timeline {
    @apply w-full;
}

.chart-container {
    min-height: 400px;
}

/* Custom scrollbar for select elements */
select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Animation for loading states */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>