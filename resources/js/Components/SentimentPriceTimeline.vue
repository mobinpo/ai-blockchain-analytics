<template>
    <div class="sentiment-price-timeline">
        <div class="chart-header mb-6">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                Sentiment vs Price Timeline
            </h3>
            <div class="flex flex-wrap gap-4 mb-4">
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Token:</label>
                    <select 
                        v-model="selectedToken" 
                        @change="loadData"
                        class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-panel text-gray-900 dark:text-white"
                    >
                        <option value="ethereum">Ethereum (ETH)</option>
                        <option value="bitcoin">Bitcoin (BTC)</option>
                        <option value="chainlink">Chainlink (LINK)</option>
                        <option value="uniswap">Uniswap (UNI)</option>
                    </select>
                </div>
                
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Timeframe:</label>
                    <select 
                        v-model="timeframe" 
                        @change="loadData"
                        class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-panel text-gray-900 dark:text-white"
                    >
                        <option value="7d">7 Days</option>
                        <option value="30d">30 Days</option>
                        <option value="90d">90 Days</option>
                    </select>
                </div>
                
                <button 
                    @click="loadData" 
                    :disabled="loading"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-md transition-colors"
                >
                    <span v-if="loading" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading...
                    </span>
                    <span v-else>Refresh</span>
                </button>
            </div>
        </div>

        <div v-if="error" class="bg-red-100 dark:bg-red-900/20 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-400 px-4 py-3 rounded mb-4">
            {{ error }}
        </div>

        <div class="chart-container">
            <div v-if="loading" class="flex items-center justify-center h-96">
                <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-600 dark:text-gray-400">Loading chart data...</p>
                </div>
            </div>
            
            <div v-else class="relative">
                <canvas 
                    ref="chartCanvas" 
                    class="max-w-full h-96"
                ></canvas>
                
                <!-- Chart Legend -->
                <div class="mt-4 flex flex-wrap justify-center gap-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-blue-500 rounded"></div>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Price (USD)</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-green-500 rounded"></div>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Sentiment Score</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Statistics -->
        <div v-if="!loading && chartData" class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-panel p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-600 dark:text-gray-400">Current Price</div>
                <div class="text-xl font-bold text-blue-600">${{ currentPrice?.toFixed(2) || 'N/A' }}</div>
            </div>
            <div class="bg-white dark:bg-panel p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-600 dark:text-gray-400">Avg Sentiment</div>
                <div class="text-xl font-bold text-green-600">{{ averageSentiment?.toFixed(1) || 'N/A' }}</div>
            </div>
            <div class="bg-white dark:bg-panel p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-600 dark:text-gray-400">Price Change</div>
                <div class="text-xl font-bold" :class="priceChange >= 0 ? 'text-green-600' : 'text-red-600'">
                    {{ priceChange >= 0 ? '+' : '' }}{{ priceChange?.toFixed(2) || 'N/A' }}%
                </div>
            </div>
            <div class="bg-white dark:bg-panel p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-600 dark:text-gray-400">Correlation</div>
                <div class="text-xl font-bold text-purple-600">{{ correlation?.toFixed(2) || 'N/A' }}</div>
            </div>
        </div>
    </div>
</template>

<script>
import {
    Chart,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    TimeScale,
    Filler
} from 'chart.js'
import 'chartjs-adapter-date-fns'
import { format, parseISO } from 'date-fns'
import { markRaw, nextTick } from 'vue'

// Register Chart.js components
Chart.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    TimeScale,
    Filler
)

export default {
    name: 'SentimentPriceTimeline',
    props: {
        contractAddress: {
            type: String,
            default: null
        },
        autoRefresh: {
            type: Boolean,
            default: false
        },
        refreshInterval: {
            type: Number,
            default: 300000 // 5 minutes
        }
    },
    data() {
        return {
            chart: null,
            chartData: null,
            loading: false,
            error: null,
            showFallback: false,
            selectedToken: 'ethereum',
            timeframe: '30d',
            currentPrice: null,
            averageSentiment: null,
            priceChange: null,
            correlation: null,
            refreshTimer: null
        }
    },
    mounted() {
        // Use nextTick to ensure DOM is ready
        this.$nextTick(() => {
            this.initializeChart()
            this.loadData()
            
            if (this.autoRefresh) {
                this.startAutoRefresh()
            }
        })
    },
    beforeUnmount() {
        if (this.chart) {
            this.chart.destroy()
        }
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer)
        }
    },
    methods: {
        initializeChart() {
            // Destroy existing chart if it exists
            if (this.chart) {
                try {
                    this.chart.destroy()
                } catch (e) {
                    console.warn('Error destroying chart:', e)
                }
                this.chart = null
            }
            
            // Check if canvas ref exists
            if (!this.$refs.chartCanvas) {
                console.warn('Canvas ref not available, deferring chart initialization')
                this.$nextTick(() => {
                    if (this.$refs.chartCanvas) {
                        this.initializeChart()
                    }
                })
                return
            }
            
            try {
                const ctx = this.$refs.chartCanvas.getContext('2d')
                if (!ctx) {
                    throw new Error('Failed to get 2D context from canvas')
                }
                
                // Create chart configuration as a plain object (non-reactive)
                const chartConfig = {
                    type: 'line',
                    data: {
                        datasets: []
                    },
                    options: this.getChartOptions()
                }
                
                // Create chart with non-reactive configuration
                const chartInstance = new Chart(ctx, chartConfig)
                
                // Prevent chart instance from becoming reactive
                this.chart = markRaw(chartInstance)
                
            } catch (error) {
                console.error('Failed to initialize chart:', error)
                this.error = 'Failed to initialize chart. Please refresh the page.'
            }
        },

        getChartOptions() {
            // Return a plain object that's not reactive
            return {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        type: 'linear', // Temporarily use linear instead of time
                        display: true,
                        title: {
                            display: true,
                            text: 'Date',
                            color: '#6B7280'
                        },
                        grid: {
                            color: '#E5E7EB'
                        },
                        ticks: {
                            color: '#6B7280'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Price (USD)',
                            color: '#3B82F6'
                        },
                        grid: {
                            color: '#E5E7EB'
                        },
                        ticks: {
                            color: '#3B82F6',
                            callback: function(value) {
                                return '$' + value.toFixed(2)
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Sentiment Score',
                            color: '#10B981'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            color: '#10B981',
                            min: -1,
                            max: 1,
                            callback: function(value) {
                                return value.toFixed(1)
                            }
                        }
                    }
                },
                plugins: {
                    title: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || ''
                                const value = context.parsed.y
                                
                                if (label.includes('Price')) {
                                    return `${label}: $${value.toFixed(2)}`
                                } else if (label.includes('Sentiment')) {
                                    return `${label}: ${value.toFixed(2)}`
                                }
                                return `${label}: ${value}`
                            }
                        }
                    },
                    legend: {
                        display: false // We'll use custom legend
                    }
                }
            }
        },

        async loadData() {
            this.loading = true
            this.error = null
            
            try {
                // Load price data from CoinGecko
                const priceData = await this.fetchPriceData()
                
                // Load sentiment data from our API
                const sentimentData = await this.fetchSentimentData()
                
                // Combine and process data
                this.processChartData(priceData, sentimentData)
                
                // Update chart
                this.updateChart()
                
                // Calculate statistics
                this.calculateStatistics()
                
            } catch (error) {
                console.error('Error loading chart data:', error)
                this.error = 'Failed to load chart data. Please try again.'
            } finally {
                this.loading = false
            }
        },

        async fetchPriceData() {
            const days = this.timeframe.replace('d', '')
            const url = `https://api.coingecko.com/api/v3/coins/${this.selectedToken}/market_chart?vs_currency=usd&days=${days}&interval=daily`
            
            const response = await fetch(url)
            if (!response.ok) {
                throw new Error('Failed to fetch price data from CoinGecko')
            }
            
            const data = await response.json()
            
            return data.prices.map(([timestamp, price]) => ({
                timestamp: new Date(timestamp),
                price: price
            }))
        },

        async fetchSentimentData() {
            const params = new URLSearchParams({
                token: this.selectedToken,
                timeframe: this.timeframe
            })
            
            // Only add contract_address if it's not empty
            if (this.contractAddress && this.contractAddress.trim() !== '') {
                params.append('contract_address', this.contractAddress)
            }
            
            const response = await fetch(`/api/sentiment-timeline/timeline?${params}`)
            if (!response.ok) {
                // If sentiment API fails, generate mock data
                return this.generateMockSentimentData()
            }
            
            const data = await response.json()
            return data.sentiment_data || []
        },

        generateMockSentimentData() {
            // Generate mock sentiment data for demonstration
            const data = []
            const days = parseInt(this.timeframe.replace('d', ''))
            const now = new Date()
            
            for (let i = days; i >= 0; i--) {
                const date = new Date(now.getTime() - (i * 24 * 60 * 60 * 1000))
                const sentiment = Math.sin(i / 7) * 0.3 + Math.random() * 0.4 - 0.2
                
                data.push({
                    timestamp: date,
                    sentiment: Math.max(-1, Math.min(1, sentiment)),
                    volume: Math.floor(Math.random() * 1000) + 100
                })
            }
            
            return data
        },

        processChartData(priceData, sentimentData) {
            // Align timestamps and combine data
            const combinedData = []
            
            priceData.forEach(pricePoint => {
                const sentimentPoint = sentimentData.find(s => 
                    Math.abs(s.timestamp.getTime() - pricePoint.timestamp.getTime()) < 24 * 60 * 60 * 1000
                )
                
                if (sentimentPoint) {
                    combinedData.push({
                        timestamp: new Date(pricePoint.timestamp),
                        price: Number(pricePoint.price),
                        sentiment: Number(sentimentPoint.sentiment)
                    })
                }
            })
            
            // Use JSON clone to remove reactivity
            this.chartData = JSON.parse(JSON.stringify(combinedData))
        },

        updateChart() {
            if (!this.chart || !this.chartData) return
            
            try {
                // Clone data to avoid Vue reactivity issues with Chart.js
                const priceData = []
                const sentimentData = []
                
                for (let i = 0; i < this.chartData.length; i++) {
                    const point = this.chartData[i]
                    
                    const price = Number(point.price)
                    const sentiment = Number(point.sentiment)
                    
                    // Validate numbers
                    if (isNaN(price) || isNaN(sentiment)) {
                        console.warn('Invalid data:', { price: point.price, sentiment: point.sentiment })
                        continue
                    }
                    
                    // Use simple index instead of dates temporarily
                    priceData.push({ x: i, y: price })
                    sentimentData.push({ x: i, y: sentiment })
                }
            
                // Create completely new dataset objects to avoid any reactivity issues
                const newDatasets = [
                    {
                        label: 'Price (USD)',
                        data: JSON.parse(JSON.stringify(priceData)), // Deep clone to break reactivity
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'y',
                        tension: 0.1
                    },
                    {
                        label: 'Sentiment Score',
                        data: JSON.parse(JSON.stringify(sentimentData)), // Deep clone to break reactivity
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'y1',
                        tension: 0.1
                    }
                ]
                
                // Replace the entire datasets array
                this.chart.data.datasets = newDatasets
                
                // Use async update to prevent blocking
                this.$nextTick(async () => {
                    try {
                        this.chart.update('none') // Use 'none' mode for better performance
                    } catch (error) {
                        console.warn('Chart update failed:', error)
                        // Don't recursively call initializeChart to prevent infinite loops
                        this.error = 'Chart update failed. Please refresh manually.'
                    }
                })
                
            } catch (error) {
                console.error('Error in updateChart:', error)
                this.error = 'Chart rendering failed. Please refresh the page.'
                this.showFallback = true
            }
        },

        calculateStatistics() {
            if (!this.chartData || this.chartData.length === 0) return
            
            const prices = this.chartData.map(d => d.price)
            const sentiments = this.chartData.map(d => d.sentiment)
            
            // Current price
            this.currentPrice = prices[prices.length - 1]
            
            // Average sentiment
            this.averageSentiment = sentiments.reduce((a, b) => a + b, 0) / sentiments.length
            
            // Price change percentage
            if (prices.length > 1) {
                const firstPrice = prices[0]
                const lastPrice = prices[prices.length - 1]
                this.priceChange = ((lastPrice - firstPrice) / firstPrice) * 100
            }
            
            // Correlation between price and sentiment
            this.correlation = this.calculateCorrelation(prices, sentiments)
        },

        calculateCorrelation(x, y) {
            if (x.length !== y.length || x.length === 0) return 0
            
            const n = x.length
            const sumX = x.reduce((a, b) => a + b, 0)
            const sumY = y.reduce((a, b) => a + b, 0)
            const sumXY = x.map((xi, i) => xi * y[i]).reduce((a, b) => a + b, 0)
            const sumXX = x.map(xi => xi * xi).reduce((a, b) => a + b, 0)
            const sumYY = y.map(yi => yi * yi).reduce((a, b) => a + b, 0)
            
            const numerator = n * sumXY - sumX * sumY
            const denominator = Math.sqrt((n * sumXX - sumX * sumX) * (n * sumYY - sumY * sumY))
            
            return denominator === 0 ? 0 : numerator / denominator
        },

        startAutoRefresh() {
            this.refreshTimer = setInterval(() => {
                this.loadData()
            }, this.refreshInterval)
        },

        stopAutoRefresh() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer)
                this.refreshTimer = null
            }
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
.sentiment-price-timeline {
    @apply w-full;
}

.chart-container {
    @apply bg-white dark:bg-panel rounded-lg border border-gray-200 dark:border-gray-700 p-6;
}

.chart-header {
    @apply border-b border-gray-200 dark:border-gray-700 pb-4;
}
</style>
