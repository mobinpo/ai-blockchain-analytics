<template>
    <div class="pdf-report" :class="{ 'print-mode': pdfMode, 'pdf-optimized': pdfMode }">
        <Head :title="data.title || 'Dashboard Report'" />
        
        <!-- Web Interface (shown when not in PDF mode) -->
        <div v-if="!pdfMode" class="web-controls bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-lg mb-8">
            <h1 class="text-3xl font-bold mb-4">📊 Dashboard Report Generator</h1>
            <p class="text-blue-100 mb-6">Generate professional PDF reports from your blockchain analytics dashboard</p>
            
            <div class="flex flex-wrap gap-4">
                <button @click="generatePdf('browserless')" :disabled="generating" 
                        class="bg-white text-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-ink disabled:opacity-50 flex items-center gap-2">
                    <i class="fas fa-chrome"></i>
                    {{ generating && method === 'browserless' ? 'Generating...' : 'Generate with Browserless' }}
                </button>
                
                <button @click="generatePdf('dompdf')" :disabled="generating"
                        class="bg-blue-800 text-white px-6 py-3 rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                    <i class="fas fa-file-pdf"></i>
                    {{ generating && method === 'dompdf' ? 'Generating...' : 'Generate with DomPDF' }}
                </button>
                
                <button @click="refreshPreview" class="bg-transparent border-2 border-white text-white px-6 py-3 rounded-lg hover:bg-white hover:text-blue-600">
                    <i class="fas fa-sync-alt"></i>
                    Refresh Preview
                </button>
            </div>
            
            <div v-if="lastGeneration" class="mt-4 p-4 bg-white/10 rounded-lg">
                <p class="text-sm">
                    <strong>Last Generated:</strong> {{ lastGeneration.method }} in {{ lastGeneration.time }}ms 
                    <a :href="lastGeneration.url" target="_blank" class="underline hover:no-underline ml-2">
                        📥 Download {{ lastGeneration.filename }}
                    </a>
                </p>
            </div>
        </div>
        
        <!-- PDF Header -->
        <div class="pdf-header">
            <div class="header-content">
                <div class="logo-section" v-if="pdfMode">
                    <div class="company-logo">
                        <div class="logo-placeholder">🚀</div>
                        <div class="company-info">
                            <h1 class="company-name">AI Blockchain Analytics</h1>
                            <p class="company-tagline">Advanced Smart Contract Intelligence</p>
                        </div>
                    </div>
                </div>
                
                <div class="report-info">
                    <h1 class="report-title">{{ data.title || 'Blockchain Analytics Dashboard Report' }}</h1>
                    <div class="report-meta">
                        <span class="date-range">{{ formatDateRange(data.date_range) }}</span>
                        <span class="generated-at">Generated: {{ new Date().toLocaleString() }}</span>
                        <span class="report-id">Report ID: {{ generateReportId() }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Executive Summary -->
        <section class="summary-section">
            <h2>Executive Summary</h2>
            <div class="summary-grid">
                <div class="metric-card">
                    <div class="metric-value">{{ formatNumber(data.metrics?.total_posts) }}</div>
                    <div class="metric-label">Total Posts Analyzed</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ formatSentiment(data.metrics?.sentiment_score) }}</div>
                    <div class="metric-label">Overall Sentiment</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ formatNumber(data.metrics?.engagement) }}</div>
                    <div class="metric-label">Total Engagement</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ Object.keys(data.metrics?.platforms || {}).length }}</div>
                    <div class="metric-label">Platforms Monitored</div>
                </div>
            </div>
        </section>

        <!-- Platform Breakdown -->
        <section class="platform-section">
            <h2>Platform Breakdown</h2>
            <div class="platform-grid">
                <div 
                    v-for="(count, platform) in data.metrics?.platforms" 
                    :key="platform"
                    class="platform-card"
                >
                    <div class="platform-header">
                        <div class="platform-icon">{{ getPlatformIcon(platform) }}</div>
                        <h3>{{ formatPlatformName(platform) }}</h3>
                    </div>
                    <div class="platform-stats">
                        <div class="stat">
                            <span class="stat-value">{{ formatNumber(count) }}</span>
                            <span class="stat-label">Posts</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value">{{ calculatePercentage(count, data.metrics?.total_posts) }}%</span>
                            <span class="stat-label">Share</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section v-if="data.charts" class="charts-section">
            <h2>Trend Analysis</h2>
            
            <!-- Sentiment Trend Chart -->
            <div v-if="data.charts.sentiment_trend" class="chart-container">
                <h3>Sentiment Trend Over Time</h3>
                <canvas ref="sentimentChart" class="chart-canvas"></canvas>
            </div>

            <!-- Volume Trend Chart -->
            <div v-if="data.charts.volume_trend" class="chart-container">
                <h3>Volume Trend Over Time</h3>
                <canvas ref="volumeChart" class="chart-canvas"></canvas>
            </div>
        </section>

        <!-- Key Insights -->
        <section class="insights-section">
            <h2>Key Insights</h2>
            <div class="insights-grid">
                <div class="insight-card positive">
                    <div class="insight-icon">📈</div>
                    <div class="insight-content">
                        <h4>Positive Sentiment Growth</h4>
                        <p>Sentiment has improved by {{ formatPercentage(data.insights?.sentiment_growth) }} over the reporting period.</p>
                    </div>
                </div>
                <div class="insight-card neutral">
                    <div class="insight-icon">📊</div>
                    <div class="insight-content">
                        <h4>Platform Diversification</h4>
                        <p>Engagement is well-distributed across {{ Object.keys(data.metrics?.platforms || {}).length }} social media platforms.</p>
                    </div>
                </div>
                <div class="insight-card warning" v-if="data.insights?.volatility_high">
                    <div class="insight-icon">⚠️</div>
                    <div class="insight-content">
                        <h4>High Volatility Period</h4>
                        <p>Sentiment shows increased volatility, indicating market uncertainty.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <div class="pdf-footer">
            <div class="footer-content">
                <div class="footer-left">
                    <span class="company-name">AI Blockchain Analytics</span>
                    <span class="report-type">Dashboard Report</span>
                </div>
                <div class="footer-right">
                    <span class="page-info">Page 1 of 1</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick } from 'vue'
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
    demo_mode: {
        type: Boolean,
        default: false
    },
    options: {
        type: Object,
        default: () => ({})
    }
})

// Refs
const sentimentChart = ref(null)
const volumeChart = ref(null)
const generating = ref(false)
const method = ref('')
const lastGeneration = ref(null)

// Computed
const pdfMode = computed(() => props.pdf_mode || props.options?.pdf_mode)

// Methods
const formatNumber = (num) => {
    if (!num) return '0'
    return new Intl.NumberFormat().format(num)
}

const formatSentiment = (score) => {
    if (!score) return 'N/A'
    const value = parseFloat(score)
    return value >= 0 ? `+${value.toFixed(3)}` : value.toFixed(3)
}

const formatPercentage = (value) => {
    if (!value) return '0'
    return `${(parseFloat(value) * 100).toFixed(1)}`
}

const formatDateRange = (range) => {
    if (!range || !Array.isArray(range)) return 'N/A'
    return `${range[0]} to ${range[1]}`
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

const calculatePercentage = (part, total) => {
    if (!part || !total) return 0
    return Math.round((part / total) * 100)
}

const generateReportId = () => {
    return `RPT-${Date.now().toString(36).toUpperCase()}`
}

// PDF Generation Methods
const generatePdf = async (selectedMethod) => {
    if (generating.value) return
    
    generating.value = true
    method.value = selectedMethod
    
    const startTime = Date.now()
    
    try {
        const endpoint = selectedMethod === 'browserless' ? '/pdf/dashboard' : '/pdf/dashboard'
        
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                data: props.data,
                options: {
                    ...props.options,
                    force_browserless: selectedMethod === 'browserless',
                    filename: `dashboard-report-${new Date().toISOString().split('T')[0]}.pdf`,
                    title: 'Blockchain Analytics Dashboard Report',
                    format: 'A4',
                    orientation: 'portrait'
                }
            })
        })
        
        const result = await response.json()
        const endTime = Date.now()
        
        if (result.success) {
            // Store generation info
            lastGeneration.value = {
                method: result.method || selectedMethod,
                time: endTime - startTime,
                url: result.file_url,
                filename: result.filename
            }
            
            // Auto-download
            const link = document.createElement('a')
            link.href = result.file_url
            link.download = result.filename
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            
            showNotification(`PDF generated successfully using ${result.method}!`, 'success')
        } else {
            console.error('PDF generation failed:', result.error)
            showNotification(`PDF generation failed: ${result.error}`, 'error')
        }
    } catch (error) {
        console.error('PDF generation error:', error)
        showNotification('PDF generation failed. Please try again.', 'error')
    } finally {
        generating.value = false
        method.value = ''
    }
}

const refreshPreview = () => {
    window.location.reload()
}

const showNotification = (message, type) => {
    // Simple notification system
    const notification = document.createElement('div')
    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg font-medium z-50 ${
        type === 'success' 
            ? 'bg-green-500 text-white' 
            : 'bg-red-500 text-white'
    }`
    notification.textContent = message
    
    document.body.appendChild(notification)
    
    setTimeout(() => {
        document.body.removeChild(notification)
    }, 4000)
}

// Initialize charts when component mounts
onMounted(async () => {
    if (props.data.charts && !pdfMode.value) {
        // Only load Chart.js in interactive mode
        await initializeCharts()
    }
    
    // PDF mode initialization
    if (pdfMode.value) {
        // Signal to PDF generator that content is ready
        await nextTick()
        setTimeout(() => {
            if (window.pdfReady) {
                window.pdfReady()
            }
        }, 1000)
    }
})

const initializeCharts = async () => {
    try {
        const { Chart, registerables } = await import('chart.js')
        Chart.register(...registerables)

        // Initialize sentiment chart
        if (props.data.charts.sentiment_trend && sentimentChart.value) {
            new Chart(sentimentChart.value, {
                type: 'line',
                data: {
                    labels: props.data.charts.sentiment_trend.map(item => item.date),
                    datasets: [{
                        label: 'Sentiment Score',
                        data: props.data.charts.sentiment_trend.map(item => item.sentiment),
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: -1,
                            max: 1
                        }
                    }
                }
            })
        }

        // Initialize volume chart
        if (props.data.charts.volume_trend && volumeChart.value) {
            new Chart(volumeChart.value, {
                type: 'bar',
                data: {
                    labels: props.data.charts.volume_trend.map(item => item.date),
                    datasets: [{
                        label: 'Volume',
                        data: props.data.charts.volume_trend.map(item => item.volume),
                        backgroundColor: 'rgba(34, 197, 94, 0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    }
                }
            })
        }
    } catch (error) {
        console.warn('Chart.js not available in PDF mode:', error)
    }
}
</script>

<style scoped>
.pdf-report {
    @apply bg-white text-gray-900 font-sans;
    font-size: 14px;
    line-height: 1.5;
    max-width: 210mm; /* A4 width */
    margin: 0 auto;
}

.print-mode {
    @apply p-6;
}

.pdf-header {
    @apply border-b-2 border-gray-200 pb-6 mb-8;
}

.header-content {
    @apply flex justify-between items-start;
}

.report-title {
    @apply text-2xl font-bold text-gray-900 mb-2;
}

.report-meta {
    @apply text-right text-sm text-gray-600 space-y-1;
}

.report-meta span {
    @apply block;
}

/* Sections */
section {
    @apply mb-8;
}

section h2 {
    @apply text-xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200;
}

section h3 {
    @apply text-lg font-medium text-gray-800 mb-3;
}

/* Summary Grid */
.summary-grid {
    @apply grid grid-cols-2 md:grid-cols-4 gap-4;
}

.metric-card {
    @apply bg-panel p-4 rounded-lg text-center;
}

.metric-value {
    @apply text-2xl font-bold text-gray-900 mb-1;
}

.metric-label {
    @apply text-sm text-gray-600;
}

/* Platform Grid */
.platform-grid {
    @apply grid grid-cols-1 md:grid-cols-3 gap-4;
}

.platform-card {
    @apply bg-panel p-4 rounded-lg;
}

.platform-header {
    @apply flex items-center mb-3;
}

.platform-icon {
    @apply text-2xl mr-3;
}

.platform-header h3 {
    @apply text-lg font-medium text-gray-900 mb-0;
}

.platform-stats {
    @apply grid grid-cols-2 gap-4;
}

.stat {
    @apply text-center;
}

.stat-value {
    @apply block text-lg font-semibold text-gray-900;
}

.stat-label {
    @apply block text-sm text-gray-600;
}

/* Charts */
.charts-section {
    page-break-inside: avoid;
}

.chart-container {
    @apply mb-6;
}

.chart-canvas {
    @apply w-full;
    height: 300px;
}

/* Insights */
.insights-grid {
    @apply grid grid-cols-1 md:grid-cols-2 gap-4;
}

.insight-card {
    @apply p-4 rounded-lg flex items-start space-x-3;
}

.insight-card.positive {
    @apply bg-green-50 border border-green-200;
}

.insight-card.neutral {
    @apply bg-blue-50 border border-blue-200;
}

.insight-card.warning {
    @apply bg-yellow-50 border border-yellow-200;
}

.insight-icon {
    @apply text-2xl flex-shrink-0;
}

.insight-content h4 {
    @apply font-medium text-gray-900 mb-1;
}

.insight-content p {
    @apply text-sm text-gray-700;
}

/* Footer */
.pdf-footer {
    @apply border-t border-gray-200 pt-4 mt-8;
}

.footer-content {
    @apply flex justify-between items-center text-sm text-gray-600;
}

/* Print Styles */
@media print {
    .pdf-report {
        @apply p-0 m-0;
        max-width: none;
    }
    
    .chart-canvas {
        height: 250px;
    }
    
    .insights-grid {
        page-break-inside: avoid;
    }
    
    .chart-container {
        page-break-inside: avoid;
    }
}
</style>