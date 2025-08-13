<template>
    <Head title="Sentiment Shield - Analysis Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        🛡️ Sentiment Shield Analysis Dashboard
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        AI-powered social sentiment monitoring with blockchain security insights
                    </p>
                </div>
                
                <!-- Header Actions -->
                <div class="flex items-center space-x-3">
                    <Link 
                        :href="route('sentiment-analysis.chart')"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
                    >
                        <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        View Charts
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Recent Sentiment Overview -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Recent Sentiment Overview</h3>
                        
                        <!-- Key Metrics -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold" :class="getSentimentColor(recentSentiment.current_sentiment)">
                                    {{ recentSentiment.current_sentiment?.toFixed(3) || 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-500">Current Sentiment</div>
                                <div class="text-xs mt-1" :class="getTrendColor(recentSentiment.trend)">
                                    {{ getTrendLabel(recentSentiment.trend) }}
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-2xl font-bold" :class="getChangeColor(recentSentiment.change_7d)">
                                    {{ recentSentiment.change_7d >= 0 ? '+' : '' }}{{ recentSentiment.change_7d?.toFixed(3) || 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-500">7-Day Change</div>
                                <div class="text-xs text-gray-400 mt-1">vs last week</div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900">
                                    {{ formatNumber(recentSentiment.total_posts_7d) }}
                                </div>
                                <div class="text-sm text-gray-500">Total Posts</div>
                                <div class="text-xs text-gray-400 mt-1">last 7 days</div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900">
                                    {{ recentSentiment.daily_data?.length || 0 }}
                                </div>
                                <div class="text-sm text-gray-500">Days Tracked</div>
                                <div class="text-xs text-gray-400 mt-1">with data</div>
                            </div>
                        </div>

                        <!-- Mini Timeline Chart -->
                        <div v-if="recentSentiment.daily_data && recentSentiment.daily_data.length > 0">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">7-Day Sentiment Trend</h4>
                            <div class="flex items-end space-x-1 h-20">
                                <div 
                                    v-for="(day, index) in recentSentiment.daily_data" 
                                    :key="index"
                                    class="flex-1 bg-gray-200 rounded-t relative group cursor-pointer transition-colors"
                                    :class="getSentimentBarColor(day.sentiment)"
                                    :style="{ height: getBarHeight(day.sentiment) + '%' }"
                                >
                                    <!-- Tooltip -->
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                        <div class="bg-panel text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                            <div>{{ new Date(day.date).toLocaleDateString() }}</div>
                                            <div>Sentiment: {{ day.sentiment.toFixed(3) }}</div>
                                            <div>Posts: {{ formatNumber(day.posts) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>{{ new Date(recentSentiment.daily_data[0]?.date).toLocaleDateString() }}</span>
                                <span>{{ new Date(recentSentiment.daily_data[recentSentiment.daily_data.length - 1]?.date).toLocaleDateString() }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Sentiment vs Price Chart -->
                    <Link 
                        :href="route('sentiment-analysis.chart', { coin: 'bitcoin', days: 30 })"
                        class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Price Correlation</h3>
                                <p class="text-sm text-gray-500">Analyze sentiment vs cryptocurrency prices</p>
                            </div>
                        </div>
                    </Link>

                    <!-- Platform Analysis -->
                    <Link 
                        :href="route('sentiment-analysis.platform', { platform: 'twitter', days: 30 })"
                        class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Platform Analysis</h3>
                                <p class="text-sm text-gray-500">Deep dive into platform-specific sentiment</p>
                            </div>
                        </div>
                    </Link>

                    <!-- Trend Analysis -->
                    <Link 
                        :href="route('sentiment-analysis.trends', { timeframe: '90d' })"
                        class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Trend Analysis</h3>
                                <p class="text-sm text-gray-500">Long-term sentiment trends and patterns</p>
                            </div>
                        </div>
                    </Link>

                    <!-- Multi-Coin Correlations -->
                    <Link 
                        :href="route('sentiment-analysis.correlations')"
                        class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Multi-Coin Analysis</h3>
                                <p class="text-sm text-gray-500">Compare sentiment across cryptocurrencies</p>
                            </div>
                        </div>
                    </Link>

                    <!-- Twitter Analysis -->
                    <Link 
                        :href="route('sentiment-analysis.platform', { platform: 'twitter', category: 'blockchain' })"
                        class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-cyan-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-cyan-600" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Twitter Sentiment</h3>
                                <p class="text-sm text-gray-500">Twitter-specific blockchain sentiment</p>
                            </div>
                        </div>
                    </Link>

                    <!-- Reddit Analysis -->
                    <Link 
                        :href="route('sentiment-analysis.platform', { platform: 'reddit', category: 'defi' })"
                        class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Reddit Sentiment</h3>
                                <p class="text-sm text-gray-500">Reddit DeFi community sentiment</p>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Platform Breakdown -->
                <div v-if="availableFilters.platforms.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Available Data Sources</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Platforms -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-3">Platforms</h4>
                                <div class="space-y-2">
                                    <div 
                                        v-for="platform in availableFilters.platforms" 
                                        :key="platform"
                                        class="flex items-center justify-between p-3 bg-panel rounded-lg"
                                    >
                                        <div class="flex items-center">
                                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-3"></div>
                                            <span class="text-sm font-medium text-gray-900 capitalize">
                                                {{ platform }}
                                            </span>
                                        </div>
                                        <Link 
                                            :href="route('sentiment-analysis.platform', { platform })"
                                            class="text-xs text-brand-500 hover:text-indigo-500"
                                        >
                                            View Analysis →
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <!-- Categories -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-3">Categories</h4>
                                <div class="space-y-2">
                                    <div 
                                        v-for="category in availableFilters.categories" 
                                        :key="category"
                                        class="flex items-center justify-between p-3 bg-panel rounded-lg"
                                    >
                                        <div class="flex items-center">
                                            <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                                            <span class="text-sm font-medium text-gray-900 capitalize">
                                                {{ category }}
                                            </span>
                                        </div>
                                        <Link 
                                            :href="route('sentiment-analysis.platform', { category })"
                                            class="text-xs text-brand-500 hover:text-indigo-500"
                                        >
                                            View Analysis →
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Getting Started -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h3 class="text-lg font-medium text-blue-900 mb-4">Getting Started</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-blue-900 mb-2">Understanding Sentiment Scores</h4>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li>• <strong>+0.6 to +1.0:</strong> Very positive sentiment</li>
                                <li>• <strong>+0.2 to +0.6:</strong> Positive sentiment</li>
                                <li>• <strong>-0.2 to +0.2:</strong> Neutral sentiment</li>
                                <li>• <strong>-0.6 to -0.2:</strong> Negative sentiment</li>
                                <li>• <strong>-1.0 to -0.6:</strong> Very negative sentiment</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-medium text-blue-900 mb-2">Analysis Tips</h4>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li>• Compare sentiment across multiple timeframes</li>
                                <li>• Look for correlation with major market events</li>
                                <li>• Consider volume and volatility alongside sentiment</li>
                                <li>• Use platform-specific analysis for deeper insights</li>
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
    recentSentiment: {
        type: Object,
        default: () => ({
            current_sentiment: 0,
            trend: 'neutral',
            change_7d: 0,
            total_posts_7d: 0,
            daily_data: []
        })
    },
    availableFilters: {
        type: Object,
        default: () => ({
            platforms: [],
            categories: []
        })
    }
})

// Methods
const getSentimentColor = (sentiment) => {
    if (!sentiment) return 'text-gray-500'
    if (sentiment > 0.2) return 'text-green-600'
    if (sentiment < -0.2) return 'text-red-600'
    return 'text-gray-600'
}

const getTrendColor = (trend) => {
    return {
        'positive': 'text-green-600',
        'negative': 'text-red-600',
        'neutral': 'text-gray-600'
    }[trend] || 'text-gray-600'
}

const getTrendLabel = (trend) => {
    return {
        'positive': '↗ Trending Up',
        'negative': '↘ Trending Down', 
        'neutral': '→ Stable'
    }[trend] || 'No Trend'
}

const getChangeColor = (change) => {
    if (!change) return 'text-gray-500'
    return change > 0 ? 'text-green-600' : 'text-red-600'
}

const getSentimentBarColor = (sentiment) => {
    if (sentiment > 0.2) return 'bg-green-400 hover:bg-green-500'
    if (sentiment < -0.2) return 'bg-red-400 hover:bg-red-500'
    return 'bg-gray-400 hover:bg-panel'
}

const getBarHeight = (sentiment) => {
    // Normalize sentiment (-1 to 1) to height percentage (10% to 90%)
    return ((sentiment + 1) / 2) * 80 + 10
}

const formatNumber = (num) => {
    if (!num) return '0'
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
    return num.toString()
}

// Route helper
const route = (name, params = {}) => {
    const routes = {
        'sentiment-analysis.chart': '/sentiment-analysis/chart',
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
</script>

<style scoped>
/* Custom tooltip arrow */
.group:hover .absolute::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #1f2937 transparent transparent transparent;
}

/* Smooth transitions */
.transition-shadow {
    transition: box-shadow 0.15s ease-in-out;
}

.transition-colors {
    transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
}

/* Responsive grid adjustments */
@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>