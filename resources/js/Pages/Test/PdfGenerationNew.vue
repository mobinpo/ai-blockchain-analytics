<template>
    <Head title="PDF Generation Test" />
    
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    PDF Generation Test Suite
                </h2>
                
                <div class="flex items-center space-x-4">
                    <div class="text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Engine:</span>
                        <span class="ml-1 font-medium" :class="engineStatus.browserless?.available ? 'text-green-600' : 'text-orange-600'">
                            {{ engineStatus.browserless?.available ? 'Browserless' : 'DomPDF' }}
                        </span>
                    </div>
                    
                    <button
                        @click="checkEngineStatus"
                        :disabled="checkingStatus"
                        class="px-3 py-1 text-xs border border-gray-300 rounded hover:bg-panel"
                    >
                        {{ checkingStatus ? 'Checking...' : 'Check Status' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Chart Test -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
                            Sentiment vs Price Chart Test
                        </h3>
                        
                        <SentimentPriceTimelineChart
                            :default-coin="selectedCoin"
                            :default-timeframe="selectedTimeframe"
                            :height="400"
                            theme="auto"
                            @pdf-export-started="onPdfExportStarted"
                            @pdf-export-completed="onPdfExportCompleted"
                            @pdf-export-failed="onPdfExportFailed"
                        />
                        
                        <div class="mt-4 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Cryptocurrency
                                </label>
                                <select 
                                    v-model="selectedCoin"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                >
                                    <option value="bitcoin">Bitcoin (BTC)</option>
                                    <option value="ethereum">Ethereum (ETH)</option>
                                    <option value="cardano">Cardano (ADA)</option>
                                    <option value="solana">Solana (SOL)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Timeframe
                                </label>
                                <select 
                                    v-model="selectedTimeframe"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                >
                                    <option value="7">7 Days</option>
                                    <option value="30">30 Days</option>
                                    <option value="90">90 Days</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Engine Status -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            PDF Engine Status
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium">Browserless</h4>
                                    <div class="flex items-center">
                                        <div :class="engineStatus.browserless?.available ? 'bg-green-500' : 'bg-red-500'" class="w-3 h-3 rounded-full mr-2"></div>
                                        <span class="text-sm">{{ engineStatus.browserless?.available ? 'Available' : 'Unavailable' }}</span>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Chrome headless browser for high-quality PDF generation with full JavaScript support.
                                </p>
                            </div>
                            
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium">DomPDF</h4>
                                    <div class="flex items-center">
                                        <div :class="engineStatus.dompdf?.available ? 'bg-green-500' : 'bg-red-500'" class="w-3 h-3 rounded-full mr-2"></div>
                                        <span class="text-sm">{{ engineStatus.dompdf?.available ? 'Available' : 'Unavailable' }}</span>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    PHP-based PDF generation. Fallback option with limited JavaScript support.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SentimentPriceTimelineChart from '@/Components/SentimentPriceTimelineChart.vue'

// Reactive state
const selectedCoin = ref('bitcoin')
const selectedTimeframe = ref('30')
const checkingStatus = ref(false)

// Engine status
const engineStatus = ref({
    browserless: {
        available: false,
        url: null
    },
    dompdf: {
        available: true,
        version: 'Available'
    }
})

// Methods
const checkEngineStatus = async () => {
    checkingStatus.value = true
    
    try {
        const response = await fetch('/pdf/test-engines')
        const data = await response.json()
        
        if (data.status === 'success') {
            engineStatus.value = data.engines
        }
    } catch (error) {
        console.error('Failed to check engine status:', error)
    } finally {
        checkingStatus.value = false
    }
}

// PDF Export event handlers
const onPdfExportStarted = () => {
    console.log('PDF export started')
}

const onPdfExportCompleted = (result) => {
    console.log('PDF export completed:', result)
}

const onPdfExportFailed = (error) => {
    console.error('PDF export failed:', error)
}

// Lifecycle
onMounted(() => {
    checkEngineStatus()
})
</script>

<style scoped>
.chart-container {
    min-height: 400px;
}
</style>