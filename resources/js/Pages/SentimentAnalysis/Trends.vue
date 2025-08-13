<template>
    <Head title="Sentiment Trends Analysis" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Sentiment Trends Analysis
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Long-term sentiment trends and historical patterns
                    </p>
                </div>
                
                <!-- Header Actions -->
                <div class="flex items-center space-x-3">
                    <select 
                        v-model="selectedTimeframe" 
                        @change="updateTimeframe"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500"
                    >
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="90d">Last 90 Days</option>
                        <option value="180d">Last 6 Months</option>
                        <option value="365d">Last Year</option>
                    </select>
                    
                    <Link 
                        :href="route('sentiment-analysis.index')"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-panel"
                    >
                        ← Dashboard
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Current Period Overview -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">
                            Current Period Overview
                            <span class="text-sm text-gray-500 ml-2">
                                ({{ trends.current_period?.start_date }} to {{ trends.current_period?.end_date }})
                            </span>
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold" :class="getSentimentColor(trends.current_period?.overall_stats?.average_sentiment)">
                                    {{ trends.current_period?.overall_stats?.average_sentiment?.toFixed(3) || 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-500">Average Sentiment</div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-3xl font-bold text-gray-900">
                                    {{ formatNumber(trends.current_period?.overall_stats?.total_posts) }}
                                </div>
                                <div class="text-sm text-gray-500">Total Posts</div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-3xl font-bold text-gray-900">
                                    {{ trends.current_period?.overall_stats?.days_with_data || 0 }}
                                </div>
                                <div class="text-sm text-gray-500">Days with Data</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comparison with Previous Period -->
                <div v-if="trends.comparison_period" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">
                            Comparison with Previous Period
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="text-center">
                                <div class="text-lg text-gray-600 mb-2">Sentiment Change</div>
                                <div class="text-2xl font-bold" :class="getChangeColor(sentimentChange)">
                                    {{ sentimentChange >= 0 ? '+' : '' }}{{ sentimentChange?.toFixed(3) || 'N/A' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ trends.comparison_period.overall_stats.average_sentiment?.toFixed(3) }} → 
                                    {{ trends.current_period.overall_stats.average_sentiment?.toFixed(3) }}
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-lg text-gray-600 mb-2">Volume Change</div>
                                <div class="text-2xl font-bold" :class="getChangeColor(volumeChange)">
                                    {{ volumeChange >= 0 ? '+' : '' }}{{ volumeChangePercentage }}%
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ formatNumber(trends.comparison_period.overall_stats.total_posts) }} → 
                                    {{ formatNumber(trends.current_period.overall_stats.total_posts) }}
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-lg text-gray-600 mb-2">Data Coverage</div>
                                <div class="text-2xl font-bold text-gray-900">
                                    {{ dataCoveragePercentage }}%
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ trends.current_period.overall_stats.days_with_data }} of {{ expectedDays }} days
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Platform Breakdown -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Platform Breakdown</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div 
                                v-for="platform in trends.current_period?.platform_breakdown || []" 
                                :key="platform.platform"
                                class="bg-panel rounded-lg p-4"
                            >
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-medium text-gray-900 capitalize">
                                        {{ platform.platform }}
                                    </h4>
                                    <span class="text-xs text-gray-500">
                                        {{ formatNumber(platform.total_posts) }} posts
                                    </span>
                                </div>
                                
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Avg Sentiment</span>
                                        <span class="text-sm font-medium" :class="getSentimentColor(platform.average_sentiment)">
                                            {{ platform.average_sentiment?.toFixed(3) }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Range</span>
                                        <span class="text-xs text-gray-500">
                                            {{ platform.sentiment_range?.min?.toFixed(2) }} to {{ platform.sentiment_range?.max?.toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Mini sentiment bar -->
                                <div class="mt-3 h-2 bg-gray-200 rounded-full">
                                    <div 
                                        class="h-2 rounded-full transition-all duration-500"
                                        :class="getSentimentBarColor(platform.average_sentiment)"
                                        :style="{ width: getSentimentBarWidth(platform.average_sentiment) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline Chart -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Daily Timeline</h3>
                        
                        <div class="overflow-x-auto">
                            <div class="flex items-end space-x-1 h-32 min-w-full" style="min-width: 800px;">
                                <div 
                                    v-for="(day, index) in trends.current_period?.daily_timeline || []" 
                                    :key="index"
                                    class="flex-1 bg-gray-200 rounded-t relative group cursor-pointer"
                                    :class="getSentimentBarColor(day.sentiment)"
                                    :style="{ height: getTimelineBarHeight(day.sentiment) + '%' }"
                                >
                                    <!-- Tooltip -->
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        <div class="bg-panel text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                            <div>{{ new Date(day.date).toLocaleDateString() }}</div>
                                            <div>{{ day.platform }}</div>
                                            <div>Sentiment: {{ day.sentiment?.toFixed(3) }}</div>
                                            <div>Posts: {{ formatNumber(day.posts) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between text-xs text-gray-500 mt-2">
                            <span>{{ trends.current_period?.start_date }}</span>
                            <span>{{ trends.current_period?.end_date }}</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-panel rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Explore Further</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <Link 
                            :href="route('sentiment-analysis.platform', { platform: 'twitter' })"
                            class="text-center p-3 bg-white rounded border hover:bg-panel"
                        >
                            <div class="font-medium">Twitter Analysis</div>
                            <div class="text-sm text-gray-500">Platform-specific trends</div>
                        </Link>
                        
                        <Link 
                            :href="route('sentiment-analysis.platform', { platform: 'reddit' })"
                            class="text-center p-3 bg-white rounded border hover:bg-panel"
                        >
                            <div class="font-medium">Reddit Analysis</div>
                            <div class="text-sm text-gray-500">Community sentiment</div>
                        </Link>
                        
                        <Link 
                            :href="route('sentiment-analysis.chart')"
                            class="text-center p-3 bg-white rounded border hover:bg-panel"
                        >
                            <div class="font-medium">Price Correlation</div>
                            <div class="text-sm text-gray-500">vs crypto prices</div>
                        </Link>
                        
                        <Link 
                            :href="route('sentiment-analysis.correlations')"
                            class="text-center p-3 bg-white rounded border hover:bg-panel"
                        >
                            <div class="font-medium">Multi-Coin Analysis</div>
                            <div class="text-sm text-gray-500">Compare cryptocurrencies</div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// Props
const props = defineProps({
    timeframe: {
        type: String,
        default: '30d'
    },
    comparison: {
        type: String,
        default: 'none'
    },
    trends: {
        type: Object,
        default: () => ({
            current_period: null,
            comparison_period: null,
            timeframe: '30d',
            comparison_type: 'none'
        })
    }
})

// Reactive state
const selectedTimeframe = ref(props.timeframe)

// Computed properties
const sentimentChange = computed(() => {
    if (!props.trends.current_period || !props.trends.comparison_period) return null
    return props.trends.current_period.overall_stats.average_sentiment - 
           props.trends.comparison_period.overall_stats.average_sentiment
})

const volumeChange = computed(() => {
    if (!props.trends.current_period || !props.trends.comparison_period) return null
    return props.trends.current_period.overall_stats.total_posts - 
           props.trends.comparison_period.overall_stats.total_posts
})

const volumeChangePercentage = computed(() => {
    if (!props.trends.current_period || !props.trends.comparison_period) return 0
    const prev = props.trends.comparison_period.overall_stats.total_posts
    const curr = props.trends.current_period.overall_stats.total_posts
    if (prev === 0) return 0
    return Math.round(((curr - prev) / prev) * 100)
})

const expectedDays = computed(() => {
    const days = {
        '7d': 7,
        '30d': 30,
        '90d': 90,
        '180d': 180,
        '365d': 365
    }
    return days[props.timeframe] || 30
})

const dataCoveragePercentage = computed(() => {
    if (!props.trends.current_period) return 0
    const actual = props.trends.current_period.overall_stats.days_with_data
    return Math.round((actual / expectedDays.value) * 100)
})

// Methods
const getSentimentColor = (sentiment) => {
    if (!sentiment) return 'text-gray-500'
    if (sentiment > 0.2) return 'text-green-600'
    if (sentiment < -0.2) return 'text-red-600'
    return 'text-gray-600'
}

const getChangeColor = (change) => {
    if (!change) return 'text-gray-500'
    return change > 0 ? 'text-green-600' : 'text-red-600'
}

const getSentimentBarColor = (sentiment) => {
    if (!sentiment) return 'bg-gray-400'
    if (sentiment > 0.2) return 'bg-green-400 hover:bg-green-500'
    if (sentiment < -0.2) return 'bg-red-400 hover:bg-red-500'
    return 'bg-gray-400 hover:bg-panel'
}

const getSentimentBarWidth = (sentiment) => {
    // Convert sentiment (-1 to 1) to width percentage (0 to 100)
    return ((sentiment + 1) / 2) * 100
}

const getTimelineBarHeight = (sentiment) => {
    // Convert sentiment (-1 to 1) to height percentage (10% to 90%)
    return ((sentiment + 1) / 2) * 80 + 10
}

const formatNumber = (num) => {
    if (!num) return '0'
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
    return num.toString()
}

const updateTimeframe = () => {
    router.get(route('sentiment-analysis.trends'), {
        timeframe: selectedTimeframe.value,
        comparison: 'previous'
    })
}

// Route helper
const route = (name, params = {}) => {
    const routes = {
        'sentiment-analysis.index': '/sentiment-analysis',
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