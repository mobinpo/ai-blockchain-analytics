<template>
    <div class="sentiment-price-chart-container">
        <!-- Chart Controls -->
        <div class="chart-controls mb-6 p-4 bg-panel rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Coin Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Cryptocurrency
                    </label>
                    <select 
                        v-model="selectedCoin" 
                        @change="onCoinChange"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500"
                    >
                        <option value="">Select a coin...</option>
                        <option 
                            v-for="coin in availableCoins" 
                            :key="coin.id" 
                            :value="coin.id"
                        >
                            {{ coin.name }} ({{ coin.symbol.toUpperCase() }})
                        </option>
                    </select>
                </div>

                <!-- Date Range -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Start Date
                    </label>
                    <input 
                        v-model="startDate" 
                        type="date" 
                        @change="onDateChange"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        End Date
                    </label>
                    <input 
                        v-model="endDate" 
                        type="date" 
                        @change="onDateChange"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500"
                    />
                </div>

                <!-- Platform Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Platform
                    </label>
                    <select 
                        v-model="selectedPlatform" 
                        @change="onFiltersChange"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500"
                    >
                        <option value="all">All Platforms</option>
                        <option value="twitter">Twitter</option>
                        <option value="reddit">Reddit</option>
                        <option value="telegram">Telegram</option>
                    </select>
                </div>
            </div>

            <!-- Quick Date Range Buttons -->
            <div class="mt-4 flex flex-wrap gap-2">
                <button 
                    v-for="range in quickRanges" 
                    :key="range.key"
                    @click="setQuickRange(range)"
                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-panel focus:outline-none focus:ring-2 focus:ring-brand-500"
                >
                    {{ range.label }}
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center h-64">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            <span class="ml-2 text-gray-600">Loading chart data...</span>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error loading chart data</h3>
                    <p class="mt-1 text-sm text-red-700">{{ error }}</p>
                </div>
            </div>
        </div>

        <!-- Statistics Panel -->
        <div v-else-if="statistics" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">Correlation</div>
                <div class="mt-1 text-2xl font-semibold" :class="getCorrelationColor(parseFloat(statistics.correlation_score))">
                    {{ statistics.correlation_score || 'N/A' }}
                </div>
                <div class="text-xs text-gray-600">{{ getCorrelationStrength(parseFloat(statistics.correlation_score)) }}</div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">Avg Sentiment</div>
                <div class="mt-1 text-2xl font-semibold" :class="getSentimentColor(statistics.avg_sentiment)">
                    {{ statistics.avg_sentiment?.toFixed(3) || 'N/A' }}
                </div>
                <div class="text-xs text-gray-600">{{ getSentimentLabel(statistics.avg_sentiment) }}</div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">Price Change</div>
                <div class="mt-1 text-2xl font-semibold" :class="getPriceChangeColor(parseFloat(statistics.price_change_percent))">
                    {{ statistics.price_change_percent || 'N/A' }}%
                </div>
                <div class="text-xs text-gray-600">Total period change</div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">Data Points</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ statistics.data_points || 0 }}
                </div>
                <div class="text-xs text-gray-600">Days analyzed</div>
            </div>
        </div>

        <!-- Chart Container -->
        <div v-else-if="chartData" class="bg-white rounded-lg shadow-lg p-6">
            <div class="mb-4 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">
                    Sentiment vs Price Timeline
                    <span v-if="selectedCoinName" class="text-sm text-gray-500 ml-2">
                        ({{ selectedCoinName }})
                    </span>
                </h3>
                
                <!-- Chart Controls -->
                <div class="flex items-center space-x-4">
                    <!-- Chart Type Toggle -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-700">Chart Type:</label>
                        <select 
                            v-model="chartType" 
                            @change="updateChart"
                            class="text-sm rounded border-gray-300 focus:border-indigo-500 focus:ring-brand-500"
                        >
                            <option value="line">Line Chart</option>
                            <option value="scatter">Scatter Plot</option>
                            <option value="dual">Dual Axis</option>
                        </select>
                    </div>

                    <!-- Export Buttons -->
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="exportToCSV"
                            :disabled="!chartData"
                            class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 rounded hover:bg-panel disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Export data as CSV"
                        >
                            <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            CSV
                        </button>
                        
                        <button 
                            @click="exportChartImage"
                            :disabled="!chartData"
                            class="inline-flex items-center px-2 py-1 text-xs border border-gray-300 rounded hover:bg-panel disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Export chart as PNG"
                        >
                            <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            PNG
                        </button>
                    </div>
                </div>
            </div>

            <!-- Chart Canvas -->
            <div class="relative" style="height: 400px;">
                <canvas ref="chartCanvas"></canvas>
            </div>

            <!-- Chart Legend/Info -->
            <div class="mt-4 text-sm text-gray-600">
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded mr-2"></div>
                        <span>Sentiment Score</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded mr-2"></div>
                        <span>Price (USD)</span>
                    </div>
                    <div v-if="chartType === 'scatter'" class="text-xs">
                        <span>Bubble size represents post volume</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Data State -->
        <div v-else class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No data available</h3>
            <p class="mt-1 text-sm text-gray-500">Select a cryptocurrency and date range to view the chart.</p>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import axios from 'axios'

export default {
    name: 'SentimentPriceChart',
    props: {
        initialCoin: {
            type: String,
            default: 'bitcoin'
        },
        initialDays: {
            type: Number,
            default: 30
        }
    },
    setup(props) {
        // Reactive state
        const loading = ref(false)
        const error = ref(null)
        const chartCanvas = ref(null)
        const chart = ref(null)
        
        const selectedCoin = ref('')
        const selectedCoinName = ref('')
        const selectedPlatform = ref('all')
        const selectedCategory = ref('all')
        const chartType = ref('dual')
        
        const startDate = ref('')
        const endDate = ref('')
        
        const availableCoins = ref([])
        const chartData = ref(null)
        const statistics = ref(null)

        // Quick date ranges
        const quickRanges = [
            { key: '7d', label: 'Last 7 Days', days: 7 },
            { key: '30d', label: 'Last 30 Days', days: 30 },
            { key: '90d', label: 'Last 90 Days', days: 90 },
            { key: '180d', label: 'Last 6 Months', days: 180 },
            { key: '365d', label: 'Last Year', days: 365 }
        ]

        // Computed properties
        const hasValidParams = computed(() => {
            return selectedCoin.value && startDate.value && endDate.value
        })

        // Methods
        const formatDate = (date) => {
            return new Date(date).toISOString().split('T')[0]
        }

        const setQuickRange = (range) => {
            const end = new Date()
            const start = new Date(end.getTime() - (range.days * 24 * 60 * 60 * 1000))
            
            endDate.value = formatDate(end)
            startDate.value = formatDate(start)
            
            if (hasValidParams.value) {
                fetchChartData()
            }
        }

        const loadAvailableCoins = async () => {
            try {
                const response = await axios.get('/api/sentiment/available-coins')
                availableCoins.value = response.data.coins || []
                
                // Set initial coin if provided
                if (props.initialCoin && availableCoins.value.some(coin => coin.id === props.initialCoin)) {
                    selectedCoin.value = props.initialCoin
                    const coin = availableCoins.value.find(c => c.id === props.initialCoin)
                    selectedCoinName.value = coin?.name || ''
                }
            } catch (err) {
                console.error('Failed to load available coins:', err)
                error.value = 'Failed to load available cryptocurrencies'
            }
        }

        // Helper function to calculate Pearson correlation
        const calculateCorrelation = (x, y) => {
            const n = x.length
            if (n !== y.length || n < 2) return 0

            const sumX = x.reduce((a, b) => a + b, 0)
            const sumY = y.reduce((a, b) => a + b, 0)
            const sumXY = x.reduce((acc, xi, i) => acc + xi * y[i], 0)
            const sumXX = x.reduce((acc, xi) => acc + xi * xi, 0)
            const sumYY = y.reduce((acc, yi) => acc + yi * yi, 0)

            const numerator = n * sumXY - sumX * sumY
            const denominator = Math.sqrt((n * sumXX - sumX * sumX) * (n * sumYY - sumY * sumY))

            return denominator !== 0 ? numerator / denominator : 0
        }

        const fetchChartData = async () => {
            if (!hasValidParams.value) return

            loading.value = true
            error.value = null

            try {
                const params = {
                    coin_id: selectedCoin.value,
                    start_date: startDate.value,
                    end_date: endDate.value,
                    platforms: selectedPlatform.value,
                    categories: selectedCategory.value
                }

                const response = await axios.get('/api/sentiment/price-correlation', { params })
                
                chartData.value = response.data.chart_data
                statistics.value = response.data.correlation_stats
                
                await nextTick()
                renderChart()
                
            } catch (err) {
                console.warn('API failed, generating demo data:', err.message)
                
                // Generate demo data as fallback
                const days = Math.ceil((new Date(endDate.value) - new Date(startDate.value)) / (1000 * 60 * 60 * 24))
                const demoSentimentData = []
                const demoPriceData = []
                
                let basePrice = 50000 // Bitcoin base price
                
                for (let i = 0; i <= days; i++) {
                    const date = new Date(startDate.value)
                    date.setDate(date.getDate() + i)
                    
                    // Generate correlated sentiment and price data
                    const sentiment = Math.sin(i * 0.1) * 0.3 + ((Math.random() - 0.5) * 0.4)
                    const clampedSentiment = Math.max(-1, Math.min(1, sentiment))
                    
                    const priceChange = clampedSentiment * 0.02 + ((Math.random() - 0.5) * 0.01)
                    basePrice = basePrice * (1 + priceChange)
                    
                    demoSentimentData.push({
                        date: date.toISOString().split('T')[0],
                        sentiment: Math.round(clampedSentiment * 1000) / 1000,
                        volume: Math.floor(Math.random() * 500) + 50
                    })
                    
                    demoPriceData.push({
                        date: date.toISOString().split('T')[0],
                        price: Math.round(basePrice * 100) / 100,
                        volume: Math.floor(Math.random() * 9000000) + 1000000
                    })
                }
                
                chartData.value = {
                    sentiment_timeline: demoSentimentData,
                    price_timeline: demoPriceData
                }
                
                // Calculate demo correlation
                const sentimentValues = demoSentimentData.map(d => d.sentiment)
                const priceValues = demoPriceData.map(d => d.price)
                const correlation = calculateCorrelation(sentimentValues, priceValues)
                
                statistics.value = {
                    correlation_coefficient: correlation,
                    data_points: demoSentimentData.length,
                    period: {
                        start: startDate.value,
                        end: endDate.value,
                        days: days
                    }
                }
                
                await nextTick()
                renderChart()
                
                error.value = 'Using demo data (API temporarily unavailable)'
            } finally {
                loading.value = false
            }
        }

        const renderChart = async () => {
            if (!chartCanvas.value || !chartData.value) return

            // Dynamically import Chart.js
            const { Chart, registerables } = await import('chart.js')
            Chart.register(...registerables)

            // Destroy existing chart
            if (chart.value) {
                chart.value.destroy()
            }

            const ctx = chartCanvas.value.getContext('2d')
            const correlation = chartData.value || []

            if (chartType.value === 'scatter') {
                renderScatterChart(Chart, ctx, correlation)
            } else if (chartType.value === 'dual') {
                renderDualAxisChart(Chart, ctx, correlation)
            } else {
                renderLineChart(Chart, ctx, correlation)
            }
        }

        const renderLineChart = (Chart, ctx, data) => {
            chart.value = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => new Date(d.date).toLocaleDateString()),
                    datasets: [
                        {
                            label: 'Sentiment Score',
                            data: data.map(d => d.sentiment_data?.average_sentiment || 0),
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            yAxisID: 'y'
                        },
                        {
                            label: 'Price Change %',
                            data: data.map(d => d.price_data?.price_change_percent || 0),
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            yAxisID: 'y1'
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
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Sentiment Score'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Price Change %'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            })
        }

        const renderScatterChart = (Chart, ctx, data) => {
            chart.value = new Chart(ctx, {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Sentiment vs Price Change',
                        data: data.map(d => ({
                            x: d.sentiment_data?.average_sentiment || 0,
                            y: d.price_data?.price_change_percent || 0,
                            r: Math.max(3, Math.min(15, (d.sentiment_data?.total_posts || 0) / 10)) // Bubble size based on post count
                        })),
                        backgroundColor: 'rgba(59, 130, 246, 0.6)',
                        borderColor: 'rgb(59, 130, 246)',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Sentiment Score'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Price Change %'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const point = data[context.dataIndex]
                                    return [
                                        `Date: ${new Date(point.date).toLocaleDateString()}`,
                                        `Sentiment: ${(point.sentiment_data?.average_sentiment || 0).toFixed(3)}`,
                                        `Price Change: ${(point.price_data?.price_change_percent || 0).toFixed(2)}%`,
                                        `Posts: ${point.sentiment_data?.total_posts || 0}`
                                    ]
                                }
                            }
                        }
                    }
                }
            })
        }

        const renderDualAxisChart = (Chart, ctx, data) => {
            chart.value = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => new Date(d.date).toLocaleDateString()),
                    datasets: [
                        {
                            label: 'Sentiment Score',
                            data: data.map(d => d.sentiment_data?.average_sentiment || 0),
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            yAxisID: 'y',
                            tension: 0.3
                        },
                        {
                            label: 'Price (USD)',
                            data: data.map(d => d.price_data?.price_avg || 0),
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.3
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
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
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
                            max: 1
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
                            },
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                afterBody: (tooltipItems) => {
                                    const index = tooltipItems[0].dataIndex
                                    const point = data[index]
                                    return [
                                        '',
                                        `Posts: ${point.sentiment_data?.total_posts || 0}`,
                                        `Correlation: ${point.correlation_score?.toFixed(3) || 'N/A'}`
                                    ]
                                }
                            }
                        }
                    }
                }
            })
        }

        const updateChart = () => {
            if (chartData.value) {
                renderChart()
            }
        }

        // Event handlers
        const onCoinChange = () => {
            const coin = availableCoins.value.find(c => c.id === selectedCoin.value)
            selectedCoinName.value = coin?.name || ''
            
            if (hasValidParams.value) {
                fetchChartData()
            }
        }

        const onDateChange = () => {
            if (hasValidParams.value) {
                fetchChartData()
            }
        }

        const onFiltersChange = () => {
            if (hasValidParams.value) {
                fetchChartData()
            }
        }

        // Utility functions
        const getCorrelationColor = (correlation) => {
            if (!correlation) return 'text-gray-500'
            const abs = Math.abs(correlation)
            if (abs >= 0.6) return correlation > 0 ? 'text-green-600' : 'text-red-600'
            if (abs >= 0.3) return correlation > 0 ? 'text-green-500' : 'text-red-500'
            return 'text-gray-500'
        }

        const getSentimentColor = (sentiment) => {
            if (!sentiment) return 'text-gray-500'
            if (sentiment > 0.2) return 'text-green-600'
            if (sentiment < -0.2) return 'text-red-600'
            return 'text-gray-500'
        }

        const getPriceChangeColor = (change) => {
            if (!change) return 'text-gray-500'
            return change > 0 ? 'text-green-600' : 'text-red-600'
        }

        const getSentimentLabel = (sentiment) => {
            if (!sentiment) return ''
            if (sentiment > 0.6) return 'Very Positive'
            if (sentiment > 0.2) return 'Positive'
            if (sentiment > -0.2) return 'Neutral'
            if (sentiment > -0.6) return 'Negative'
            return 'Very Negative'
        }

        const getCorrelationStrength = (correlation) => {
            if (!correlation) return ''
            const abs = Math.abs(correlation)
            if (abs >= 0.7) return 'Strong'
            if (abs >= 0.4) return 'Moderate'
            if (abs >= 0.2) return 'Weak'
            return 'Very Weak'
        }

        // Lifecycle
        onMounted(async () => {
            await loadAvailableCoins()
            
            // Set initial date range
            setQuickRange(quickRanges.find(r => r.days === props.initialDays) || quickRanges[1])
        })

        const exportToCSV = () => {
            if (!chartData.value) {
                console.warn('No data available for CSV export')
                return
            }

            const data = chartData.value
            const csvHeaders = [
                'Date',
                'Sentiment Score', 
                'Price (USD)',
                'Price Change %',
                'Posts Count',
                'Correlation Score'
            ]

            const csvRows = data.map(item => [
                item.date,
                item.sentiment_data?.average_sentiment?.toFixed(3) || 'N/A',
                item.price_data?.price_avg?.toFixed(2) || 'N/A', 
                item.price_data?.price_change_percent?.toFixed(2) || 'N/A',
                item.sentiment_data?.total_posts || 0,
                item.correlation_score?.toFixed(3) || 'N/A'
            ])

            const csvContent = [
                csvHeaders.join(','),
                ...csvRows.map(row => row.join(','))
            ].join('\n')

            const blob = new Blob([csvContent], { type: 'text/csv' })
            const url = URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = `sentiment-price-data-${selectedCoin.value}-${startDate.value}-to-${endDate.value}.csv`
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            URL.revokeObjectURL(url)
        }

        const exportChartImage = async () => {
            if (!chart.value) {
                console.warn('Chart not available for image export')
                return
            }

            try {
                const canvas = chart.value.canvas
                const url = canvas.toDataURL('image/png')
                const link = document.createElement('a')
                link.href = url
                link.download = `sentiment-price-chart-${selectedCoin.value}-${startDate.value}.png`
                document.body.appendChild(link)
                link.click()
                document.body.removeChild(link)
            } catch (error) {
                console.error('Failed to export chart image:', error)
            }
        }

        return {
            // State
            loading,
            error,
            chartCanvas,
            selectedCoin,
            selectedCoinName,
            selectedPlatform,
            selectedCategory,
            chartType,
            startDate,
            endDate,
            availableCoins,
            chartData,
            statistics,
            quickRanges,
            
            // Methods
            setQuickRange,
            onCoinChange,
            onDateChange,
            onFiltersChange,
            updateChart,
            getCorrelationColor,
            getSentimentColor,
            getPriceChangeColor,
            getSentimentLabel,
            getCorrelationStrength,
            exportToCSV,
            exportChartImage
        }
    }
}
</script>

<style scoped>
.sentiment-price-chart-container {
    @apply w-full max-w-7xl mx-auto;
}

.chart-controls {
    @apply transition-all duration-200;
}

.chart-controls:hover {
    @apply shadow-md;
}

/* Custom scrollbar for chart container */
.chart-container::-webkit-scrollbar {
    height: 4px;
}

.chart-container::-webkit-scrollbar-track {
    @apply bg-ink rounded;
}

.chart-container::-webkit-scrollbar-thumb {
    @apply bg-brand-500/50 rounded;
}

.chart-container::-webkit-scrollbar-thumb:hover {
    @apply bg-brand-400;
}
</style>