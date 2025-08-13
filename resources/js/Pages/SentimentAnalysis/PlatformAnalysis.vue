<template>
    <Head :title="`${platform.charAt(0).toUpperCase() + platform.slice(1)} Sentiment Analysis`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ platform.charAt(0).toUpperCase() + platform.slice(1) }} Sentiment Analysis
                        <span v-if="category !== 'all'" class="text-sm text-gray-500 ml-2">
                            ({{ category.charAt(0).toUpperCase() + category.slice(1) }})
                        </span>
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Deep dive into platform-specific sentiment trends and patterns
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
                <!-- Summary Statistics -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Summary Statistics</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold" :class="getSentimentColor(analysis.summary.average_sentiment)">
                                    {{ analysis.summary.average_sentiment?.toFixed(3) || 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-500">Average Sentiment</div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900">
                                    {{ formatNumber(analysis.summary.total_posts) }}
                                </div>
                                <div class="text-sm text-gray-500">Total Posts</div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900">
                                    {{ analysis.summary.total_days }}
                                </div>
                                <div class="text-sm text-gray-500">Days Analyzed</div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-600">
                                    {{ analysis.summary.sentiment_range?.min?.toFixed(3) || 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-500">Min Sentiment</div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-600">
                                    {{ analysis.summary.sentiment_range?.max?.toFixed(3) || 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-500">Max Sentiment</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sentiment Distribution -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Sentiment Distribution</h3>
                        
                        <div class="space-y-4">
                            <!-- Very Positive -->
                            <div class="flex items-center">
                                <div class="w-24 text-sm text-gray-600">Very Positive</div>
                                <div class="flex-1 mx-4">
                                    <div class="bg-gray-200 rounded-full h-4 relative">
                                        <div 
                                            class="bg-green-500 h-4 rounded-full transition-all duration-500"
                                            :style="{ width: getPercentage(analysis.sentiment_distribution.very_positive) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <div class="w-16 text-sm text-gray-900 text-right">
                                    {{ formatNumber(analysis.sentiment_distribution.very_positive) }}
                                </div>
                            </div>

                            <!-- Positive -->
                            <div class="flex items-center">
                                <div class="w-24 text-sm text-gray-600">Positive</div>
                                <div class="flex-1 mx-4">
                                    <div class="bg-gray-200 rounded-full h-4 relative">
                                        <div 
                                            class="bg-green-400 h-4 rounded-full transition-all duration-500"
                                            :style="{ width: getPercentage(analysis.sentiment_distribution.positive) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <div class="w-16 text-sm text-gray-900 text-right">
                                    {{ formatNumber(analysis.sentiment_distribution.positive) }}
                                </div>
                            </div>

                            <!-- Neutral -->
                            <div class="flex items-center">
                                <div class="w-24 text-sm text-gray-600">Neutral</div>
                                <div class="flex-1 mx-4">
                                    <div class="bg-gray-200 rounded-full h-4 relative">
                                        <div 
                                            class="bg-gray-400 h-4 rounded-full transition-all duration-500"
                                            :style="{ width: getPercentage(analysis.sentiment_distribution.neutral) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <div class="w-16 text-sm text-gray-900 text-right">
                                    {{ formatNumber(analysis.sentiment_distribution.neutral) }}
                                </div>
                            </div>

                            <!-- Negative -->
                            <div class="flex items-center">
                                <div class="w-24 text-sm text-gray-600">Negative</div>
                                <div class="flex-1 mx-4">
                                    <div class="bg-gray-200 rounded-full h-4 relative">
                                        <div 
                                            class="bg-red-400 h-4 rounded-full transition-all duration-500"
                                            :style="{ width: getPercentage(analysis.sentiment_distribution.negative) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <div class="w-16 text-sm text-gray-900 text-right">
                                    {{ formatNumber(analysis.sentiment_distribution.negative) }}
                                </div>
                            </div>

                            <!-- Very Negative -->
                            <div class="flex items-center">
                                <div class="w-24 text-sm text-gray-600">Very Negative</div>
                                <div class="flex-1 mx-4">
                                    <div class="bg-gray-200 rounded-full h-4 relative">
                                        <div 
                                            class="bg-red-500 h-4 rounded-full transition-all duration-500"
                                            :style="{ width: getPercentage(analysis.sentiment_distribution.very_negative) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <div class="w-16 text-sm text-gray-900 text-right">
                                    {{ formatNumber(analysis.sentiment_distribution.very_negative) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Breakdown -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Daily Breakdown</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-panel">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Sentiment
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Posts
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Volatility
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Distribution
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="day in analysis.daily_breakdown" :key="day.date">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ new Date(day.date).toLocaleDateString() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm" :class="getSentimentColor(day.sentiment)">
                                            {{ day.sentiment.toFixed(3) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatNumber(day.posts) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            {{ day.volatility.toFixed(3) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex space-x-1">
                                                <div class="w-3 h-3 bg-green-500 rounded-full" :title="`Very Positive: ${day.distribution.very_positive}`"></div>
                                                <div class="w-3 h-3 bg-green-400 rounded-full" :title="`Positive: ${day.distribution.positive}`"></div>
                                                <div class="w-3 h-3 bg-gray-400 rounded-full" :title="`Neutral: ${day.distribution.neutral}`"></div>
                                                <div class="w-3 h-3 bg-red-400 rounded-full" :title="`Negative: ${day.distribution.negative}`"></div>
                                                <div class="w-3 h-3 bg-red-500 rounded-full" :title="`Very Negative: ${day.distribution.very_negative}`"></div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Filter Options -->
                <div class="bg-panel rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Explore Different Views</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link 
                            :href="route('sentiment-analysis.platform', { platform, category, days: 7 })"
                            class="text-center p-3 bg-white rounded border hover:bg-panel"
                        >
                            <div class="font-medium">Last 7 Days</div>
                            <div class="text-sm text-gray-500">Short-term trends</div>
                        </Link>
                        
                        <Link 
                            :href="route('sentiment-analysis.platform', { platform, category, days: 90 })"
                            class="text-center p-3 bg-white rounded border hover:bg-panel"
                        >
                            <div class="font-medium">Last 90 Days</div>
                            <div class="text-sm text-gray-500">Long-term analysis</div>
                        </Link>
                        
                        <Link 
                            :href="route('sentiment-analysis.chart', { platform, category })"
                            class="text-center p-3 bg-white rounded border hover:bg-panel"
                        >
                            <div class="font-medium">Price Correlation</div>
                            <div class="text-sm text-gray-500">vs cryptocurrency prices</div>
                        </Link>
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
    platform: {
        type: String,
        default: 'all'
    },
    category: {
        type: String,
        default: 'all'
    },
    days: {
        type: Number,
        default: 30
    },
    analysis: {
        type: Object,
        default: () => ({
            summary: {
                total_days: 0,
                total_posts: 0,
                average_sentiment: 0,
                sentiment_range: { min: 0, max: 0 },
                average_volatility: 0
            },
            daily_breakdown: [],
            sentiment_distribution: {
                very_positive: 0,
                positive: 0,
                neutral: 0,
                negative: 0,
                very_negative: 0
            }
        })
    }
})

// Computed
const totalSentimentPosts = computed(() => {
    const dist = props.analysis.sentiment_distribution
    return dist.very_positive + dist.positive + dist.neutral + dist.negative + dist.very_negative
})

// Methods
const getSentimentColor = (sentiment) => {
    if (!sentiment) return 'text-gray-500'
    if (sentiment > 0.2) return 'text-green-600'
    if (sentiment < -0.2) return 'text-red-600'
    return 'text-gray-600'
}

const formatNumber = (num) => {
    if (!num) return '0'
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
    return num.toString()
}

const getPercentage = (value) => {
    if (!value || totalSentimentPosts.value === 0) return 0
    return (value / totalSentimentPosts.value) * 100
}

// Route helper
const route = (name, params = {}) => {
    const routes = {
        'sentiment-analysis.index': '/sentiment-analysis',
        'sentiment-analysis.chart': '/sentiment-analysis/chart',
        'sentiment-analysis.platform': '/sentiment-analysis/platform'
    }
    
    let url = routes[name] || '#'
    
    if (Object.keys(params).length > 0) {
        const searchParams = new URLSearchParams(params)
        url += '?' + searchParams.toString()
    }
    
    return url
}
</script>