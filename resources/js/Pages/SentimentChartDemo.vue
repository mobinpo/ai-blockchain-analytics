<template>
  <Head title="Sentiment vs Price Chart Demo" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        📈 Sentiment vs Price Chart Demo
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
          <div class="p-6 text-gray-900">
            <h3 class="text-lg font-semibold mb-4">Interactive Sentiment vs Price Timeline</h3>
            <p class="text-gray-600 mb-4">
              This chart combines sentiment analysis data from our pipeline with cryptocurrency price data 
              from Coingecko API to show correlations and trends over time.
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div class="bg-blue-50 p-4 rounded-lg">
                <h4 class="font-semibold text-blue-800 mb-2">📊 Real-time Data</h4>
                <p class="text-sm text-blue-600">
                  Connects to our sentiment pipeline API and Coingecko for live data updates.
                </p>
              </div>
              <div class="bg-green-50 p-4 rounded-lg">
                <h4 class="font-semibold text-green-800 mb-2">🔄 Interactive Controls</h4>
                <p class="text-sm text-green-600">
                  Select different cryptocurrencies and time ranges to explore correlations.
                </p>
              </div>
              <div class="bg-purple-50 p-4 rounded-lg">
                <h4 class="font-semibold text-purple-800 mb-2">📈 Statistical Analysis</h4>
                <p class="text-sm text-purple-600">
                  Automatic correlation calculation, sentiment scoring, and trend analysis.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Chart -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
          <div class="p-6">
            <SentimentPriceChart
              :title="chartTitle"
              :default-coin="selectedDefaultCoin"
              :use-coingecko-api="useRealApi"
              :api-endpoint="apiEndpoint"
              :width="800"
              :height="500"
            />
          </div>
        </div>

        <!-- Configuration Panel -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">⚙️ Chart Configuration</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Chart Title
                </label>
                <input
                  v-model="chartTitle"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Chart title..."
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Default Cryptocurrency
                </label>
                <select
                  v-model="selectedDefaultCoin"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="bitcoin">Bitcoin</option>
                  <option value="ethereum">Ethereum</option>
                  <option value="cardano">Cardano</option>
                  <option value="solana">Solana</option>
                </select>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  API Source
                </label>
                <select
                  v-model="useRealApi"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option :value="true">Real API (Coingecko)</option>
                  <option :value="false">Mock Data</option>
                </select>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  API Endpoint
                </label>
                <input
                  v-model="apiEndpoint"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="/api/sentiment-pipeline/trends"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Code Examples -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">💻 Usage Examples</h3>
            
            <div class="space-y-6">
              <!-- Vue Component Usage -->
              <div>
                <h4 class="font-medium text-gray-800 mb-2">Vue Component Usage</h4>
                <pre class="bg-ink p-4 rounded-lg overflow-x-auto text-sm"><code>&lt;SentimentPriceChart
  title="Bitcoin Sentiment vs Price"
  :default-coin="'bitcoin'"
  :use-coingecko-api="true"
  :api-endpoint="'/api/sentiment-pipeline/trends'"
  :width="800"
  :height="400"
/&gt;</code></pre>
              </div>

              <!-- API Integration -->
              <div>
                <h4 class="font-medium text-gray-800 mb-2">API Integration</h4>
                <pre class="bg-ink p-4 rounded-lg overflow-x-auto text-sm"><code>// Fetch sentiment data
GET {{ apiEndpoint }}?days=30&platform=all&category=crypto

// Fetch price data (Coingecko)
GET https://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=30&interval=daily</code></pre>
              </div>

              <!-- Chart Configuration -->
              <div>
                <h4 class="font-medium text-gray-800 mb-2">Chart Configuration Options</h4>
                <pre class="bg-ink p-4 rounded-lg overflow-x-auto text-sm"><code>const chartOptions = {
  title: 'Custom Chart Title',
  defaultCoin: 'ethereum',
  useCoingeckoApi: true,
  apiEndpoint: '/api/sentiment-pipeline/trends',
  width: 1000,
  height: 600
}</code></pre>
              </div>
            </div>
          </div>
        </div>

        <!-- Feature List -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">✨ Chart Features</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-3">
                <h4 class="font-medium text-gray-800">Data Sources</h4>
                <ul class="space-y-2 text-sm text-gray-600">
                  <li class="flex items-center">
                    <span class="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>
                    Sentiment data from our Google Cloud NLP pipeline
                  </li>
                  <li class="flex items-center">
                    <span class="w-2 h-2 bg-green-500 rounded-full mr-3"></span>
                    Price data from Coingecko API
                  </li>
                  <li class="flex items-center">
                    <span class="w-2 h-2 bg-purple-500 rounded-full mr-3"></span>
                    Real-time correlation calculations
                  </li>
                </ul>
              </div>
              
              <div class="space-y-3">
                <h4 class="font-medium text-gray-800">Interactive Features</h4>
                <ul class="space-y-2 text-sm text-gray-600">
                  <li class="flex items-center">
                    <span class="w-2 h-2 bg-orange-500 rounded-full mr-3"></span>
                    Cryptocurrency selection dropdown
                  </li>
                  <li class="flex items-center">
                    <span class="w-2 h-2 bg-red-500 rounded-full mr-3"></span>
                    Time range controls (7 days to 1 year)
                  </li>
                  <li class="flex items-center">
                    <span class="w-2 h-2 bg-indigo-500 rounded-full mr-3"></span>
                    Dual-axis visualization
                  </li>
                  <li class="flex items-center">
                    <span class="w-2 h-2 bg-pink-500 rounded-full mr-3"></span>
                    Statistical analysis display
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SentimentPriceChart from '@/Components/SentimentPriceChart.vue'

// Reactive configuration
const chartTitle = ref('Crypto Sentiment vs Price Timeline')
const selectedDefaultCoin = ref('bitcoin')
const useRealApi = ref(true)
const apiEndpoint = ref('/api/sentiment-pipeline/trends')
</script>

<style scoped>
/* Custom scrollbar for code blocks */
pre::-webkit-scrollbar {
  height: 8px;
}

pre::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

pre::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 4px;
}

pre::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style> 