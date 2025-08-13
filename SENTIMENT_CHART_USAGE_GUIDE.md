# Sentiment vs Price Chart Component Usage Guide

## Overview

Your application already includes a comprehensive Vue.js chart component for analyzing sentiment vs cryptocurrency price correlations with Coingecko API integration. This guide shows you how to use and customize these components.

## Available Components

### 1. Main Chart Component
**Path**: `/resources/js/Components/Charts/SentimentPriceChart.vue`

This is the primary component with full functionality:
- ✅ Multiple chart types (line, scatter, dual-axis)
- ✅ Interactive controls (coin selection, date ranges, platform filtering)
- ✅ Real-time Coingecko API integration
- ✅ Export functionality (CSV, PNG)
- ✅ Statistics panel with correlation analysis
- ✅ Responsive design with loading states

### 2. Page Component
**Path**: `/resources/js/Pages/SentimentAnalysis/SentimentPriceChart.vue`

Complete page implementation with:
- ✅ Enhanced export options (JSON, CSV, PNG)
- ✅ Help documentation
- ✅ Navigation links
- ✅ Correlation insights panel

### 3. Simple Widget Component
**Path**: `/resources/js/Components/Examples/SimpleSentimentChart.vue`

Simplified wrapper for embedding in other pages.

### 4. Dashboard Widget
**Path**: `/resources/js/Components/Examples/DashboardSentimentWidget.vue`

Compact widget for dashboard integration.

## Basic Usage

### Using the Main Component

```vue
<template>
    <div>
        <SentimentPriceChart 
            :initial-coin="'bitcoin'"
            :initial-days="30"
            ref="chartRef"
        />
    </div>
</template>

<script setup>
import { ref } from 'vue'
import SentimentPriceChart from '@/Components/Charts/SentimentPriceChart.vue'

const chartRef = ref(null)

// Access chart data
const getChartData = () => {
    return chartRef.value?.chartData
}

// Export functionality
const exportData = () => {
    chartRef.value?.exportToCSV()
}
</script>
```

### Using the Simple Widget

```vue
<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Bitcoin Analysis -->
        <SimpleSentimentChart 
            title="Bitcoin Sentiment Analysis"
            description="7-day sentiment vs price correlation"
            coin="bitcoin"
            :days="7"
        />
        
        <!-- Ethereum Analysis -->
        <SimpleSentimentChart 
            title="Ethereum Market Sentiment"
            description="30-day analysis with export options"
            coin="ethereum"
            :days="30"
            :show-actions="true"
        />
    </div>
</template>

<script setup>
import SimpleSentimentChart from '@/Components/Examples/SimpleSentimentChart.vue'
</script>
```

### Dashboard Integration

```vue
<template>
    <div class="dashboard-grid">
        <!-- Other dashboard widgets -->
        
        <DashboardSentimentWidget 
            default-coin="bitcoin"
            :timeframe="7"
        />
        
        <!-- More widgets -->
    </div>
</template>

<script setup>
import DashboardSentimentWidget from '@/Components/Examples/DashboardSentimentWidget.vue'
</script>
```

## Component Props

### SentimentPriceChart Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `initialCoin` | String | `'bitcoin'` | Cryptocurrency ID from Coingecko |
| `initialDays` | Number | `30` | Initial date range in days |

### SimpleSentimentChart Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | String | `'Sentiment vs Price Analysis'` | Chart title |
| `description` | String | `''` | Chart description |
| `coin` | String | `'bitcoin'` | Cryptocurrency to analyze |
| `days` | Number | `30` | Date range in days |
| `showActions` | Boolean | `true` | Show export/refresh buttons |

### DashboardSentimentWidget Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `defaultCoin` | String | `'bitcoin'` | Initial cryptocurrency |
| `timeframe` | Number | `7` | Days to analyze (compact view) |

## API Endpoints

The components use these API endpoints:

```javascript
// Get chart data
GET /api/sentiment-charts/data
Parameters:
- coin_id: string (required)
- start_date: date (required)
- end_date: date (required)
- platforms: array (optional)
- categories: array (optional)
- include_price: boolean (default: true)
- include_volume: boolean (default: false)

// Get available coins
GET /api/sentiment-charts/coins

// Search coins
GET /api/sentiment-charts/coins/search?query=bitcoin

// Get sentiment summary
GET /api/sentiment-charts/sentiment-summary
```

## Customization Examples

### Custom Chart Configuration

```vue
<template>
    <SentimentPriceChart 
        :initial-coin="selectedCoin"
        :initial-days="timeRange"
        ref="chart"
        @chart-loaded="handleChartLoaded"
    />
</template>

<script setup>
import { ref, watch } from 'vue'

const selectedCoin = ref('ethereum')
const timeRange = ref(90)

const handleChartLoaded = (data) => {
    console.log('Chart loaded with data:', data)
}

// Watch for changes and trigger updates
watch([selectedCoin, timeRange], () => {
    // Chart will automatically update
})
</script>
```

### Custom Export Handler

```vue
<template>
    <div>
        <SentimentPriceChart ref="chartComponent" />
        
        <button @click="customExport">
            Export to Custom Format
        </button>
    </div>
</template>

<script setup>
const customExport = () => {
    const chartData = chartComponent.value?.chartData
    const statistics = chartComponent.value?.statistics
    
    if (chartData) {
        // Create custom export format
        const customData = {
            timestamp: new Date().toISOString(),
            coin: chartComponent.value.selectedCoin,
            summary: {
                correlation: statistics.correlation_coefficient,
                data_points: statistics.data_points,
                timeframe: `${chartComponent.value.startDate} to ${chartComponent.value.endDate}`
            },
            data: chartData.correlation_data
        }
        
        // Custom export logic here
        console.log('Custom export:', customData)
    }
}
</script>
```

## Available Chart Types

### 1. Line Chart (`chartType: 'line'`)
- Shows sentiment and price trends over time
- Dual Y-axes for different scales
- Good for trend analysis

### 2. Scatter Plot (`chartType: 'scatter'`)
- Shows correlation between sentiment and price change
- Bubble size represents post volume
- Good for correlation analysis

### 3. Dual Axis (`chartType: 'dual'`)
- Overlays sentiment and normalized price
- Shows direct visual correlation
- Good for comparative analysis

## Styling Customization

### Override Component Styles

```vue
<style scoped>
/* Customize chart container */
:deep(.sentiment-price-chart-container) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 20px;
}

/* Hide certain controls */
:deep(.chart-controls) {
    display: none;
}

/* Custom statistics panel */
:deep(.bg-white.p-4.rounded-lg.shadow) {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
</style>
```

### Custom Color Themes

```vue
<script setup>
// Override correlation colors
const getCustomCorrelationColor = (correlation) => {
    if (!correlation) return 'text-gray-400'
    const abs = Math.abs(correlation)
    if (abs >= 0.8) return correlation > 0 ? 'text-emerald-600' : 'text-rose-600'
    if (abs >= 0.5) return correlation > 0 ? 'text-green-500' : 'text-red-500'
    if (abs >= 0.3) return correlation > 0 ? 'text-blue-500' : 'text-orange-500'
    return 'text-gray-400'
}
</script>
```

## Performance Optimization

### Lazy Loading

```vue
<script setup>
import { defineAsyncComponent } from 'vue'

// Lazy load the chart component
const SentimentPriceChart = defineAsyncComponent(() => 
    import('@/Components/Charts/SentimentPriceChart.vue')
)
</script>
```

### Caching

The components automatically cache API responses for:
- **Price data**: 1 hour
- **Coin list**: 24 hours
- **Current prices**: 5 minutes

## Testing

### Component Testing Example

```javascript
import { mount } from '@vue/test-utils'
import SentimentPriceChart from '@/Components/Charts/SentimentPriceChart.vue'

describe('SentimentPriceChart', () => {
    test('renders with default props', () => {
        const wrapper = mount(SentimentPriceChart, {
            props: {
                initialCoin: 'bitcoin',
                initialDays: 30
            }
        })
        
        expect(wrapper.exists()).toBe(true)
        expect(wrapper.find('canvas').exists()).toBe(true)
    })
    
    test('exports data correctly', async () => {
        const wrapper = mount(SentimentPriceChart)
        await wrapper.vm.exportToCSV()
        
        // Test export functionality
    })
})
```

## Troubleshooting

### Common Issues

1. **Chart not loading**: Check if sentiment data exists for the selected date range
2. **Export not working**: Ensure chart data is loaded before attempting export
3. **API errors**: Verify authentication and check network connectivity
4. **Performance issues**: Consider reducing date ranges for large datasets

### Debug Mode

```javascript
// Enable debug logging
localStorage.setItem('sentiment-chart-debug', 'true')

// Check chart state
console.log('Chart data:', chartRef.value?.chartData)
console.log('Statistics:', chartRef.value?.statistics)
```

## Routes

Access the sentiment analysis pages:

- **Main Chart Page**: `/sentiment-analysis/chart`
- **Dashboard**: `/sentiment-analysis`
- **Platform Analysis**: `/sentiment-analysis/platform?platform=twitter`
- **Trends**: `/sentiment-analysis/trends?timeframe=90d`

## Next Steps

1. **Customize** the components for your specific use case
2. **Integrate** widgets into your dashboard
3. **Extend** with additional chart types or data sources
4. **Test** thoroughly with your data sets

For more advanced customization, check the component source files and the Laravel backend services in `/app/Services/CoingeckoService.php` and `/app/Http/Controllers/Api/SentimentChartController.php`.