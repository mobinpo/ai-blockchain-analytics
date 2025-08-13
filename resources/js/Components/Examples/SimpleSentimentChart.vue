<template>
    <div class="simple-sentiment-chart bg-white rounded-lg shadow p-6">
        <div class="mb-4">
            <h3 class="text-lg font-medium text-gray-900">
                {{ title || 'Sentiment vs Price Analysis' }}
            </h3>
            <p v-if="description" class="text-sm text-gray-600 mt-1">
                {{ description }}
            </p>
        </div>

        <!-- Embedded Chart Component -->
        <SentimentPriceChart 
            :initial-coin="coin"
            :initial-days="days"
            ref="chartRef"
        />

        <!-- Optional Action Buttons -->
        <div v-if="showActions" class="mt-4 flex justify-end space-x-2">
            <button 
                @click="exportData"
                class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
            >
                Export Data
            </button>
            <button 
                @click="refreshChart"
                class="px-3 py-1 text-sm bg-ink text-gray-700 rounded hover:bg-gray-200"
            >
                Refresh
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import SentimentPriceChart from '@/Components/Charts/SentimentPriceChart.vue'

// Props
const props = defineProps({
    title: {
        type: String,
        default: 'Sentiment vs Price Analysis'
    },
    description: {
        type: String,
        default: ''
    },
    coin: {
        type: String,
        default: 'bitcoin'
    },
    days: {
        type: Number,
        default: 30
    },
    showActions: {
        type: Boolean,
        default: true
    }
})

// Refs
const chartRef = ref(null)

// Methods
const exportData = () => {
    if (chartRef.value?.exportToCSV) {
        chartRef.value.exportToCSV()
    }
}

const refreshChart = () => {
    // Force re-fetch by changing dates slightly
    if (chartRef.value) {
        chartRef.value.onFiltersChange()
    }
}

// Expose methods for parent components
defineExpose({
    exportData,
    refreshChart,
    getChartData: () => chartRef.value?.chartData,
    getStatistics: () => chartRef.value?.statistics
})
</script>

<style scoped>
.simple-sentiment-chart {
    min-height: 500px;
}
</style>