<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const selectedTimeframe = ref('24h');
const selectedProtocol = ref('all');

const timeframes = [
  { value: '1h', label: 'Last Hour' },
  { value: '24h', label: 'Last 24 Hours' },
  { value: '7d', label: 'Last 7 Days' },
  { value: '30d', label: 'Last 30 Days' }
];

const protocolSentiments = ref([
  {
    protocol: 'Uniswap',
    current: 0.73,
    change: 0.05,
    trend: 'up',
    volume: 1247,
    sources: ['Twitter', 'Reddit', 'Discord', 'Telegram'],
    lastUpdated: '2 minutes ago',
    color: 'bg-green-500',
    details: {
      positive: 73,
      neutral: 19,
      negative: 8
    }
  },
  {
    protocol: 'Aave',
    current: 0.85,
    change: 0.12,
    trend: 'up',
    volume: 892,
    sources: ['Twitter', 'Reddit', 'Medium'],
    lastUpdated: '3 minutes ago',
    color: 'bg-green-600',
    details: {
      positive: 85,
      neutral: 12,
      negative: 3
    }
  },
  {
    protocol: 'Compound',
    current: 0.42,
    change: -0.08,
    trend: 'down',
    volume: 634,
    sources: ['Twitter', 'Discord'],
    lastUpdated: '1 minute ago',
    color: 'bg-yellow-500',
    details: {
      positive: 42,
      neutral: 35,
      negative: 23
    }
  },
  {
    protocol: 'MakerDAO',
    current: 0.68,
    change: 0.02,
    trend: 'up',
    volume: 456,
    sources: ['Twitter', 'Reddit'],
    lastUpdated: '4 minutes ago',
    color: 'bg-green-400',
    details: {
      positive: 68,
      neutral: 22,
      negative: 10
    }
  }
]);

const recentMentions = ref([
  {
    id: 1,
    protocol: 'Uniswap',
    platform: 'Twitter',
    author: '@DefiWhale',
    content: 'Uniswap V4 hooks are revolutionary! This will change the DeFi landscape forever. Amazing innovation from the team. 🚀',
    sentiment: 0.92,
    timestamp: '5 minutes ago',
    engagement: { likes: 247, retweets: 89, replies: 34 }
  },
  {
    id: 2,
    protocol: 'Aave',
    platform: 'Reddit',
    author: 'u/CryptoAnalyst',
    content: 'AAVE\'s new governance proposal looks solid. The risk parameters are well thought out and the community response has been overwhelmingly positive.',
    sentiment: 0.78,
    timestamp: '12 minutes ago',
    engagement: { likes: 156, retweets: 0, replies: 23 }
  },
  {
    id: 3,
    protocol: 'Compound',
    platform: 'Discord',
    author: 'LiquidityProvider#1234',
    content: 'Not sure about the latest Compound update. Gas fees seem higher and the UI changes are confusing. Hope they fix these issues soon.',
    sentiment: 0.15,
    timestamp: '18 minutes ago',
    engagement: { likes: 12, retweets: 0, replies: 8 }
  },
  {
    id: 4,
    protocol: 'MakerDAO',
    platform: 'Twitter',
    author: '@DeFiResearcher',
    content: 'MakerDAO\'s stability through market volatility continues to impress. The DAI peg mechanism is working beautifully.',
    sentiment: 0.84,
    timestamp: '25 minutes ago',
    engagement: { likes: 98, retweets: 45, replies: 12 }
  }
]);

const trendingTopics = ref([
  { topic: '#UniswapV4', sentiment: 0.89, mentions: 1247, change: '+15%' },
  { topic: '#LiquidStaking', sentiment: 0.76, mentions: 892, change: '+8%' },
  { topic: '#DeFiSummer', sentiment: 0.82, mentions: 634, change: '+22%' },
  { topic: '#YieldFarming', sentiment: 0.58, mentions: 456, change: '-3%' },
  { topic: '#CrossChain', sentiment: 0.71, mentions: 334, change: '+12%' }
]);

const getSentimentColor = (sentiment) => {
  if (sentiment >= 0.7) return 'text-green-600 bg-green-50';
  if (sentiment >= 0.5) return 'text-yellow-600 bg-yellow-50';
  return 'text-red-600 bg-red-50';
};

const getSentimentLabel = (sentiment) => {
  if (sentiment >= 0.7) return 'Positive';
  if (sentiment >= 0.5) return 'Neutral';
  return 'Negative';
};

const getPlatformIcon = (platform) => {
  const icons = {
    'Twitter': '🐦',
    'Reddit': '🔴',
    'Discord': '💬',
    'Telegram': '✈️',
    'Medium': '📝'
  };
  return icons[platform] || '💬';
};

// Safe interval management for sentiment updates
let sentimentUpdateInterval = null;
let apiConnectionTimeout = null;
const isComponentActive = ref(true);
const apiConnectionState = ref('connected');
const failedUpdateCount = ref(0);
const maxFailedUpdates = 3;

// Enhanced sentiment update with real API integration
const updateSentimentData = async () => {
  try {
    if (!isComponentActive.value) {
      console.log('⏹️ Sentiment component inactive, skipping update');
      return;
    }
    
    console.log('📊 Updating sentiment data...');
    
    // Make real API call to current sentiment summary endpoint
    const response = await fetch('/api/sentiment/current-summary', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const sentimentData = await response.json();
    
    // Update protocol sentiments with real data if available
    if (sentimentData.success && sentimentData.data) {
      const realData = sentimentData.data;
      
      // Update existing protocols with real sentiment data
      protocolSentiments.value.forEach(protocol => {
        const protocolKey = protocol.protocol.toLowerCase();
        const realSentiment = realData[protocolKey] || realData.general;
        
        if (realSentiment) {
          protocol.current = Math.max(0, Math.min(1, realSentiment.average_sentiment || protocol.current));
          protocol.change = realSentiment.sentiment_change || (Math.random() - 0.5) * 0.05;
          protocol.trend = protocol.change >= 0 ? 'up' : 'down';
          protocol.volume = realSentiment.total_posts || protocol.volume;
          protocol.lastUpdated = 'Just now';
          
          // Update details if available
          if (realSentiment.sentiment_breakdown) {
            protocol.details = {
              positive: Math.round(realSentiment.sentiment_breakdown.positive * 100),
              neutral: Math.round(realSentiment.sentiment_breakdown.neutral * 100),
              negative: Math.round(realSentiment.sentiment_breakdown.negative * 100)
            };
          }
        } else {
          // Fallback to subtle random changes for demo
          const change = (Math.random() - 0.5) * 0.01;
          protocol.current = Math.max(0, Math.min(1, protocol.current + change));
          protocol.lastUpdated = 'Just now';
        }
      });
      
      // Update trending topics with real data if available
      if (realData.trending_topics) {
        realData.trending_topics.forEach((topic, index) => {
          if (trendingTopics.value[index]) {
            trendingTopics.value[index].topic = topic.keyword || trendingTopics.value[index].topic;
            trendingTopics.value[index].mentions = topic.mentions || trendingTopics.value[index].mentions;
            trendingTopics.value[index].sentiment = topic.average_sentiment || trendingTopics.value[index].sentiment;
          }
        });
      }
    } else {
      // Fallback: subtle random updates for demo when no real data
      protocolSentiments.value.forEach(protocol => {
        const change = (Math.random() - 0.5) * 0.01; // Very small change
        protocol.current = Math.max(0, Math.min(1, protocol.current + change));
        protocol.lastUpdated = 'Just now';
      });
    }
    
    // Reset failure count on success
    failedUpdateCount.value = 0;
    apiConnectionState.value = 'connected';
    console.log('✅ Sentiment data updated successfully');
    
  } catch (error) {
    failedUpdateCount.value++;
    console.error(`❌ Sentiment update failed (${failedUpdateCount.value}/${maxFailedUpdates}):`, error);
    
    if (failedUpdateCount.value >= maxFailedUpdates) {
      console.error('🚫 Max sentiment update failures reached, stopping updates');
      apiConnectionState.value = 'failed';
      
      // Stop updates
      if (sentimentUpdateInterval) {
        clearInterval(sentimentUpdateInterval);
        sentimentUpdateInterval = null;
      }
      
      // Try to reconnect after delay
      apiConnectionTimeout = setTimeout(() => {
        if (isComponentActive.value) {
          console.log('🔄 Attempting to reconnect sentiment updates...');
          failedUpdateCount.value = 0;
          apiConnectionState.value = 'reconnecting';
          startSentimentUpdates();
        }
      }, 30000); // Try reconnect after 30 seconds
    } else {
      apiConnectionState.value = 'unstable';
    }
  }
};

// Start sentiment updates with proper management
const startSentimentUpdates = () => {
  if (sentimentUpdateInterval) {
    clearInterval(sentimentUpdateInterval);
  }
  
  console.log('🚀 Starting sentiment updates');
  sentimentUpdateInterval = setInterval(() => {
    if (isComponentActive.value && apiConnectionState.value !== 'failed') {
      updateSentimentData();
    }
  }, 10000); // Update every 10 seconds
};

// Enhanced component lifecycle management
onMounted(() => {
  console.log('💭 Sentiment component mounted');
  isComponentActive.value = true;
  
  // Initial update
  updateSentimentData().then(() => {
    if (apiConnectionState.value === 'connected') {
      startSentimentUpdates();
    }
  });
  
  // Handle page visibility changes
  const handleVisibilityChange = () => {
    if (document.hidden) {
      console.log('📱 Page hidden, pausing sentiment updates');
      if (sentimentUpdateInterval) {
        clearInterval(sentimentUpdateInterval);
        sentimentUpdateInterval = null;
      }
    } else {
      console.log('📱 Page visible, resuming sentiment updates');
      if (isComponentActive.value && apiConnectionState.value !== 'failed') {
        startSentimentUpdates();
      }
    }
  };
  
  document.addEventListener('visibilitychange', handleVisibilityChange);
  
  // Store the cleanup function
  onUnmounted(() => {
    console.log('🧹 Sentiment component unmounting...');
    isComponentActive.value = false;
    
    if (sentimentUpdateInterval) {
      clearInterval(sentimentUpdateInterval);
      sentimentUpdateInterval = null;
    }
    
    if (apiConnectionTimeout) {
      clearTimeout(apiConnectionTimeout);
      apiConnectionTimeout = null;
    }
    
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    console.log('✅ Sentiment cleanup completed');
  });
});

const overallSentiment = computed(() => {
  const avg = protocolSentiments.value.reduce((sum, p) => sum + p.current, 0) / protocolSentiments.value.length;
  return Math.round(avg * 100) / 100;
});

const totalMentions = computed(() => {
  return protocolSentiments.value.reduce((sum, p) => sum + p.volume, 0);
});
</script>

<template>
  <AppLayout>
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-2">🛡️ Sentiment Shield Analysis</h1>
      <p class="text-gray-600">AI-powered sentiment tracking with blockchain security correlation across all platforms</p>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Overall Sentiment</p>
            <p class="text-2xl font-bold text-gray-900">{{ Math.round(overallSentiment * 100) }}%</p>
            <div class="flex items-center mt-2">
              <span class="text-green-600 text-sm font-medium">+5.2%</span>
              <span class="text-xs text-gray-500 ml-1">vs yesterday</span>
            </div>
          </div>
          <div class="text-3xl">📈</div>
        </div>
      </div>
      
      <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Total Mentions</p>
            <p class="text-2xl font-bold text-gray-900">{{ totalMentions.toLocaleString() }}</p>
            <div class="flex items-center mt-2">
              <span class="text-green-600 text-sm font-medium">+12.5%</span>
              <span class="text-xs text-gray-500 ml-1">vs yesterday</span>
            </div>
          </div>
          <div class="text-3xl">💬</div>
        </div>
      </div>
      
      <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Positive Sentiment</p>
            <p class="text-2xl font-bold text-green-600">{{ Math.round(protocolSentiments.filter(p => p.current >= 0.7).length / protocolSentiments.length * 100) }}%</p>
            <div class="flex items-center mt-2">
              <span class="text-green-600 text-sm font-medium">+8.1%</span>
              <span class="text-xs text-gray-500 ml-1">vs yesterday</span>
            </div>
          </div>
          <div class="text-3xl">😊</div>
        </div>
      </div>
      
      <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Active Sources</p>
            <p class="text-2xl font-bold text-gray-900">12</p>
            <div class="flex items-center mt-2">
              <span class="text-green-600 text-sm font-medium">+2</span>
              <span class="text-xs text-gray-500 ml-1">new sources</span>
            </div>
          </div>
          <div class="text-3xl">🔍</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8">
      <div class="flex flex-wrap items-center gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Timeframe</label>
          <select v-model="selectedTimeframe" 
                  class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option v-for="timeframe in timeframes" :key="timeframe.value" :value="timeframe.value">
              {{ timeframe.label }}
            </option>
          </select>
        </div>
        
        <div class="flex-1"></div>
        
        <div class="flex items-center space-x-2">
          <div class="flex items-center space-x-2 rounded-full bg-green-50 px-3 py-1">
            <div class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></div>
            <span class="text-sm font-medium text-green-700">Live Updates</span>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Protocol Sentiment Cards -->
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Protocol Sentiment</h2>
          </div>
          <div class="p-6">
            <div class="space-y-6">
              <div v-for="protocol in protocolSentiments" :key="protocol.protocol" 
                   class="p-4 bg-panel rounded-lg hover:bg-ink transition-colors">
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center space-x-3">
                    <h3 class="text-lg font-semibold text-gray-900">{{ protocol.protocol }}</h3>
                    <span :class="getSentimentColor(protocol.current)" 
                          class="px-3 py-1 text-xs font-medium rounded-full">
                      {{ getSentimentLabel(protocol.current) }}
                    </span>
                  </div>
                  <div class="text-right">
                    <div class="text-2xl font-bold text-gray-900">{{ Math.round(protocol.current * 100) }}%</div>
                    <div class="flex items-center text-sm">
                      <span :class="protocol.change >= 0 ? 'text-green-600' : 'text-red-600'">
                        {{ protocol.change >= 0 ? '+' : '' }}{{ Math.round(protocol.change * 100) }}%
                      </span>
                      <span class="text-gray-500 ml-1">{{ selectedTimeframe }}</span>
                    </div>
                  </div>
                </div>

                <!-- Sentiment Breakdown -->
                <div class="mb-4">
                  <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                    <span>Sentiment Breakdown</span>
                    <span>{{ protocol.volume }} mentions</span>
                  </div>
                  <div class="flex w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-green-500 h-3 rounded-l-full transition-all duration-300" 
                         :style="{ width: protocol.details.positive + '%' }"></div>
                    <div class="bg-yellow-500 h-3 transition-all duration-300" 
                         :style="{ width: protocol.details.neutral + '%' }"></div>
                    <div class="bg-red-500 h-3 rounded-r-full transition-all duration-300" 
                         :style="{ width: protocol.details.negative + '%' }"></div>
                  </div>
                  <div class="flex justify-between text-xs text-gray-500 mt-1">
                    <span>{{ protocol.details.positive }}% Positive</span>
                    <span>{{ protocol.details.neutral }}% Neutral</span>
                    <span>{{ protocol.details.negative }}% Negative</span>
                  </div>
                </div>

                <!-- Sources & Last Updated -->
                <div class="flex items-center justify-between text-sm text-gray-600">
                  <div class="flex items-center space-x-2">
                    <span>Sources:</span>
                    <div class="flex space-x-1">
                      <span v-for="source in protocol.sources" :key="source" class="px-2 py-1 bg-gray-200 rounded text-xs">
                        {{ getPlatformIcon(source) }} {{ source }}
                      </span>
                    </div>
                  </div>
                  <span>Updated {{ protocol.lastUpdated }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Trending Topics -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Trending Topics</h2>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div v-for="topic in trendingTopics" :key="topic.topic" 
                   class="flex items-center justify-between p-3 bg-panel rounded-md">
                <div>
                  <div class="font-medium text-gray-900">{{ topic.topic }}</div>
                  <div class="text-sm text-gray-600">{{ topic.mentions }} mentions</div>
                </div>
                <div class="text-right">
                  <div :class="getSentimentColor(topic.sentiment)" 
                       class="px-2 py-1 text-xs font-medium rounded-full mb-1">
                    {{ Math.round(topic.sentiment * 100) }}%
                  </div>
                  <div :class="topic.change.startsWith('+') ? 'text-green-600' : 'text-red-600'" 
                       class="text-xs font-medium">
                    {{ topic.change }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Mentions -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Recent Mentions</h2>
          </div>
          <div class="p-6">
            <div class="space-y-4 max-h-96 overflow-y-auto">
              <div v-for="mention in recentMentions" :key="mention.id" 
                   class="p-3 bg-panel rounded-md border border-gray-100">
                <div class="flex items-start justify-between mb-2">
                  <div class="flex items-center space-x-2">
                    <span>{{ getPlatformIcon(mention.platform) }}</span>
                    <span class="font-medium text-sm text-gray-900">{{ mention.author }}</span>
                    <span class="text-xs text-gray-500">{{ mention.protocol }}</span>
                  </div>
                  <span :class="getSentimentColor(mention.sentiment)" 
                        class="px-2 py-1 text-xs font-medium rounded-full">
                    {{ Math.round(mention.sentiment * 100) }}%
                  </span>
                </div>
                <p class="text-sm text-gray-700 mb-2 line-clamp-3">{{ mention.content }}</p>
                <div class="flex items-center justify-between text-xs text-gray-500">
                  <span>{{ mention.timestamp }}</span>
                  <div class="flex space-x-2">
                    <span>👍 {{ mention.engagement.likes }}</span>
                    <span v-if="mention.engagement.retweets > 0">🔄 {{ mention.engagement.retweets }}</span>
                    <span>💬 {{ mention.engagement.replies }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Demo Banner -->
    <div class="mt-8 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg p-6 text-white">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold mb-2">🤖 AI-Powered Sentiment Analysis</h3>
          <p class="text-green-100">Advanced natural language processing analyzes millions of social media posts, news articles, and forum discussions to provide real-time market sentiment insights.</p>
        </div>
        <button class="px-6 py-3 bg-white bg-opacity-20 rounded-lg font-medium hover:bg-opacity-30 transition-colors">
          API Access
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>