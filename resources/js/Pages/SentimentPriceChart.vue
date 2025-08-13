<template>
    <Head title="Sentiment Shield - Price Correlation" />
    
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
                        🛡️ Sentiment Shield - Price Correlation
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        AI-powered correlation analysis between security sentiment and market prices
                    </p>
                </div>
                
                <div class="mt-4 md:mt-0">
                    <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span>Real-time data</span>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Chart Component -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <SentimentPriceTimeline
                            :title="'Cryptocurrency Sentiment vs Price Timeline'"
                            :subtitle="'Track how market sentiment correlates with price movements across different cryptocurrencies'"
                            :initial-token="selectedToken"
                            :initial-time-range="selectedTimeRange"
                            :api-endpoint="'/api/sentiment-price/data'"
                            :coingecko-enabled="true"
                            :real-time-updates="enableRealTime"
                            :update-interval="300000"
                        />
                    </div>
                </div>

                <!-- Information Cards -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- What is Sentiment Analysis -->
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        Sentiment Analysis
                                    </h3>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Our AI-powered sentiment analysis processes social media posts, news articles, and forum discussions to gauge market sentiment for cryptocurrencies. Values range from -1 (very negative) to +1 (very positive).
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- How Correlation Works -->
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        Price Correlation
                                    </h3>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    We calculate the Pearson correlation coefficient between sentiment scores and price movements. Strong correlations (>0.7) suggest sentiment may be a leading indicator for price changes.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Data Sources -->
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        Data Sources
                                    </h3>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Price data is sourced from CoinGecko API for accuracy and reliability. Sentiment data comes from our proprietary analysis of multiple social platforms, news sources, and community forums.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage Tips -->
                <div class="mt-8 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            💡 How to Use This Tool
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600 dark:text-gray-400">
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">Analyzing Trends</h4>
                                <ul class="space-y-1">
                                    <li>• Look for sentiment leading price movements</li>
                                    <li>• Strong positive correlation suggests sentiment-driven markets</li>
                                    <li>• Divergences may indicate market inefficiencies</li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">Best Practices</h4>
                                <ul class="space-y-1">
                                    <li>• Use longer time frames for better correlation analysis</li>
                                    <li>• Compare multiple cryptocurrencies for insights</li>
                                    <li>• Consider external factors affecting sentiment</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart Controls (if needed for customization) -->
                <div class="mt-8">
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Chart Settings
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Default Token
                                    </label>
                                    <select 
                                        v-model="selectedToken"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="bitcoin">Bitcoin</option>
                                        <option value="ethereum">Ethereum</option>
                                        <option value="binancecoin">Binance Coin</option>
                                        <option value="cardano">Cardano</option>
                                        <option value="solana">Solana</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Default Time Range
                                    </label>
                                    <select 
                                        v-model="selectedTimeRange"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="24h">24 Hours</option>
                                        <option value="7d">7 Days</option>
                                        <option value="30d">30 Days</option>
                                        <option value="90d">90 Days</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Real-time Updates
                                    </label>
                                    <div class="flex items-center">
                                        <input 
                                            v-model="enableRealTime"
                                            type="checkbox"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        >
                                        <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                            Auto-refresh every 5 minutes
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SentimentPriceTimeline from '@/Components/Charts/SentimentPriceTimeline.vue'

// Page state
const selectedToken = ref('bitcoin')
const selectedTimeRange = ref('7d')
const enableRealTime = ref(false)
</script>

<style scoped>
/* Additional custom styles if needed */
</style>
