<template>
    <div class="space-y-6">
        <!-- Live Market Selection -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Cryptocurrency</label>
                <select v-model="selectedCrypto" @change="updateLiveData" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500">
                    <option value="bitcoin">Bitcoin (BTC)</option>
                    <option value="ethereum">Ethereum (ETH)</option>
                    <option value="cardano">Cardano (ADA)</option>
                    <option value="solana">Solana (SOL)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Social Platform</label>
                <select v-model="selectedPlatform" @change="updateLiveData"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500">
                    <option value="all">All Platforms</option>
                    <option value="twitter">Twitter</option>
                    <option value="reddit">Reddit</option>
                    <option value="telegram">Telegram</option>
                </select>
            </div>
        </div>

        <!-- Live Stats Dashboard -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-blue-600">{{ liveStats.sentimentScore }}</div>
                <div class="text-xs text-blue-700">Sentiment Score</div>
                <div class="flex items-center justify-center mt-1">
                    <div class="h-2 w-2 bg-blue-400 rounded-full animate-pulse mr-1"></div>
                    <span class="text-xs text-blue-600">Live</span>
                </div>
            </div>
            
            <div class="bg-green-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-green-600">${{ liveStats.currentPrice }}</div>
                <div class="text-xs text-green-700">Current Price</div>
                <div class="text-xs" :class="liveStats.priceChange >= 0 ? 'text-green-600' : 'text-red-600'">
                    {{ liveStats.priceChange >= 0 ? '+' : '' }}{{ liveStats.priceChange }}%
                </div>
            </div>
            
            <div class="bg-purple-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-purple-600">{{ liveStats.mentions }}</div>
                <div class="text-xs text-purple-700">Mentions/Hour</div>
                <div class="text-xs text-purple-600">+{{ liveStats.mentionGrowth }}%</div>
            </div>
            
            <div class="bg-yellow-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-yellow-600">{{ liveStats.correlation }}</div>
                <div class="text-xs text-yellow-700">Correlation</div>
                <div class="text-xs text-yellow-600">{{ getCorrelationStrength(liveStats.correlation) }}</div>
            </div>
        </div>

        <!-- Live Sentiment Feed -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Real-time Posts -->
            <div class="bg-white border border-gray-200 rounded-lg">
                <div class="p-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h4 class="font-semibold text-gray-900">Live Social Feed</h4>
                        <div class="flex items-center space-x-2">
                            <div class="h-2 w-2 bg-green-400 rounded-full animate-pulse"></div>
                            <span class="text-xs text-green-600">Live Updates</span>
                        </div>
                    </div>
                </div>
                <div class="p-4 max-h-80 overflow-y-auto">
                    <div class="space-y-3">
                        <div v-for="post in livePosts" :key="post.id" 
                             class="flex items-start space-x-3 p-3 bg-panel rounded-lg"
                             :class="{ 'animate-pulse': post.isNew }">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     :class="getPlatformColor(post.platform)">
                                    <span class="text-xs font-bold text-white">{{ post.platform[0].toUpperCase() }}</span>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-2 mb-1">
                                    <span class="text-sm font-medium text-gray-900">@{{ post.username }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                          :class="getSentimentBadge(post.sentiment)">
                                        {{ post.sentiment > 0.3 ? 'Positive' : post.sentiment < -0.3 ? 'Negative' : 'Neutral' }}
                                    </span>
                                    <span class="text-xs text-gray-500">{{ post.timeAgo }}</span>
                                </div>
                                <p class="text-sm text-gray-700">{{ post.content }}</p>
                                <div class="mt-1 text-xs text-gray-500">
                                    Sentiment: {{ post.sentiment.toFixed(2) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sentiment Trends Chart -->
            <div class="bg-white border border-gray-200 rounded-lg">
                <div class="p-4 border-b border-gray-200">
                    <h4 class="font-semibold text-gray-900">Sentiment vs Price Trend</h4>
                </div>
                <div class="p-4">
                    <!-- Simple trend visualization -->
                    <div class="space-y-4">
                        <div v-for="(point, index) in trendData" :key="index" 
                             class="flex items-center justify-between p-2 bg-panel rounded">
                            <div class="flex items-center space-x-3">
                                <div class="text-xs text-gray-500">{{ point.time }}</div>
                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all duration-500"
                                         :class="point.sentiment > 0 ? 'bg-green-400' : 'bg-red-400'"
                                         :style="{ width: Math.abs(point.sentiment) * 100 + '%' }"></div>
                                </div>
                            </div>
                            <div class="text-sm font-medium"
                                 :class="point.priceChange >= 0 ? 'text-green-600' : 'text-red-600'">
                                {{ point.priceChange >= 0 ? '+' : '' }}{{ point.priceChange.toFixed(2) }}%
                            </div>
                        </div>
                    </div>
                    
                    <!-- Correlation Insight -->
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center space-x-2 mb-2">
                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium text-blue-900">AI Insight</span>
                        </div>
                        <p class="text-sm text-blue-800">{{ getAIInsight() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Analysis Controls -->
        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Real-time Analysis Engine</h4>
                    <p class="text-sm text-gray-600">Processing {{ liveStats.mentions }} posts per hour with 94.7% accuracy</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button @click="pauseLiveUpdates" 
                            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-panel">
                        {{ isPaused ? 'Resume' : 'Pause' }} Live Updates
                    </button>
                    <button @click="exportLiveData"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Export Data
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

// Demo state
const selectedCrypto = ref('bitcoin')
const selectedPlatform = ref('all')
const isPaused = ref(false)

const liveStats = ref({
    sentimentScore: 0.647,
    currentPrice: 43250,
    priceChange: 2.34,
    mentions: 2847,
    mentionGrowth: 15,
    correlation: 0.73
})

const livePosts = ref([
    {
        id: 1,
        platform: 'twitter',
        username: 'crypto_trader_99',
        content: 'Bitcoin looking strong today! 🚀 Breaking resistance levels',
        sentiment: 0.85,
        timeAgo: '2m ago',
        isNew: false
    },
    {
        id: 2,
        platform: 'reddit',
        username: 'defi_enthusiast',
        content: 'Interesting analysis on BTC market dynamics. Bullish long term.',
        sentiment: 0.62,
        timeAgo: '5m ago',
        isNew: false
    },
    {
        id: 3,
        platform: 'telegram',
        username: 'moon_boy_2024',
        content: 'Not sure about this pump, feels like manipulation to me',
        sentiment: -0.34,
        timeAgo: '8m ago',
        isNew: false
    }
])

const trendData = ref([
    { time: '12:00', sentiment: 0.65, priceChange: 1.2 },
    { time: '12:15', sentiment: 0.72, priceChange: 2.1 },
    { time: '12:30', sentiment: 0.58, priceChange: 1.8 },
    { time: '12:45', sentiment: 0.81, priceChange: 2.9 },
    { time: '13:00', sentiment: 0.69, priceChange: 2.3 }
])

let updateInterval

// Methods
const updateLiveData = () => {
    // Simulate different data for different cryptos/platforms
    const variance = Math.random() * 0.2 - 0.1 // ±10% variance
    
    liveStats.value.sentimentScore = Math.max(0, Math.min(1, liveStats.value.sentimentScore + variance))
    liveStats.value.priceChange += (Math.random() - 0.5) * 0.5
    liveStats.value.mentions = Math.floor(Math.random() * 1000) + 2000
    liveStats.value.correlation = Math.max(-1, Math.min(1, liveStats.value.correlation + (Math.random() - 0.5) * 0.1))
}

const getPlatformColor = (platform) => {
    const colors = {
        twitter: 'bg-blue-500',
        reddit: 'bg-orange-500',
        telegram: 'bg-blue-600'
    }
    return colors[platform] || 'bg-panel'
}

const getSentimentBadge = (sentiment) => {
    if (sentiment > 0.3) return 'bg-green-100 text-green-800'
    if (sentiment < -0.3) return 'bg-red-100 text-red-800'
    return 'bg-ink text-gray-800'
}

const getCorrelationStrength = (correlation) => {
    const abs = Math.abs(correlation)
    if (abs >= 0.7) return 'Strong'
    if (abs >= 0.4) return 'Moderate'
    return 'Weak'
}

const getAIInsight = () => {
    const insights = [
        'Strong positive correlation detected between sentiment and price movements',
        'Social momentum is building with 87% positive sentiment in last hour',
        'Price prediction model shows 73% confidence for continued upward trend',
        'Unusual volume spike detected across social platforms - potential catalyst event'
    ]
    
    return insights[Math.floor(Date.now() / 10000) % insights.length]
}

const addNewPost = () => {
    const newPosts = [
        {
            platform: 'twitter',
            username: 'hodl_master',
            content: 'This is the way! 💎🙌 Diamond hands forever',
            sentiment: 0.91
        },
        {
            platform: 'reddit',
            username: 'technical_analyst',
            content: 'RSI showing overbought conditions, expecting pullback',
            sentiment: -0.15
        },
        {
            platform: 'telegram',
            username: 'whale_watcher',
            content: 'Large wallet movements detected on-chain',
            sentiment: 0.45
        }
    ]
    
    const newPost = newPosts[Math.floor(Math.random() * newPosts.length)]
    const post = {
        id: Date.now(),
        ...newPost,
        timeAgo: 'Just now',
        isNew: true
    }
    
    livePosts.value.unshift(post)
    
    // Remove the "new" highlight after 3 seconds
    setTimeout(() => {
        post.isNew = false
        post.timeAgo = '1m ago'
    }, 3000)
    
    // Keep only last 10 posts
    if (livePosts.value.length > 10) {
        livePosts.value = livePosts.value.slice(0, 10)
    }
}

const pauseLiveUpdates = () => {
    isPaused.value = !isPaused.value
    
    if (isPaused.value) {
        clearInterval(updateInterval)
    } else {
        startLiveUpdates()
    }
}

const exportLiveData = () => {
    const exportData = {
        timestamp: new Date().toISOString(),
        cryptocurrency: selectedCrypto.value,
        platform: selectedPlatform.value,
        stats: liveStats.value,
        recent_posts: livePosts.value,
        trend_data: trendData.value
    }
    
    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `sentiment-live-data-${Date.now()}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
}

const startLiveUpdates = () => {
    updateInterval = setInterval(() => {
        if (!isPaused.value) {
            updateLiveData()
            
            // Add new post occasionally
            if (Math.random() < 0.3) {
                addNewPost()
            }
            
            // Update trend data
            const newPoint = {
                time: new Date().toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: false 
                }),
                sentiment: liveStats.value.sentimentScore,
                priceChange: liveStats.value.priceChange
            }
            
            trendData.value.push(newPoint)
            if (trendData.value.length > 8) {
                trendData.value.shift()
            }
        }
    }, 5000) // Update every 5 seconds
}

// Lifecycle
onMounted(() => {
    startLiveUpdates()
})

onUnmounted(() => {
    if (updateInterval) {
        clearInterval(updateInterval)
    }
})
</script>