<template>
    <div class="dashboard-sentiment-widget">
        <!-- Widget Header -->
        <div class="flex justify-between items-center mb-4">
            <div>
                <h4 class="text-md font-semibold text-gray-900">Market Sentiment</h4>
                <p class="text-sm text-gray-600">{{ selectedCoin.toUpperCase() }} sentiment vs price correlation</p>
            </div>
            
            <!-- Coin Selector -->
            <select 
                v-model="selectedCoin" 
                @change="updateChart"
                class="text-sm border border-gray-300 rounded px-2 py-1"
            >
                <option value="bitcoin">Bitcoin</option>
                <option value="ethereum">Ethereum</option>
                <option value="cardano">Cardano</option>
                <option value="solana">Solana</option>
            </select>
        </div>

        <!-- Compact Chart -->
        <div class="relative h-64 bg-panel rounded-lg overflow-hidden">
            <SentimentPriceChart 
                :initial-coin="selectedCoin"
                :initial-days="timeframe"
                ref="chartComponent"
            />
        </div>

        <!-- Quick Stats -->
        <div v-if="statistics" class="grid grid-cols-3 gap-4 mt-4 text-center">
            <div class="bg-blue-50 rounded p-2">
                <div class="text-xs text-blue-600 font-medium">Correlation</div>
                <div class="text-sm font-bold" :class="getCorrelationColor(statistics.correlation_coefficient)">
                    {{ statistics.correlation_coefficient?.toFixed(2) || 'N/A' }}
                </div>
            </div>
            
            <div class="bg-green-50 rounded p-2">
                <div class="text-xs text-green-600 font-medium">Avg Sentiment</div>
                <div class="text-sm font-bold" :class="getSentimentColor(statistics.sentiment_stats?.average)">
                    {{ statistics.sentiment_stats?.average?.toFixed(2) || 'N/A' }}
                </div>
            </div>
            
            <div class="bg-purple-50 rounded p-2">
                <div class="text-xs text-purple-600 font-medium">Data Points</div>
                <div class="text-sm font-bold text-purple-700">
                    {{ statistics.data_points || 0 }}
                </div>
            </div>
        </div>

        <!-- Action Links -->
        <div class="flex justify-between items-center mt-4 pt-3 border-t border-gray-200">
            <Link 
                :href="'/sentiment-analysis/chart?coin=' + selectedCoin"
                class="text-sm text-blue-600 hover:text-blue-800"
            >
                View Detailed Analysis →
            </Link>
            
            <button 
                @click="exportQuickData"
                class="text-sm text-gray-500 hover:text-gray-700"
            >
                Export
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import SentimentPriceChart from '@/Components/Charts/SentimentPriceChart.vue'

// Props
const props = defineProps({
    defaultCoin: {
        type: String,
        default: 'bitcoin'
    },
    timeframe: {
        type: Number,
        default: 7 // Last 7 days for widget
    }
})

// Reactive state
const selectedCoin = ref(props.defaultCoin)
const chartComponent = ref(null)

// Computed properties
const statistics = computed(() => {
    return chartComponent.value?.statistics || null
})

// Methods
const updateChart = () => {
    // Chart will automatically update when selectedCoin changes
    // due to reactive prop binding
}

const exportQuickData = () => {
    if (chartComponent.value?.exportToCSV) {
        chartComponent.value.exportToCSV()
    }
}

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

// Watch for coin changes to trigger chart updates
watch(selectedCoin, (newCoin) => {
    if (chartComponent.value) {
        // Update the chart component's selected coin
        chartComponent.value.selectedCoin = newCoin
        chartComponent.value.onCoinChange()
    }
})
</script>

<style scoped>
.dashboard-sentiment-widget {
    @apply bg-white rounded-lg shadow-sm border border-gray-200 p-4;
    min-height: 400px;
}

/* Override chart component styles for compact view */
:deep(.sentiment-price-chart-container) {
    @apply p-0;
}

:deep(.chart-controls) {
    @apply hidden; /* Hide controls in widget view */
}

:deep(.bg-white.rounded-lg.shadow-lg) {
    @apply shadow-none border-0 p-0; /* Simplify chart container */
}
</style>