<template>
    <div class="sentiment-price-chart-pdf">
        <!-- PDF Header -->
        <div class="pdf-header">
            <h1 class="pdf-title">{{ title || `${coinSymbol.toUpperCase()} Sentiment vs Price Analysis` }}</h1>
            <div class="pdf-meta">
                <span>Generated on {{ formatDate(new Date()) }}</span>
                <span v-if="metadata?.start_date && metadata?.end_date">
                    • {{ formatDate(metadata.start_date) }} to {{ formatDate(metadata.end_date) }}
                </span>
                <span v-if="stats?.data_points">• {{ stats.data_points }} Data Points</span>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Average Sentiment</div>
                <div class="summary-value" :class="getSentimentColorClass(stats?.avg_sentiment)">
                    {{ formatSentiment(stats?.avg_sentiment) }}
                </div>
                <div class="summary-sublabel">{{ getSentimentLabel(stats?.avg_sentiment) }}</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Price Change</div>
                <div class="summary-value" :class="getPriceChangeColorClass(stats?.price_change)">
                    {{ formatPriceChange(stats?.price_change) }}
                </div>
                <div class="summary-sublabel">{{ metadata?.days }} day period</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Correlation</div>
                <div class="summary-value" :class="getCorrelationColorClass(stats?.correlation)">
                    {{ formatCorrelation(stats?.correlation) }}
                </div>
                <div class="summary-sublabel">{{ getCorrelationStrength(stats?.correlation) }}</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Data Source</div>
                <div class="summary-value">
                    {{ metadata?.is_demo ? 'Demo' : 'Live' }}
                </div>
                <div class="summary-sublabel">{{ metadata?.is_demo ? 'Simulated data' : 'CoinGecko API' }}</div>
            </div>
        </div>

        <!-- Chart Container -->
        <div class="chart-section">
            <h2 class="section-title">Price and Sentiment Timeline</h2>
            <div class="chart-container">
                <canvas ref="chartCanvas" :width="chartWidth" :height="chartHeight"></canvas>
                
                <!-- Fallback message for when Chart.js fails -->
                <div v-if="chartError" class="chart-fallback">
                    <div class="fallback-icon">📊</div>
                    <div class="fallback-message">Chart Rendering Error</div>
                    <div class="fallback-detail">{{ chartError }}</div>
                </div>
            </div>
            
            <!-- Chart Legend -->
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-color sentiment-color"></div>
                    <span>Sentiment Score (-1 to +1)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color price-color"></div>
                    <span>{{ coinSymbol.toUpperCase() }} Price (USD)</span>
                </div>
            </div>
        </div>

        <!-- Correlation Analysis -->
        <div v-if="stats?.correlation !== null" class="correlation-section">
            <h2 class="section-title">Correlation Analysis</h2>
            <div class="correlation-card">
                <div class="correlation-value" :class="getCorrelationColorClass(stats.correlation)">
                    {{ formatCorrelation(stats.correlation) }}
                </div>
                <div class="correlation-description">
                    <div class="correlation-strength">{{ getCorrelationStrength(stats.correlation) }}</div>
                    <div class="correlation-explanation">
                        {{ getCorrelationExplanation(stats.correlation) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div v-if="showDataTable && tableData?.length" class="data-section">
            <h2 class="section-title">Historical Data</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sentiment</th>
                            <th>Category</th>
                            <th>Price (USD)</th>
                            <th>Change %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in limitedTableData" :key="index">
                            <td>{{ formatDate(row.date) }}</td>
                            <td :class="getSentimentColorClass(row.sentiment)">
                                {{ formatSentiment(row.sentiment) }}
                            </td>
                            <td :class="getSentimentColorClass(row.sentiment)">
                                {{ getSentimentLabel(row.sentiment) }}
                            </td>
                            <td>${{ formatPrice(row.price) }}</td>
                            <td :class="getPriceChangeColorClass(row.priceChange)">
                                {{ formatPriceChange(row.priceChange) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="pdf-footer">
            <div class="footer-line">
                Generated by AI Blockchain Analytics Platform
            </div>
            <div class="footer-line">
                <span>Sentiment Analysis: Google Cloud NLP</span>
                <span v-if="!metadata?.is_demo"> • Price Data: CoinGecko API</span>
                <span v-if="metadata?.is_demo"> • Demo Data for Testing</span>
            </div>
            <div class="footer-line">
                Report generated at {{ formatDateTime(new Date()) }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'

// Props
const props = defineProps({
    chartData: {
        type: Object,
        default: () => ({})
    },
    stats: {
        type: Object,
        default: () => ({})
    },
    metadata: {
        type: Object,
        default: () => ({})
    },
    coinSymbol: {
        type: String,
        default: 'BTC'
    },
    title: {
        type: String,
        default: ''
    },
    showDataTable: {
        type: Boolean,
        default: true
    },
    chartWidth: {
        type: Number,
        default: 800
    },
    chartHeight: {
        type: Number,
        default: 400
    },
    maxTableRows: {
        type: Number,
        default: 20
    }
})

// Reactive state
const chartCanvas = ref(null)
const chartInstance = ref(null)
const chartError = ref('')

// Computed properties
const tableData = computed(() => {
    if (!props.chartData?.sentiment || !props.chartData?.price) return []
    
    return props.chartData.sentiment.map((sentimentPoint, index) => {
        const pricePoint = props.chartData.price[index]
        const prevPricePoint = index > 0 ? props.chartData.price[index - 1] : null
        
        const priceChange = prevPricePoint && prevPricePoint.y > 0 
            ? ((pricePoint?.y - prevPricePoint.y) / prevPricePoint.y) * 100 
            : 0
        
        return {
            date: new Date(sentimentPoint.x),
            sentiment: sentimentPoint.y,
            price: pricePoint?.y || 0,
            priceChange
        }
    })
})

const limitedTableData = computed(() => {
    return tableData.value.slice(0, props.maxTableRows)
})

// Chart initialization
const initializeChart = async () => {
    if (!chartCanvas.value || !props.chartData?.sentiment || !props.chartData?.price) return
    
    try {
        // Dynamic import of Chart.js
        const { Chart, registerables } = await import('chart.js/auto')
        Chart.register(...registerables)
        
        // Import date adapter
        await import('chartjs-adapter-date-fns')
        
        const ctx = chartCanvas.value.getContext('2d')
        
        // Destroy existing chart
        if (chartInstance.value) {
            chartInstance.value.destroy()
        }
        
        chartInstance.value = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: 'Sentiment Score',
                        data: props.chartData.sentiment,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'sentiment',
                        tension: 0.4,
                        pointRadius: 1,
                        pointHoverRadius: 3
                    },
                    {
                        label: `${props.coinSymbol.toUpperCase()} Price`,
                        data: props.chartData.price,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'price',
                        tension: 0.4,
                        pointRadius: 1,
                        pointHoverRadius: 3
                    }
                ]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                animation: false, // Disable animations for PDF
                plugins: {
                    legend: {
                        display: false // We have our own legend
                    },
                    tooltip: {
                        enabled: false // Not needed in PDF
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MMM dd'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Date',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    sentiment: {
                        type: 'linear',
                        position: 'left',
                        min: -1,
                        max: 1,
                        title: {
                            display: true,
                            text: 'Sentiment Score',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            font: {
                                size: 10
                            },
                            callback: function(value) {
                                return value.toFixed(1)
                            }
                        }
                    },
                    price: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: `Price (${(props.chartData.currency || 'USD').toUpperCase()})`,
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            font: {
                                size: 10
                            },
                            callback: function(value) {
                                return '$' + value.toLocaleString()
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        })
        
    } catch (error) {
        console.error('Chart initialization failed:', error)
        chartError.value = error.message
    }
}

// Utility functions
const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    })
}

const formatDateTime = (date) => {
    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    })
}

const formatSentiment = (value) => {
    if (value == null) return 'N/A'
    return (value * 100).toFixed(1) + '%'
}

const formatPrice = (value) => {
    if (value == null) return '0.00'
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value)
}

const formatPriceChange = (value) => {
    if (value == null) return 'N/A'
    return (value >= 0 ? '+' : '') + value.toFixed(2) + '%'
}

const formatCorrelation = (value) => {
    if (value == null) return 'N/A'
    return (value >= 0 ? '+' : '') + value.toFixed(3)
}

const getSentimentLabel = (value) => {
    if (value == null) return 'Unknown'
    if (value > 0.6) return 'Very Positive'
    if (value > 0.2) return 'Positive'
    if (value > -0.2) return 'Neutral'
    if (value > -0.6) return 'Negative'
    return 'Very Negative'
}

const getCorrelationStrength = (value) => {
    if (value == null) return 'Unknown'
    const abs = Math.abs(value)
    if (abs > 0.8) return 'Very Strong'
    if (abs > 0.6) return 'Strong'
    if (abs > 0.4) return 'Moderate'
    if (abs > 0.2) return 'Weak'
    return 'Very Weak'
}

const getCorrelationExplanation = (value) => {
    if (value == null) return 'Insufficient data for correlation analysis.'
    
    const abs = Math.abs(value)
    const direction = value > 0 ? 'positive' : 'negative'
    
    if (abs > 0.7) {
        return `Strong ${direction} correlation indicates sentiment and price move together ${value > 0 ? 'in the same direction' : 'in opposite directions'}.`
    } else if (abs > 0.3) {
        return `Moderate ${direction} correlation suggests some relationship between sentiment and price movements.`
    } else {
        return 'Weak correlation indicates little relationship between sentiment and price movements.'
    }
}

// Color utility functions
const getSentimentColorClass = (value) => {
    if (value > 0.2) return 'positive'
    if (value < -0.2) return 'negative'
    return 'neutral'
}

const getPriceChangeColorClass = (value) => {
    if (value > 0) return 'positive'
    if (value < 0) return 'negative'
    return 'neutral'
}

const getCorrelationColorClass = (value) => {
    if (value == null) return 'neutral'
    const abs = Math.abs(value)
    if (abs > 0.6) return 'strong'
    if (abs > 0.3) return 'moderate'
    return 'weak'
}

// Lifecycle
onMounted(async () => {
    await nextTick()
    await initializeChart()
})
</script>

<style scoped>
.sentiment-price-chart-pdf {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #374151;
    background: white;
    padding: 20px;
    max-width: 100%;
}

.pdf-header {
    text-align: center;
    margin-bottom: 30px;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 20px;
}

.pdf-title {
    font-size: 24px;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 8px;
}

.pdf-meta {
    font-size: 14px;
    color: #6b7280;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.summary-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    background: #f9fafb;
}

.summary-label {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
}

.summary-value {
    font-size: 20px;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 4px;
}

.summary-sublabel {
    font-size: 10px;
    color: #9ca3af;
}

.section-title {
    font-size: 18px;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 15px;
}

.chart-section {
    margin-bottom: 30px;
}

.chart-container {
    position: relative;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    background: white;
    margin-bottom: 15px;
}

.chart-fallback {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.fallback-icon {
    font-size: 48px;
    margin-bottom: 10px;
}

.fallback-message {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 5px;
}

.fallback-detail {
    font-size: 12px;
    color: #9ca3af;
}

.chart-legend {
    display: flex;
    justify-content: center;
    gap: 30px;
    font-size: 12px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-color {
    width: 16px;
    height: 3px;
    border-radius: 2px;
}

.sentiment-color {
    background-color: #3b82f6;
}

.price-color {
    background-color: #10b981;
}

.correlation-section {
    margin-bottom: 30px;
}

.correlation-card {
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.correlation-value {
    font-size: 32px;
    font-weight: bold;
    min-width: 120px;
    text-align: center;
}

.correlation-description {
    flex: 1;
}

.correlation-strength {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.correlation-explanation {
    font-size: 12px;
    color: #6b7280;
    line-height: 1.5;
}

.data-section {
    margin-bottom: 30px;
}

.table-container {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

.data-table th,
.data-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.data-table th {
    background: #f3f4f6;
    font-weight: 600;
    color: #374151;
}

.data-table tr:nth-child(even) {
    background: #f9fafb;
}

.pdf-footer {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
    font-size: 10px;
    color: #6b7280;
}

.footer-line {
    margin-bottom: 5px;
}

/* Color classes */
.positive { color: #059669; }
.negative { color: #dc2626; }
.neutral { color: #6b7280; }
.strong { color: #1e40af; }
.moderate { color: #f59e0b; }
.weak { color: #6b7280; }

/* Print styles */
@media print {
    .sentiment-price-chart-pdf {
        font-size: 10px;
        padding: 10px;
    }
    
    .pdf-title {
        font-size: 20px;
    }
    
    .summary-value {
        font-size: 16px;
    }
    
    .section-title {
        font-size: 14px;
    }
    
    .correlation-value {
        font-size: 24px;
    }
}

/* PDF-specific styles */
@page {
    margin: 1cm;
    size: A4 landscape;
}

.chart-container {
    page-break-inside: avoid;
}

.data-section {
    page-break-before: avoid;
}
</style>