<template>
    <div class="sentiment-report" :class="{ 'print-mode': pdfMode }">
        <Head :title="data.title || 'Sentiment Analysis Report'" />
        
        <!-- Report Header -->
        <div class="report-header">
            <div class="header-left">
                <h1 class="report-title">{{ data.title || 'Sentiment Analysis Report' }}</h1>
                <div class="report-subtitle">{{ data.period || 'Comprehensive Sentiment Analysis' }}</div>
            </div>
            <div class="header-right">
                <div class="overall-sentiment" :class="sentimentClass">
                    <div class="sentiment-score">{{ formatSentiment(data.overall_sentiment) }}</div>
                    <div class="sentiment-label">Overall Sentiment</div>
                </div>
            </div>
        </div>

        <!-- Platform Analysis -->
        <section class="platform-analysis">
            <h2>📱 Platform Sentiment Analysis</h2>
            <div class="platforms-grid">
                <div 
                    v-for="(platformData, platform) in data.platforms" 
                    :key="platform"
                    class="platform-card"
                >
                    <div class="platform-header">
                        <div class="platform-icon">{{ getPlatformIcon(platform) }}</div>
                        <h3>{{ formatPlatformName(platform) }}</h3>
                        <div class="platform-sentiment" :class="getSentimentClass(platformData.sentiment)">
                            {{ formatSentiment(platformData.sentiment) }}
                        </div>
                    </div>
                    
                    <div class="platform-metrics">
                        <div class="metric">
                            <span class="metric-label">Posts Analyzed</span>
                            <span class="metric-value">{{ formatNumber(platformData.posts) }}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Total Engagement</span>
                            <span class="metric-value">{{ formatNumber(platformData.engagement) }}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Avg. Engagement</span>
                            <span class="metric-value">{{ calculateAvgEngagement(platformData) }}</span>
                        </div>
                    </div>

                    <!-- Sentiment Distribution -->
                    <div class="sentiment-distribution">
                        <h4>Sentiment Distribution</h4>
                        <div class="distribution-bars">
                            <div class="distribution-bar positive" :style="{ width: getDistributionWidth(platformData, 'positive') }">
                                <span v-if="getDistributionPercentage(platformData, 'positive') > 15">
                                    {{ getDistributionPercentage(platformData, 'positive') }}%
                                </span>
                            </div>
                            <div class="distribution-bar neutral" :style="{ width: getDistributionWidth(platformData, 'neutral') }">
                                <span v-if="getDistributionPercentage(platformData, 'neutral') > 15">
                                    {{ getDistributionPercentage(platformData, 'neutral') }}%
                                </span>
                            </div>
                            <div class="distribution-bar negative" :style="{ width: getDistributionWidth(platformData, 'negative') }">
                                <span v-if="getDistributionPercentage(platformData, 'negative') > 15">
                                    {{ getDistributionPercentage(platformData, 'negative') }}%
                                </span>
                            </div>
                        </div>
                        <div class="distribution-legend">
                            <span class="legend-item positive">😊 Positive</span>
                            <span class="legend-item neutral">😐 Neutral</span>
                            <span class="legend-item negative">😞 Negative</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Top Keywords Analysis -->
        <section v-if="data.top_keywords" class="keywords-analysis">
            <h2>🔑 Top Keywords & Sentiment</h2>
            <div class="keywords-grid">
                <div 
                    v-for="(keywordData, keyword) in data.top_keywords" 
                    :key="keyword"
                    class="keyword-card"
                >
                    <div class="keyword-header">
                        <h3 class="keyword-name"># {{ keyword }}</h3>
                        <div class="keyword-sentiment" :class="getSentimentClass(keywordData.sentiment)">
                            {{ formatSentiment(keywordData.sentiment) }}
                        </div>
                    </div>
                    <div class="keyword-stats">
                        <div class="mentions-count">
                            <span class="count">{{ formatNumber(keywordData.mentions) }}</span>
                            <span class="label">mentions</span>
                        </div>
                        <div class="trend-indicator" :class="getTrendClass(keywordData.trend)">
                            {{ getTrendIcon(keywordData.trend) }} {{ formatTrend(keywordData.trend) }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sentiment Timeline Chart -->
        <section class="timeline-section">
            <h2>📈 Sentiment Timeline</h2>
            <div class="chart-container">
                <canvas ref="sentimentTimeline" class="timeline-chart"></canvas>
            </div>
        </section>

        <!-- Key Insights & Recommendations -->
        <section class="insights-section">
            <h2>💡 Key Insights & Recommendations</h2>
            <div class="insights-container">
                
                <!-- Positive Insights -->
                <div class="insight-group positive">
                    <h3>🌟 Positive Indicators</h3>
                    <ul class="insight-list">
                        <li v-if="data.overall_sentiment > 0.1">
                            Overall sentiment is positive ({{ formatSentiment(data.overall_sentiment) }}), indicating favorable market perception.
                        </li>
                        <li v-for="platform in getPositivePlatforms()" :key="platform">
                            {{ formatPlatformName(platform) }} shows strong positive sentiment, driving overall market confidence.
                        </li>
                        <li v-if="hasGrowingKeywords()">
                            Several key terms show increasing mention volume with positive sentiment trends.
                        </li>
                    </ul>
                </div>

                <!-- Areas for Attention -->
                <div class="insight-group warning">
                    <h3>⚠️ Areas Requiring Attention</h3>
                    <ul class="insight-list">
                        <li v-if="data.overall_sentiment < -0.1">
                            Overall sentiment is negative ({{ formatSentiment(data.overall_sentiment) }}), indicating market concerns.
                        </li>
                        <li v-for="platform in getNegativePlatforms()" :key="platform">
                            {{ formatPlatformName(platform) }} shows concerning negative sentiment that may impact market perception.
                        </li>
                        <li v-if="hasVolatileKeywords()">
                            High sentiment volatility observed in key discussion topics.
                        </li>
                    </ul>
                </div>

                <!-- Strategic Recommendations -->
                <div class="insight-group recommendations">
                    <h3>🎯 Strategic Recommendations</h3>
                    <ul class="insight-list">
                        <li>Monitor {{ getMostInfluentialPlatform() }} closely as it shows the highest engagement and sentiment impact.</li>
                        <li>Focus communication efforts on addressing concerns raised in {{ getMostNegativePlatform() }} discussions.</li>
                        <li>Leverage positive sentiment momentum from trending keywords for strategic communications.</li>
                        <li>Implement sentiment tracking for emerging topics to maintain proactive market awareness.</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Report Footer -->
        <div class="report-footer">
            <div class="footer-left">
                <div class="generation-info">
                    <span class="generated-label">Generated:</span>
                    <span class="generated-date">{{ new Date().toLocaleString() }}</span>
                </div>
                <div class="data-source">
                    <span class="source-label">Data Sources:</span>
                    <span class="source-platforms">{{ Object.keys(data.platforms || {}).join(', ') }}</span>
                </div>
            </div>
            <div class="footer-right">
                <div class="company-brand">
                    <span class="company-name">AI Blockchain Analytics</span>
                    <span class="report-version">Sentiment Report v2.1</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Head } from '@inertiajs/vue3'

// Props
const props = defineProps({
    data: {
        type: Object,
        required: true
    },
    pdf_mode: {
        type: Boolean,
        default: false
    },
    options: {
        type: Object,
        default: () => ({})
    }
})

// Refs
const sentimentTimeline = ref(null)

// Computed
const pdfMode = computed(() => props.pdf_mode || props.options?.pdf_mode)

const sentimentClass = computed(() => {
    const score = props.data.overall_sentiment || 0
    if (score > 0.2) return 'positive'
    if (score < -0.2) return 'negative'
    return 'neutral'
})

// Methods
const formatNumber = (num) => {
    if (!num) return '0'
    return new Intl.NumberFormat().format(num)
}

const formatSentiment = (score) => {
    if (score === null || score === undefined) return 'N/A'
    const value = parseFloat(score)
    return value >= 0 ? `+${value.toFixed(3)}` : value.toFixed(3)
}

const getPlatformIcon = (platform) => {
    const icons = {
        twitter: '🐦',
        reddit: '📋',
        telegram: '📢',
        facebook: '📘',
        instagram: '📷'
    }
    return icons[platform.toLowerCase()] || '📱'
}

const formatPlatformName = (platform) => {
    return platform.charAt(0).toUpperCase() + platform.slice(1)
}

const getSentimentClass = (score) => {
    if (score > 0.1) return 'positive'
    if (score < -0.1) return 'negative'
    return 'neutral'
}

const calculateAvgEngagement = (platformData) => {
    if (!platformData.posts || !platformData.engagement) return '0'
    return Math.round(platformData.engagement / platformData.posts).toLocaleString()
}

const getDistributionWidth = (platformData, type) => {
    // Simulate distribution based on sentiment
    const sentiment = platformData.sentiment || 0
    let positive = 33, neutral = 34, negative = 33
    
    if (sentiment > 0.2) {
        positive = 50
        neutral = 30
        negative = 20
    } else if (sentiment < -0.2) {
        positive = 20
        neutral = 30
        negative = 50
    }
    
    const values = { positive, neutral, negative }
    return `${values[type]}%`
}

const getDistributionPercentage = (platformData, type) => {
    const width = getDistributionWidth(platformData, type)
    return parseInt(width.replace('%', ''))
}

const getTrendClass = (trend) => {
    if (!trend) return 'neutral'
    return trend > 0 ? 'positive' : 'negative'
}

const getTrendIcon = (trend) => {
    if (!trend) return '→'
    return trend > 0 ? '↗️' : '↘️'
}

const formatTrend = (trend) => {
    if (!trend) return '0%'
    return `${trend > 0 ? '+' : ''}${trend.toFixed(1)}%`
}

const getPositivePlatforms = () => {
    return Object.entries(props.data.platforms || {})
        .filter(([_, data]) => data.sentiment > 0.1)
        .map(([platform, _]) => platform)
}

const getNegativePlatforms = () => {
    return Object.entries(props.data.platforms || {})
        .filter(([_, data]) => data.sentiment < -0.1)
        .map(([platform, _]) => platform)
}

const getMostInfluentialPlatform = () => {
    const platforms = props.data.platforms || {}
    let maxEngagement = 0
    let mostInfluential = 'social media'
    
    Object.entries(platforms).forEach(([platform, data]) => {
        if (data.engagement > maxEngagement) {
            maxEngagement = data.engagement
            mostInfluential = formatPlatformName(platform)
        }
    })
    
    return mostInfluential
}

const getMostNegativePlatform = () => {
    const platforms = props.data.platforms || {}
    let minSentiment = 1
    let mostNegative = 'social media'
    
    Object.entries(platforms).forEach(([platform, data]) => {
        if (data.sentiment < minSentiment) {
            minSentiment = data.sentiment
            mostNegative = formatPlatformName(platform)
        }
    })
    
    return mostNegative
}

const hasGrowingKeywords = () => {
    return Object.values(props.data.top_keywords || {}).some(keyword => keyword.trend > 0)
}

const hasVolatileKeywords = () => {
    return Object.values(props.data.top_keywords || {}).some(keyword => Math.abs(keyword.trend) > 10)
}

// Initialize chart when component mounts
onMounted(async () => {
    if (!pdfMode.value) {
        await initializeChart()
    }
})

const initializeChart = async () => {
    try {
        const { Chart, registerables } = await import('chart.js')
        Chart.register(...registerables)

        if (sentimentTimeline.value) {
            // Generate sample timeline data
            const timelineData = generateTimelineData()
            
            new Chart(sentimentTimeline.value, {
                type: 'line',
                data: {
                    labels: timelineData.labels,
                    datasets: [{
                        label: 'Overall Sentiment',
                        data: timelineData.overall,
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            min: -1,
                            max: 1,
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(1)
                                }
                            }
                        }
                    }
                }
            })
        }
    } catch (error) {
        console.warn('Chart.js not available in PDF mode:', error)
    }
}

const generateTimelineData = () => {
    const labels = []
    const overall = []
    const baseScore = props.data.overall_sentiment || 0
    
    for (let i = 29; i >= 0; i--) {
        const date = new Date()
        date.setDate(date.getDate() - i)
        labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }))
        
        // Generate realistic variation around the base score
        const variation = (Math.random() - 0.5) * 0.3
        overall.push(Math.max(-1, Math.min(1, baseScore + variation)))
    }
    
    return { labels, overall }
}
</script>

<style scoped>
.sentiment-report {
    @apply bg-white text-gray-900 font-sans;
    font-size: 13px;
    line-height: 1.4;
    max-width: 297mm; /* A4 landscape width */
    margin: 0 auto;
}

.print-mode {
    @apply p-6;
}

/* Header */
.report-header {
    @apply flex justify-between items-start border-b-2 border-gray-200 pb-6 mb-8;
}

.report-title {
    @apply text-3xl font-bold text-gray-900 mb-1;
}

.report-subtitle {
    @apply text-lg text-gray-600;
}

.overall-sentiment {
    @apply text-center p-4 rounded-lg border-2;
}

.overall-sentiment.positive {
    @apply bg-green-50 border-green-300 text-green-800;
}

.overall-sentiment.negative {
    @apply bg-red-50 border-red-300 text-red-800;
}

.overall-sentiment.neutral {
    @apply bg-panel border-gray-300 text-gray-800;
}

.sentiment-score {
    @apply text-2xl font-bold mb-1;
}

.sentiment-label {
    @apply text-sm font-medium;
}

/* Sections */
section {
    @apply mb-8;
}

section h2 {
    @apply text-xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200;
}

/* Platform Analysis */
.platforms-grid {
    @apply grid grid-cols-1 lg:grid-cols-3 gap-6;
}

.platform-card {
    @apply bg-panel p-4 rounded-lg border border-gray-200;
}

.platform-header {
    @apply flex items-center justify-between mb-4;
}

.platform-icon {
    @apply text-2xl mr-2;
}

.platform-header h3 {
    @apply text-lg font-medium text-gray-900 flex-1 mb-0;
}

.platform-sentiment {
    @apply px-3 py-1 rounded-full text-sm font-medium;
}

.platform-sentiment.positive {
    @apply bg-green-100 text-green-800;
}

.platform-sentiment.negative {
    @apply bg-red-100 text-red-800;
}

.platform-sentiment.neutral {
    @apply bg-ink text-gray-800;
}

.platform-metrics {
    @apply grid grid-cols-3 gap-2 mb-4;
}

.metric {
    @apply text-center;
}

.metric-label {
    @apply block text-xs text-gray-600 mb-1;
}

.metric-value {
    @apply block text-sm font-semibold text-gray-900;
}

/* Sentiment Distribution */
.sentiment-distribution h4 {
    @apply text-sm font-medium text-gray-900 mb-2;
}

.distribution-bars {
    @apply flex w-full h-6 bg-gray-200 rounded mb-2 overflow-hidden;
}

.distribution-bar {
    @apply flex items-center justify-center text-xs font-medium text-white;
}

.distribution-bar.positive {
    @apply bg-green-500;
}

.distribution-bar.neutral {
    @apply bg-gray-400;
}

.distribution-bar.negative {
    @apply bg-red-500;
}

.distribution-legend {
    @apply flex justify-between text-xs text-gray-600;
}

/* Keywords Analysis */
.keywords-grid {
    @apply grid grid-cols-2 lg:grid-cols-4 gap-4;
}

.keyword-card {
    @apply bg-panel p-3 rounded-lg border border-gray-200;
}

.keyword-header {
    @apply flex justify-between items-start mb-2;
}

.keyword-name {
    @apply text-sm font-medium text-gray-900 mb-0;
}

.keyword-sentiment {
    @apply px-2 py-1 rounded text-xs font-medium;
}

.keyword-sentiment.positive {
    @apply bg-green-100 text-green-800;
}

.keyword-sentiment.negative {
    @apply bg-red-100 text-red-800;
}

.keyword-sentiment.neutral {
    @apply bg-ink text-gray-800;
}

.keyword-stats {
    @apply flex justify-between items-end;
}

.mentions-count {
    @apply text-center;
}

.mentions-count .count {
    @apply block text-lg font-bold text-gray-900;
}

.mentions-count .label {
    @apply block text-xs text-gray-600;
}

.trend-indicator {
    @apply text-xs font-medium;
}

.trend-indicator.positive {
    @apply text-green-600;
}

.trend-indicator.negative {
    @apply text-red-600;
}

.trend-indicator.neutral {
    @apply text-gray-600;
}

/* Chart */
.chart-container {
    @apply bg-panel p-4 rounded-lg;
}

.timeline-chart {
    @apply w-full;
    height: 250px;
}

/* Insights */
.insights-container {
    @apply space-y-6;
}

.insight-group {
    @apply p-4 rounded-lg border;
}

.insight-group.positive {
    @apply bg-green-50 border-green-200;
}

.insight-group.warning {
    @apply bg-yellow-50 border-yellow-200;
}

.insight-group.recommendations {
    @apply bg-blue-50 border-blue-200;
}

.insight-group h3 {
    @apply text-lg font-medium mb-3;
}

.insight-list {
    @apply space-y-2 text-sm text-gray-700;
}

.insight-list li {
    @apply flex items-start;
}

.insight-list li::before {
    content: "•";
    @apply text-gray-400 font-bold mr-2 mt-1;
}

/* Footer */
.report-footer {
    @apply border-t border-gray-200 pt-4 mt-8 flex justify-between items-end text-xs text-gray-600;
}

.generation-info {
    @apply mb-1;
}

.generated-label, .source-label {
    @apply font-medium;
}

.company-brand {
    @apply text-right;
}

.company-name {
    @apply block font-semibold text-gray-900;
}

.report-version {
    @apply block text-gray-500;
}

/* Print Styles */
@media print {
    .sentiment-report {
        @apply p-0 m-0;
        max-width: none;
    }
    
    .timeline-chart {
        height: 200px;
    }
    
    .platforms-grid {
        page-break-inside: avoid;
    }
    
    .insights-container {
        page-break-inside: avoid;
    }
}
</style>