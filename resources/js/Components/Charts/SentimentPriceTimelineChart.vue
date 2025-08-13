<template>
    <div class="sentiment-price-timeline-chart">
        <!-- Chart Header -->
        <div class="chart-header mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ selectedCoin.name }} Sentiment vs Price Timeline
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Real-time correlation between market sentiment and price movements
                    </p>
                </div>

                <!-- Controls -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <!-- Coin Selector -->
                    <div class="relative">
                        <select 
                            v-model="selectedCoinId" 
                            @change="loadChartData"
                            class="appearance-none bg-white dark:bg-panel border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="coin in availableCoins" :key="coin.id" :value="coin.id">
                                {{ coin.name }} ({{ coin.symbol.toUpperCase() }})
                            </option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Time Range Selector -->
                    <div class="relative">
                        <select 
                            v-model="timeRange" 
                            @change="loadChartData"
                            class="appearance-none bg-white dark:bg-panel border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="7d">7 Days</option>
                            <option value="30d">30 Days</option>
                            <option value="90d">90 Days</option>
                            <option value="1y">1 Year</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Refresh Button -->
                    <button 
                        @click="refreshData"
                        :disabled="loading"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg text-sm font-medium transition-colors duration-200"
                    >
                        <svg 
                            class="w-4 h-4" 
                            :class="{ 'animate-spin': loading }" 
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        {{ loading ? 'Loading...' : 'Refresh' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center h-96 bg-panel dark:bg-panel rounded-lg">
            <div class="text-center">
                <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-gray-600 dark:text-gray-400">Loading chart data...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center h-96 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
            <div class="text-center">
                <svg class="h-12 w-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h4 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-2">Failed to Load Data</h4>
                <p class="text-red-600 dark:text-red-300 mb-4">{{ error }}</p>
                <button 
                    @click="loadChartData"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors duration-200"
                >
                    Try Again
                </button>
            </div>
        </div>

        <!-- Chart Container -->
        <div v-else class="chart-container">
            <!-- Chart Canvas -->
            <div class="relative bg-white dark:bg-panel rounded-lg shadow-lg p-6 mb-6">
                <canvas 
                    ref="chartCanvas" 
                    class="w-full"
                    style="height: 500px;"
                ></canvas>
            </div>

            <!-- Chart Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Price Statistics -->
                <div class="bg-white dark:bg-panel rounded-lg shadow-lg p-6">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                        Price Statistics
                    </h4>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Current Price:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                ${{ formatPrice(priceStats.current) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Period Change:</span>
                            <span 
                                class="font-semibold"
                                :class="priceStats.change >= 0 ? 'text-green-600' : 'text-red-600'"
                            >
                                {{ priceStats.change >= 0 ? '+' : '' }}{{ priceStats.change.toFixed(2) }}%
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">High:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                ${{ formatPrice(priceStats.high) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Low:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                ${{ formatPrice(priceStats.low) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sentiment Statistics -->
                <div class="bg-white dark:bg-panel rounded-lg shadow-lg p-6">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        Sentiment Statistics
                    </h4>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Current Sentiment:</span>
                            <span class="font-semibold" :class="getSentimentColor(sentimentStats.current)">
                                {{ sentimentStats.current.toFixed(2) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Average:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ sentimentStats.average.toFixed(2) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Highest:</span>
                            <span class="font-semibold text-green-600">
                                {{ sentimentStats.high.toFixed(2) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Lowest:</span>
                            <span class="font-semibold text-red-600">
                                {{ sentimentStats.low.toFixed(2) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Correlation Statistics -->
                <div class="bg-white dark:bg-panel rounded-lg shadow-lg p-6">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <div class="w-3 h-3 bg-purple-500 rounded-full mr-3"></div>
                        Correlation Analysis
                    </h4>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Correlation:</span>
                            <span class="font-semibold" :class="getCorrelationColor(correlationStats.coefficient)">
                                {{ correlationStats.coefficient.toFixed(3) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Strength:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ correlationStats.strength }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">R-Squared:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ correlationStats.rSquared.toFixed(3) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Data Points:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ correlationStats.dataPoints }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Legend and Info -->
            <div class="bg-white dark:bg-panel rounded-lg shadow-lg p-6">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Chart Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h5 class="font-medium text-gray-900 dark:text-white mb-2">Legend</h5>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <div class="w-4 h-0.5 bg-blue-500 mr-3"></div>
                                <span class="text-sm text-gray-600 dark:text-gray-400">Price (USD)</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-0.5 bg-green-500 mr-3"></div>
                                <span class="text-sm text-gray-600 dark:text-gray-400">Sentiment Score</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h5 class="font-medium text-gray-900 dark:text-white mb-2">Data Source</h5>
                        <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                            <p>• Price data: CoinGecko API</p>
                            <p>• Sentiment data: Social media analysis</p>
                            <p>• Update frequency: {{ updateFrequency }}</p>
                            <p>• Last updated: {{ formatTimestamp(lastUpdated) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';
import 'chartjs-adapter-date-fns';

Chart.register(...registerables);

export default {
    name: 'SentimentPriceTimelineChart',
    
    props: {
        coinId: {
            type: String,
            default: 'bitcoin'
        },
        initialTimeRange: {
            type: String,
            default: '30d'
        },
        autoRefresh: {
            type: Boolean,
            default: false
        },
        refreshInterval: {
            type: Number,
            default: 300000 // 5 minutes
        },
        apiEndpoint: {
            type: String,
            default: '/api/sentiment-price-timeline'
        }
    },

    data() {
        return {
            chart: null,
            loading: false,
            error: null,
            selectedCoinId: this.coinId,
            timeRange: this.initialTimeRange,
            chartData: [],
            availableCoins: [
                { id: 'bitcoin', name: 'Bitcoin', symbol: 'btc' },
                { id: 'ethereum', name: 'Ethereum', symbol: 'eth' },
                { id: 'binancecoin', name: 'BNB', symbol: 'bnb' },
                { id: 'cardano', name: 'Cardano', symbol: 'ada' },
                { id: 'solana', name: 'Solana', symbol: 'sol' },
                { id: 'polkadot', name: 'Polkadot', symbol: 'dot' },
                { id: 'chainlink', name: 'Chainlink', symbol: 'link' },
                { id: 'polygon', name: 'Polygon', symbol: 'matic' }
            ],
            refreshTimer: null,
            lastUpdated: null,
            updateFrequency: 'Every 5 minutes'
        };
    },

    computed: {
        selectedCoin() {
            return this.availableCoins.find(coin => coin.id === this.selectedCoinId) || this.availableCoins[0];
        },

        priceStats() {
            if (!this.chartData.length) {
                return { current: 0, change: 0, high: 0, low: 0 };
            }

            const prices = this.chartData.map(item => item.price);
            const current = prices[prices.length - 1];
            const first = prices[0];
            const change = first > 0 ? ((current - first) / first) * 100 : 0;

            return {
                current,
                change,
                high: Math.max(...prices),
                low: Math.min(...prices)
            };
        },

        sentimentStats() {
            if (!this.chartData.length) {
                return { current: 0, average: 0, high: 0, low: 0 };
            }

            const sentiments = this.chartData.map(item => item.sentiment);
            const current = sentiments[sentiments.length - 1];
            const average = sentiments.reduce((a, b) => a + b, 0) / sentiments.length;

            return {
                current,
                average,
                high: Math.max(...sentiments),
                low: Math.min(...sentiments)
            };
        },

        correlationStats() {
            if (this.chartData.length < 2) {
                return { coefficient: 0, strength: 'Insufficient data', rSquared: 0, dataPoints: 0 };
            }

            const correlation = this.calculateCorrelation(
                this.chartData.map(item => item.price),
                this.chartData.map(item => item.sentiment)
            );

            return {
                coefficient: correlation,
                strength: this.getCorrelationStrength(correlation),
                rSquared: correlation * correlation,
                dataPoints: this.chartData.length
            };
        }
    },

    mounted() {
        this.initializeChart();
        this.loadChartData();
        
        if (this.autoRefresh) {
            this.startAutoRefresh();
        }
    },

    beforeUnmount() {
        this.stopAutoRefresh();
        if (this.chart) {
            this.chart.destroy();
        }
    },

    methods: {
        async loadChartData() {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch(`${this.apiEndpoint}?coin=${this.selectedCoinId}&range=${this.timeRange}`);
                
                if (!response.ok) {
                    throw new Error(`Failed to fetch data: ${response.statusText}`);
                }

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load chart data');
                }

                // Clone data to avoid Vue reactivity issues
                this.chartData = JSON.parse(JSON.stringify(data.data || []));
                this.lastUpdated = new Date();
                this.updateChart();

            } catch (error) {
                console.error('Error loading chart data:', error);
                this.error = error.message;
                
                // Load demo data as fallback
                this.loadDemoData();
            } finally {
                this.loading = false;
            }
        },

        loadDemoData() {
            console.log('Loading demo data as fallback...');
            
            // Generate demo data
            const now = new Date();
            const demoData = [];
            const dataPoints = this.getDataPointsForRange(this.timeRange);

            for (let i = dataPoints - 1; i >= 0; i--) {
                const date = new Date(now.getTime() - (i * this.getIntervalForRange(this.timeRange)));
                const basePrice = this.selectedCoinId === 'bitcoin' ? 45000 : 
                                 this.selectedCoinId === 'ethereum' ? 3000 : 1;
                
                // Generate correlated price and sentiment data
                const trend = Math.sin((i / dataPoints) * Math.PI * 2) * 0.3;
                const noise = (Math.random() - 0.5) * 0.4;
                
                const sentiment = 50 + (trend * 30) + (noise * 20); // 0-100 scale
                const priceMultiplier = 1 + (trend * 0.2) + (noise * 0.1);
                
                demoData.push({
                    timestamp: date.toISOString(),
                    price: basePrice * priceMultiplier,
                    sentiment: Math.max(0, Math.min(100, sentiment)),
                    volume: Math.random() * 1000000000
                });
            }

            // Clone demo data to avoid Vue reactivity issues
            this.chartData = JSON.parse(JSON.stringify(demoData));
            this.lastUpdated = new Date();
            this.updateChart();
        },

        initializeChart() {
            const ctx = this.$refs.chartCanvas.getContext('2d');
            
            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    datasets: [
                        {
                            label: 'Price (USD)',
                            data: [],
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'price'
                        },
                        {
                            label: 'Sentiment Score',
                            data: [],
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'sentiment'
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
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            callbacks: {
                                title: (context) => {
                                    return new Date(context[0].parsed.x).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                },
                                label: (context) => {
                                    if (context.datasetIndex === 0) {
                                        return `Price: $${this.formatPrice(context.parsed.y)}`;
                                    } else {
                                        return `Sentiment: ${context.parsed.y.toFixed(2)}`;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                displayFormats: {
                                    day: 'MMM dd',
                                    week: 'MMM dd',
                                    month: 'MMM yyyy'
                                }
                            },
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        price: {
                            type: 'linear',
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Price (USD)'
                            },
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: (value) => '$' + this.formatPrice(value)
                            }
                        },
                        sentiment: {
                            type: 'linear',
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Sentiment Score'
                            },
                            min: 0,
                            max: 100,
                            grid: {
                                display: false
                            },
                            ticks: {
                                callback: (value) => value.toFixed(0)
                            }
                        }
                    }
                }
            });
        },

        updateChart() {
            if (!this.chart || !this.chartData.length) return;

            // Prepare data for Chart.js with explicit type conversion
            const priceData = this.chartData.map(item => ({
                x: new Date(item.timestamp),
                y: Number(item.price)
            }));

            const sentimentData = this.chartData.map(item => ({
                x: new Date(item.timestamp),
                y: Number(item.sentiment)
            }));

            // Update chart data
            this.chart.data.datasets[0].data = priceData;
            this.chart.data.datasets[1].data = sentimentData;
            
            this.chart.update('none');
        },

        refreshData() {
            this.loadChartData();
        },

        startAutoRefresh() {
            this.stopAutoRefresh();
            this.refreshTimer = setInterval(() => {
                this.loadChartData();
            }, this.refreshInterval);
        },

        stopAutoRefresh() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
        },

        calculateCorrelation(x, y) {
            const n = x.length;
            if (n < 2) return 0;

            const sumX = x.reduce((a, b) => a + b, 0);
            const sumY = y.reduce((a, b) => a + b, 0);
            const sumXY = x.reduce((sum, xi, i) => sum + xi * y[i], 0);
            const sumX2 = x.reduce((sum, xi) => sum + xi * xi, 0);
            const sumY2 = y.reduce((sum, yi) => sum + yi * yi, 0);

            const numerator = n * sumXY - sumX * sumY;
            const denominator = Math.sqrt((n * sumX2 - sumX * sumX) * (n * sumY2 - sumY * sumY));

            return denominator === 0 ? 0 : numerator / denominator;
        },

        getCorrelationStrength(correlation) {
            const abs = Math.abs(correlation);
            if (abs >= 0.8) return 'Very Strong';
            if (abs >= 0.6) return 'Strong';
            if (abs >= 0.4) return 'Moderate';
            if (abs >= 0.2) return 'Weak';
            return 'Very Weak';
        },

        getCorrelationColor(correlation) {
            const abs = Math.abs(correlation);
            if (abs >= 0.8) return 'text-green-600 dark:text-green-400';
            if (abs >= 0.6) return 'text-blue-600 dark:text-blue-400';
            if (abs >= 0.4) return 'text-yellow-600 dark:text-yellow-400';
            if (abs >= 0.2) return 'text-orange-600 dark:text-orange-400';
            return 'text-red-600 dark:text-red-400';
        },

        getSentimentColor(sentiment) {
            if (sentiment >= 70) return 'text-green-600 dark:text-green-400';
            if (sentiment >= 50) return 'text-blue-600 dark:text-blue-400';
            if (sentiment >= 30) return 'text-yellow-600 dark:text-yellow-400';
            return 'text-red-600 dark:text-red-400';
        },

        formatPrice(price) {
            if (price >= 1000000) {
                return (price / 1000000).toFixed(2) + 'M';
            }
            if (price >= 1000) {
                return (price / 1000).toFixed(2) + 'K';
            }
            if (price >= 1) {
                return price.toFixed(2);
            }
            return price.toFixed(6);
        },

        formatTimestamp(timestamp) {
            if (!timestamp) return 'Never';
            return new Date(timestamp).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        getDataPointsForRange(range) {
            switch (range) {
                case '7d': return 168; // Hourly for 7 days
                case '30d': return 720; // Hourly for 30 days
                case '90d': return 360; // Every 6 hours for 90 days
                case '1y': return 365; // Daily for 1 year
                default: return 168;
            }
        },

        getIntervalForRange(range) {
            switch (range) {
                case '7d': return 3600000; // 1 hour
                case '30d': return 3600000; // 1 hour
                case '90d': return 21600000; // 6 hours
                case '1y': return 86400000; // 1 day
                default: return 3600000;
            }
        }
    }
};
</script>

<style scoped>
.sentiment-price-timeline-chart {
    @apply w-full;
}

.chart-container {
    @apply w-full;
}

.chart-header {
    @apply border-b border-gray-200 dark:border-gray-700 pb-6;
}

/* Custom scrollbar for select elements */
select {
    scrollbar-width: thin;
    scrollbar-color: rgb(156 163 175) transparent;
}

select::-webkit-scrollbar {
    width: 8px;
}

select::-webkit-scrollbar-track {
    background: transparent;
}

select::-webkit-scrollbar-thumb {
    background-color: rgb(156 163 175);
    border-radius: 4px;
}

select::-webkit-scrollbar-thumb:hover {
    background-color: rgb(107 114 128);
}

/* Animation for loading spinner */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>