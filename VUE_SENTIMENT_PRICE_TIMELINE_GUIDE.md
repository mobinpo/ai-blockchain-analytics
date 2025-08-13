# 📈 Vue Sentiment vs Price Timeline Chart Component

**Interactive Vue.js chart component displaying cryptocurrency sentiment correlation with price movements, powered by Coingecko API.**

## 🎯 Overview

This comprehensive chart component provides real-time sentiment analysis visualization alongside cryptocurrency price data, featuring:

- **📊 Dual-axis charting** - Sentiment scores (-1 to +1) vs Price movements (USD)
- **🔄 Live data integration** - Coingecko API for real-time price data
- **🎮 Interactive controls** - Multiple cryptocurrencies and timeframes
- **📈 Statistical analysis** - Correlation coefficients and trend analysis
- **🌐 Demo mode** - Realistic demo data when API is unavailable
- **📱 Responsive design** - Works on desktop and mobile devices

## 🏗️ Components

### 1. **EnhancedSentimentPriceTimeline.vue**
Main chart component with advanced features:

```vue
<EnhancedSentimentPriceTimeline
    :initial-coin="'bitcoin'"
    :initial-timeframe="30"
    :height="500"
    :show-volume="false"
    :auto-refresh="false"
    :refresh-interval="300000"
    @data-loaded="onDataLoaded"
    @error="onChartError"
    @coin-changed="onCoinChanged"
    @timeframe-changed="onTimeframeChanged"
/>
```

### 2. **SentimentPriceTimelineDemo.vue**
Complete demo page showcasing all features:

```vue
// Located at: resources/js/Pages/Demo/SentimentPriceTimelineDemo.vue
// Route: /sentiment-timeline-demo
```

## 🚀 Quick Start

### Basic Usage

```vue
<template>
    <div>
        <EnhancedSentimentPriceTimeline
            :initial-coin="'ethereum'"
            :initial-timeframe="7"
            :height="400"
        />
    </div>
</template>

<script setup>
import EnhancedSentimentPriceTimeline from '@/Components/Charts/EnhancedSentimentPriceTimeline.vue'
</script>
```

### Advanced Configuration

```vue
<template>
    <EnhancedSentimentPriceTimeline
        ref="chartRef"
        :initial-coin="selectedCoin"
        :initial-timeframe="timeframe"
        :height="600"
        :show-volume="true"
        :auto-refresh="true"
        :refresh-interval="300000"
        @data-loaded="handleDataLoaded"
        @error="handleError"
        @coin-changed="handleCoinChange"
        @timeframe-changed="handleTimeframeChange"
    />
</template>

<script setup>
import { ref } from 'vue'

const chartRef = ref(null)
const selectedCoin = ref('bitcoin')
const timeframe = ref(30)

const handleDataLoaded = (data) => {
    console.log('Chart data loaded:', data)
    // Access sentiment data: data.sentiment
    // Access price data: data.price
    // Access correlation: data.correlation
}

const handleError = (error) => {
    console.error('Chart error:', error)
    // Handle error (show notification, fallback to demo data, etc.)
}

const handleCoinChange = (newCoin) => {
    selectedCoin.value = newCoin
    console.log('Coin changed to:', newCoin)
}

const handleTimeframeChange = (newTimeframe) => {
    timeframe.value = newTimeframe
    console.log('Timeframe changed to:', newTimeframe)
}

// Programmatically control the chart
const refreshChart = () => {
    if (chartRef.value) {
        chartRef.value.refreshData()
    }
}

const switchToLiveData = () => {
    if (chartRef.value) {
        chartRef.value.useCoingecko = true
        chartRef.value.refreshData()
    }
}

const switchToDemoData = () => {
    if (chartRef.value) {
        chartRef.value.useCoingecko = false
        chartRef.value.refreshData()
    }
}
</script>
```

## 📊 API Integration

### Endpoints

| Endpoint | Description | Parameters |
|----------|-------------|------------|
| `GET /api/sentiment-price-timeline` | Live data with Coingecko API | `coin`, `days`, `include_volume` |
| `GET /api/sentiment-price-timeline/demo` | Demo data for testing | `coin`, `days`, `include_volume` |
| `GET /api/sentiment-price-timeline/coins` | Available cryptocurrencies | None |

### Example API Calls

```bash
# Get Bitcoin sentiment vs price for 30 days
curl "http://localhost:8003/api/sentiment-price-timeline?coin=bitcoin&days=30&include_volume=true"

# Get demo data for Ethereum
curl "http://localhost:8003/api/sentiment-price-timeline/demo?coin=ethereum&days=7"

# Get available coins
curl "http://localhost:8003/api/sentiment-price-timeline/coins"
```

### API Response Format

```json
{
  "success": true,
  "data": {
    "sentiment_data": [
      {
        "date": "2025-01-15",
        "sentiment": 0.234
      }
    ],
    "price_data": [
      {
        "date": "2025-01-15",
        "price": 45000.50,
        "volume": 1234567890
      }
    ],
    "correlation": 0.756
  },
  "stats": {
    "avg_sentiment": 0.123,
    "price_change": 5.67,
    "correlation": 0.756,
    "data_points": 30,
    "volatility": 0.234
  },
  "metadata": {
    "coin": "bitcoin",
    "symbol": "BTC",
    "currency": "usd",
    "days": 30,
    "platforms": ["twitter", "reddit"],
    "start_date": "2024-12-16",
    "end_date": "2025-01-15",
    "is_demo": false,
    "include_volume": true
  }
}
```

## 🎛️ Component Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `initialCoin` | String | `'bitcoin'` | Initial cryptocurrency to display |
| `initialTimeframe` | Number | `30` | Initial timeframe in days |
| `height` | Number | `400` | Chart height in pixels |
| `showVolume` | Boolean | `false` | Include volume data in chart |
| `autoRefresh` | Boolean | `false` | Enable automatic data refresh |
| `refreshInterval` | Number | `300000` | Auto refresh interval (5 minutes) |

## 📡 Component Events

| Event | Payload | Description |
|-------|---------|-------------|
| `data-loaded` | `{ sentiment, price, correlation }` | Fired when chart data is loaded |
| `error` | `string` | Fired when an error occurs |
| `coin-changed` | `string` | Fired when cryptocurrency is changed |
| `timeframe-changed` | `number` | Fired when timeframe is changed |

## 🎨 Features

### Interactive Controls

- **🪙 Cryptocurrency Selector**: Bitcoin, Ethereum, Cardano, Solana, Polygon, etc.
- **⏱️ Timeframe Options**: 7, 14, 30, 90, 180, 365 days
- **🔄 Data Source Toggle**: Switch between live Coingecko data and demo data
- **📊 Volume Display**: Optional trading volume overlay
- **🔄 Auto Refresh**: Configurable automatic data updates

### Chart Features

- **📈 Dual Y-Axes**: Sentiment (-1 to +1) and Price (USD) scales
- **🎯 Interactive Tooltips**: Hover for detailed data points
- **📱 Responsive Design**: Adapts to different screen sizes
- **🌙 Dark Mode Support**: Automatic theme detection
- **⚡ Smooth Animations**: Chart.js powered smooth transitions

### Statistical Analysis

- **📊 Correlation Coefficient**: Statistical relationship between sentiment and price
- **📈 Price Change**: Percentage change over selected period
- **📉 Average Sentiment**: Mean sentiment score for the period
- **📊 Volatility**: Annualized price volatility calculation
- **🔢 Data Points**: Total number of sentiment/price pairs

### Data Sources

- **🟢 Live Data**: Real-time Coingecko API integration
- **🔵 Demo Data**: Realistic simulated data for testing
- **🔄 Automatic Fallback**: Falls back to demo data if API fails
- **⚡ Caching**: Intelligent caching for better performance

## 📱 Demo Page

Visit `/sentiment-timeline-demo` to see the component in action with:

- **🎮 Interactive Controls**: Change coins, timeframes, and settings
- **📊 Real-time Statistics**: Live correlation and trend analysis
- **🔌 API Examples**: Copy-paste API calls for integration
- **🎛️ Chart Settings**: Toggle volume, auto-refresh, and data sources

### Demo Page Features

```vue
// Demo page includes:
- Feature highlights with icons
- Interactive chart controls
- Real-time statistics display
- API usage examples
- Chart settings panel
- Data source switching
- Copy-to-clipboard functionality
```

## 🛠️ Technical Implementation

### Chart.js Integration

```javascript
// Uses Chart.js with time series support
import { Chart, registerables } from 'chart.js'
import 'chartjs-adapter-date-fns'

Chart.register(...registerables)

// Dual-axis configuration
scales: {
    sentiment: {
        type: 'linear',
        position: 'left',
        min: -1,
        max: 1
    },
    price: {
        type: 'linear',
        position: 'right',
        ticks: {
            callback: (value) => '$' + value.toLocaleString()
        }
    }
}
```

### Data Processing

```javascript
// Correlation calculation
const calculateCorrelation = (sentimentData, priceData) => {
    // Pearson correlation coefficient implementation
    // Returns value between -1 (perfect negative) and +1 (perfect positive)
}

// Data alignment by date
const alignedData = sentimentData.map(sentiment => {
    const pricePoint = priceData.find(price => price.date === sentiment.date)
    return pricePoint ? { sentiment: sentiment.sentiment, price: pricePoint.price } : null
}).filter(Boolean)
```

### Error Handling

```javascript
// Comprehensive error handling
try {
    const result = await axios.get('/api/sentiment-price-timeline', { params })
    // Process successful response
} catch (error) {
    console.error('API error:', error)
    // Fallback to demo data
    useCoingecko.value = false
    await loadData()
}
```

## 🎯 Use Cases

### 1. **Cryptocurrency Analysis**
```vue
<EnhancedSentimentPriceTimeline
    :initial-coin="'bitcoin'"
    :initial-timeframe="90"
    :show-volume="true"
    @data-loaded="analyzeCrypto"
/>
```

### 2. **Market Research Dashboard**
```vue
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <EnhancedSentimentPriceTimeline :initial-coin="'bitcoin'" />
    <EnhancedSentimentPriceTimeline :initial-coin="'ethereum'" />
</div>
```

### 3. **Real-time Monitoring**
```vue
<EnhancedSentimentPriceTimeline
    :auto-refresh="true"
    :refresh-interval="60000"
    @data-loaded="updateAlerts"
/>
```

### 4. **Educational Tool**
```vue
<EnhancedSentimentPriceTimeline
    :initial-coin="'bitcoin'"
    :show-volume="false"
    @coin-changed="explainCorrelation"
/>
```

## 🔧 Customization

### Styling

```vue
<style scoped>
/* Custom chart container */
.enhanced-sentiment-price-timeline {
    @apply w-full border rounded-lg shadow-sm;
}

/* Custom control styling */
.chart-controls select {
    @apply px-3 py-2 border border-gray-300 rounded-md;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .chart-container {
        background-color: #1f2937;
        color: #f9fafb;
    }
}
</style>
```

### Custom Themes

```javascript
// Chart color customization
const chartTheme = {
    sentiment: {
        borderColor: 'rgb(59, 130, 246)',
        backgroundColor: 'rgba(59, 130, 246, 0.1)'
    },
    price: {
        borderColor: 'rgb(16, 185, 129)',
        backgroundColor: 'rgba(16, 185, 129, 0.1)'
    }
}
```

## 📊 Available Cryptocurrencies

| Coin | Symbol | Coingecko ID |
|------|--------|--------------|
| Bitcoin | BTC | bitcoin |
| Ethereum | ETH | ethereum |
| Cardano | ADA | cardano |
| Solana | SOL | solana |
| Polygon | MATIC | polygon |
| Polkadot | DOT | polkadot |
| Chainlink | LINK | chainlink |
| BNB | BNB | binancecoin |
| Avalanche | AVAX | avalanche-2 |

## 🚀 Performance

### Optimization Features

- **📦 Code Splitting**: Lazy loading of Chart.js components
- **🔄 Request Debouncing**: Prevents excessive API calls
- **💾 Intelligent Caching**: Caches API responses for better performance
- **⚡ Incremental Updates**: Only updates changed data points
- **📱 Responsive Loading**: Adapts chart size to viewport

### Best Practices

```javascript
// Debounced data loading
const debouncedLoadData = debounce(loadData, 500)

// Cleanup on unmount
onUnmounted(() => {
    if (chartInstance.value) {
        chartInstance.value.destroy()
    }
    clearInterval(refreshTimer.value)
})

// Memory management
watch(() => props.autoRefresh, (newValue) => {
    if (newValue) {
        setupAutoRefresh()
    } else {
        clearAutoRefresh()
    }
})
```

## 🎉 Summary

This Vue Sentiment vs Price Timeline Chart Component provides:

✅ **Interactive Visualization** - Real-time sentiment and price correlation  
✅ **Coingecko Integration** - Live cryptocurrency price data  
✅ **Multiple Timeframes** - 7 days to 1 year analysis periods  
✅ **Statistical Analysis** - Correlation coefficients and trend metrics  
✅ **Demo Mode** - Realistic demo data for testing and development  
✅ **Responsive Design** - Works on desktop and mobile devices  
✅ **Dark Mode Support** - Automatic theme detection and switching  
✅ **Error Handling** - Graceful fallbacks and error recovery  
✅ **Performance Optimized** - Efficient data loading and chart updates  
✅ **Customizable** - Flexible props and styling options  

**Perfect for cryptocurrency analysis, market research, and financial dashboards!** 📈🚀

---

## 🔗 Routes & Access

- **Demo Page**: `/sentiment-timeline-demo`
- **API Endpoints**: `/api/sentiment-price-timeline/*`
- **Component**: `@/Components/Charts/EnhancedSentimentPriceTimeline.vue`