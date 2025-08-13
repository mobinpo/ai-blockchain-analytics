<template>
    <Head title="Multi-Coin Sentiment Correlations" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Multi-Coin Sentiment Correlations
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Compare sentiment correlations across multiple cryptocurrencies
                    </p>
                </div>
                
                <!-- Header Actions -->
                <div class="flex items-center space-x-3">
                    <Link 
                        :href="route('sentiment-analysis.index')"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-panel"
                    >
                        ← Dashboard
                    </Link>
                    <Link 
                        :href="route('sentiment-analysis.chart')"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                    >
                        View Charts
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Analysis Period Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                    <h3 class="text-lg font-medium text-blue-900 mb-2">Analysis Period</h3>
                    <p class="text-blue-800">
                        <strong>{{ correlations.analysis_period?.start_date }}</strong> to 
                        <strong>{{ correlations.analysis_period?.end_date }}</strong>
                        ({{ correlations.analysis_period?.days }} days)
                    </p>
                    <p class="text-sm text-blue-700 mt-1">
                        Correlations calculated using sentiment data from social media and price data from CoinGecko
                    </p>
                </div>

                <!-- Correlation Matrix -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Sentiment vs Price Correlations</h3>
                        
                        <div v-if="correlations.coin_correlations && correlations.coin_correlations.length > 0">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <div 
                                    v-for="coin in correlations.coin_correlations" 
                                    :key="coin.coin_id"
                                    class="bg-panel rounded-lg p-6 hover:bg-ink transition-colors"
                                >
                                    <!-- Coin Header -->
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="font-medium text-gray-900 capitalize">
                                            {{ formatCoinName(coin.coin_id) }}
                                        </h4>
                                        <span class="text-xs text-gray-500">
                                            {{ coin.data_points }} data points
                                        </span>
                                    </div>
                                    
                                    <!-- Correlation Score -->
                                    <div class="text-center mb-4">
                                        <div class="text-3xl font-bold" :class="getCorrelationColor(coin.correlation)">
                                            {{ coin.correlation?.toFixed(3) || 'N/A' }}
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            {{ getCorrelationStrength(coin.correlation) }}
                                        </div>
                                    </div>
                                    
                                    <!-- Correlation Bar -->
                                    <div class="mb-4">
                                        <div class="bg-gray-200 rounded-full h-3 relative">
                                            <div 
                                                class="h-3 rounded-full transition-all duration-500"
                                                :class="getCorrelationBarColor(coin.correlation)"
                                                :style="{ 
                                                    width: Math.abs(coin.correlation || 0) * 100 + '%',
                                                    marginLeft: coin.correlation < 0 ? (50 - Math.abs(coin.correlation) * 50) + '%' : '50%'
                                                }"
                                            ></div>
                                            <!-- Center line -->
                                            <div class="absolute left-1/2 top-0 w-px h-3 bg-gray-400 transform -translate-x-1/2"></div>
                                        </div>
                                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                                            <span>-1.0</span>
                                            <span>0</span>
                                            <span>+1.0</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Stats -->
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Avg Sentiment</span>
                                            <span class="font-medium" :class="getSentimentColor(coin.sentiment_avg)">
                                                {{ coin.sentiment_avg?.toFixed(3) || 'N/A' }}
                                            </span>
                                        </div>
                                        
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Avg Price Change</span>
                                            <span class="font-medium" :class="getPriceChangeColor(coin.price_change_avg)">
                                                {{ coin.price_change_avg >= 0 ? '+' : '' }}{{ coin.price_change_avg?.toFixed(2) || 'N/A' }}%
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <div class="mt-4">
                                        <Link 
                                            :href="route('sentiment-analysis.chart', { coin: coin.coin_id, days: 30 })"
                                            class="block w-full text-center px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-panel transition-colors"
                                        >
                                            View Detailed Chart
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No correlation data available</h3>
                            <p class="mt-1 text-sm text-gray-500">Correlation analysis requires sufficient sentiment and price data.</p>
                        </div>
                    </div>
                </div>

                <!-- Correlation Insights -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Correlation Insights</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Strongest Positive Correlation -->
                            <div v-if="strongestPositiveCorrelation" class="bg-green-50 rounded-lg p-4">
                                <h4 class="font-medium text-green-900 mb-2">Strongest Positive Correlation</h4>
                                <div class="text-2xl font-bold text-green-700 mb-1">
                                    {{ formatCoinName(strongestPositiveCorrelation.coin_id) }}
                                </div>
                                <div class="text-green-600">
                                    {{ strongestPositiveCorrelation.correlation.toFixed(3) }} correlation coefficient
                                </div>
                                <p class="text-sm text-green-800 mt-2">
                                    Sentiment and price movements tend to move in the same direction
                                </p>
                            </div>

                            <!-- Strongest Negative Correlation -->
                            <div v-if="strongestNegativeCorrelation" class="bg-red-50 rounded-lg p-4">
                                <h4 class="font-medium text-red-900 mb-2">Strongest Negative Correlation</h4>
                                <div class="text-2xl font-bold text-red-700 mb-1">
                                    {{ formatCoinName(strongestNegativeCorrelation.coin_id) }}
                                </div>
                                <div class="text-red-600">
                                    {{ strongestNegativeCorrelation.correlation.toFixed(3) }} correlation coefficient
                                </div>
                                <p class="text-sm text-red-800 mt-2">
                                    Sentiment and price movements tend to move in opposite directions
                                </p>
                            </div>

                            <!-- Weakest Correlation -->
                            <div v-if="weakestCorrelation" class="bg-panel rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 mb-2">Weakest Correlation</h4>
                                <div class="text-2xl font-bold text-gray-700 mb-1">
                                    {{ formatCoinName(weakestCorrelation.coin_id) }}
                                </div>
                                <div class="text-gray-600">
                                    {{ weakestCorrelation.correlation.toFixed(3) }} correlation coefficient
                                </div>
                                <p class="text-sm text-gray-800 mt-2">
                                    Sentiment appears to have little relationship with price movements
                                </p>
                            </div>

                            <!-- Average Correlation -->
                            <div v-if="averageCorrelation !== null" class="bg-blue-50 rounded-lg p-4">
                                <h4 class="font-medium text-blue-900 mb-2">Average Correlation</h4>
                                <div class="text-2xl font-bold text-blue-700 mb-1">
                                    {{ averageCorrelation.toFixed(3) }}
                                </div>
                                <div class="text-blue-600">
                                    Across {{ correlations.coin_correlations?.length || 0 }} cryptocurrencies
                                </div>
                                <p class="text-sm text-blue-800 mt-2">
                                    {{ getCorrelationInterpretation(averageCorrelation) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Understanding Correlations -->
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-6">
                    <h3 class="text-lg font-medium text-amber-900 mb-4">Understanding Correlations</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-amber-900 mb-2">Correlation Strength</h4>
                            <ul class="text-sm text-amber-800 space-y-1">
                                <li>• <strong>±0.8 to ±1.0:</strong> Very strong correlation</li>
                                <li>• <strong>±0.6 to ±0.8:</strong> Strong correlation</li>
                                <li>• <strong>±0.4 to ±0.6:</strong> Moderate correlation</li>
                                <li>• <strong>±0.2 to ±0.4:</strong> Weak correlation</li>
                                <li>• <strong>0 to ±0.2:</strong> Very weak/no correlation</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-amber-900 mb-2">Important Notes</h4>
                            <ul class="text-sm text-amber-800 space-y-1">
                                <li>• Correlation doesn't imply causation</li>
                                <li>• Market conditions affect correlation strength</li>
                                <li>• External events can disrupt typical patterns</li>
                                <li>• Use multiple timeframes for validation</li>
                                <li>• Consider volume and volatility alongside correlation</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// Props
const props = defineProps({
    correlations: {
        type: Object,
        default: () => ({
            coin_correlations: [],
            analysis_period: {
                start_date: '',
                end_date: '',
                days: 0
            }
        })
    }
})

// Computed properties
const strongestPositiveCorrelation = computed(() => {
    if (!props.correlations.coin_correlations) return null
    return props.correlations.coin_correlations
        .filter(coin => coin.correlation > 0)
        .sort((a, b) => b.correlation - a.correlation)[0]
})

const strongestNegativeCorrelation = computed(() => {
    if (!props.correlations.coin_correlations) return null
    return props.correlations.coin_correlations
        .filter(coin => coin.correlation < 0)
        .sort((a, b) => a.correlation - b.correlation)[0]
})

const weakestCorrelation = computed(() => {
    if (!props.correlations.coin_correlations) return null
    return props.correlations.coin_correlations
        .sort((a, b) => Math.abs(a.correlation) - Math.abs(b.correlation))[0]
})

const averageCorrelation = computed(() => {
    if (!props.correlations.coin_correlations || props.correlations.coin_correlations.length === 0) return null
    const sum = props.correlations.coin_correlations.reduce((acc, coin) => acc + coin.correlation, 0)
    return sum / props.correlations.coin_correlations.length
})

// Methods
const formatCoinName = (coinId) => {
    const names = {
        'bitcoin': 'Bitcoin',
        'ethereum': 'Ethereum',
        'binancecoin': 'BNB',
        'cardano': 'Cardano',
        'solana': 'Solana',
        'polkadot': 'Polkadot',
        'chainlink': 'Chainlink',
        'polygon': 'Polygon'
    }
    return names[coinId] || coinId.charAt(0).toUpperCase() + coinId.slice(1)
}

const getCorrelationColor = (correlation) => {
    if (!correlation) return 'text-gray-500'
    const abs = Math.abs(correlation)
    if (abs >= 0.6) return correlation > 0 ? 'text-green-600' : 'text-red-600'
    if (abs >= 0.3) return correlation > 0 ? 'text-green-500' : 'text-red-500'
    return 'text-gray-500'
}

const getCorrelationBarColor = (correlation) => {
    if (!correlation) return 'bg-gray-400'
    const abs = Math.abs(correlation)
    if (abs >= 0.6) return correlation > 0 ? 'bg-green-500' : 'bg-red-500'
    if (abs >= 0.3) return correlation > 0 ? 'bg-green-400' : 'bg-red-400'
    return 'bg-gray-400'
}

const getCorrelationStrength = (correlation) => {
    if (!correlation) return 'No Data'
    const abs = Math.abs(correlation)
    const strength = abs >= 0.8 ? 'Very Strong' :
                    abs >= 0.6 ? 'Strong' :
                    abs >= 0.4 ? 'Moderate' :
                    abs >= 0.2 ? 'Weak' : 'Very Weak'
    const direction = correlation > 0 ? 'Positive' : 'Negative'
    return `${strength} ${direction}`
}

const getSentimentColor = (sentiment) => {
    if (!sentiment) return 'text-gray-500'
    if (sentiment > 0.2) return 'text-green-600'
    if (sentiment < -0.2) return 'text-red-600'
    return 'text-gray-600'
}

const getPriceChangeColor = (change) => {
    if (!change) return 'text-gray-500'
    return change > 0 ? 'text-green-600' : 'text-red-600'
}

const getCorrelationInterpretation = (correlation) => {
    const abs = Math.abs(correlation)
    if (abs >= 0.6) {
        return correlation > 0 ? 
            'Strong positive relationship between sentiment and prices' :
            'Strong negative relationship between sentiment and prices'
    } else if (abs >= 0.3) {
        return correlation > 0 ?
            'Moderate positive relationship between sentiment and prices' :
            'Moderate negative relationship between sentiment and prices'
    } else {
        return 'Weak relationship between sentiment and price movements'
    }
}

// Route helper
const route = (name, params = {}) => {
    const routes = {
        'sentiment-analysis.index': '/sentiment-analysis',
        'sentiment-analysis.chart': '/sentiment-analysis/chart'
    }
    
    let url = routes[name] || '#'
    
    if (Object.keys(params).length > 0) {
        const searchParams = new URLSearchParams(params)
        url += '?' + searchParams.toString()
    }
    
    return url
}
</script>