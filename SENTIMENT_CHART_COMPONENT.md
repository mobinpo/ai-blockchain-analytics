# 📈 Sentiment vs Price Chart Component

A comprehensive Vue.js component that visualizes the correlation between cryptocurrency sentiment analysis and price movements using Chart.js with optional Coingecko API integration.

## 🚀 Overview

The `SentimentPriceChart` component creates an interactive timeline chart that displays:
- **Sentiment scores** from our Google Cloud NLP pipeline
- **Cryptocurrency prices** from Coingecko API or mock data
- **Statistical correlation** analysis and trends
- **Real-time updates** with user controls

## 📋 Table of Contents

- [Features](#-features)
- [Installation](#-installation)
- [Usage](#-usage)
- [Props](#-props)
- [API Integration](#-api-integration)
- [Examples](#-examples)
- [Customization](#-customization)
- [Demo](#-demo)

## ✨ Features

- **📊 Dual-Axis Visualization** - Sentiment scores (-1 to 1) and prices on separate scales
- **🔄 Interactive Controls** - Cryptocurrency selection and time range controls
- **📈 Statistical Analysis** - Real-time correlation calculation and trend analysis
- **🌐 API Integration** - Connects to sentiment pipeline and Coingecko APIs
- **📱 Responsive Design** - Works on all screen sizes with TailwindCSS
- **⚡ Performance Optimized** - Efficient data processing and chart updates
- **🎨 Customizable** - Configurable colors, sizes, and data sources
- **🔄 Real-time Updates** - Automatic refresh and data synchronization

## 🛠️ Installation

### Prerequisites

```bash
npm install chart.js chartjs-adapter-date-fns date-fns vue@^3.0.0
```

### Dependencies

The component requires these packages (already included in your Laravel project):

```json
{
  "chart.js": "^4.4.0",
  "chartjs-adapter-date-fns": "^3.0.0",
  "date-fns": "^2.29.0",
  "vue": "^3.4.0"
}
```

## 🎯 Usage

### Basic Usage

```vue
<template>
  <SentimentPriceChart
    title="Bitcoin Sentiment vs Price"
    :default-coin="'bitcoin'"
    :use-coingecko-api="true"
  />
</template>

<script setup>
import SentimentPriceChart from '@/Components/SentimentPriceChart.vue'
</script>
```

### Advanced Configuration

```vue
<template>
  <SentimentPriceChart
    :title="chartTitle"
    :default-coin="selectedCoin"
    :use-coingecko-api="useRealApi"
    :api-endpoint="customEndpoint"
    :width="1000"
    :height="600"
  />
</template>

<script setup>
import { ref } from 'vue'
import SentimentPriceChart from '@/Components/SentimentPriceChart.vue'

const chartTitle = ref('Custom Crypto Analysis')
const selectedCoin = ref('ethereum')
const useRealApi = ref(true)
const customEndpoint = ref('/api/sentiment-pipeline/trends')
</script>
```

## 📝 Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | String | `'Sentiment vs Price Timeline'` | Chart title displayed at the top |
| `width` | Number | `800` | Chart canvas width in pixels |
| `height` | Number | `400` | Chart canvas height in pixels |
| `defaultCoin` | String | `'bitcoin'` | Initial cryptocurrency selection |
| `useCoingeckoApi` | Boolean | `true` | Enable Coingecko API for price data |
| `apiEndpoint` | String | `'/api/sentiment-pipeline/trends'` | Sentiment data API endpoint |

### Supported Cryptocurrencies

- `bitcoin` - Bitcoin (BTC)
- `ethereum` - Ethereum (ETH)  
- `cardano` - Cardano (ADA)
- `solana` - Solana (SOL)
- `polkadot` - Polkadot (DOT)
- `chainlink` - Chainlink (LINK)

## 🌐 API Integration

### Sentiment Data API

**Endpoint:** `GET /api/sentiment-pipeline/trends`

**Parameters:**
- `days` - Number of days (7, 30, 90, 365)
- `platform` - Platform filter (all, twitter, reddit, telegram)
- `category` - Category filter (crypto, defi, nft, etc.)

**Response:**
```json
{
  "success": true,
  "trends": {
    "daily_sentiment": [
      {
        "date": "2024-01-15",
        "sentiment": 0.234,
        "posts": 150,
        "volatility": 0.123
      }
    ]
  }
}
```

### Coingecko Price API

**Endpoint:** `GET https://api.coingecko.com/api/v3/coins/{id}/market_chart`

**Parameters:**
- `vs_currency=usd` - Currency (USD)
- `days` - Time range
- `interval=daily` - Data interval

**Response:**
```json
{
  "prices": [
    [1705276800000, 42500.50],
    [1705363200000, 43200.75]
  ]
}
```

## 💡 Examples

### Example 1: Basic Bitcoin Chart

```vue
<SentimentPriceChart
  title="Bitcoin Market Sentiment"
  default-coin="bitcoin"
/>
```

### Example 2: Custom Configuration

```vue
<SentimentPriceChart
  title="Ethereum Analysis Dashboard"
  :default-coin="'ethereum'"
  :use-coingecko-api="false"
  :width="1200"
  :height="600"
  api-endpoint="/api/custom-sentiment"
/>
```

### Example 3: Dynamic Chart

```vue
<template>
  <div>
    <select v-model="selectedCoin">
      <option value="bitcoin">Bitcoin</option>
      <option value="ethereum">Ethereum</option>
    </select>
    
    <SentimentPriceChart
      :title="`${coinName} Sentiment Analysis`"
      :default-coin="selectedCoin"
      :key="selectedCoin"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const selectedCoin = ref('bitcoin')
const coinName = computed(() => 
  selectedCoin.value.charAt(0).toUpperCase() + selectedCoin.value.slice(1)
)
</script>
```

## 🎨 Customization

### Color Scheme

The chart uses these default colors:
- **Sentiment Line:** `rgb(59, 130, 246)` (Blue)
- **Price Line:** `rgb(16, 185, 129)` (Green)
- **Correlation Line:** `rgba(168, 85, 247, 0.6)` (Purple, dashed)

### Styling Classes

```css
.sentiment-price-chart {
  @apply w-full bg-white rounded-lg shadow-lg p-6;
}

.chart-container {
  @apply relative mb-6;
  height: 400px;
}

.chart-stats {
  @apply grid grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg;
}

.stat-value.positive {
  @apply text-green-600;
}

.stat-value.negative {
  @apply text-red-600;
}
```

### Dark Mode Support

```css
@media (prefers-color-scheme: dark) {
  .sentiment-price-chart {
    @apply bg-gray-800 text-white;
  }
  
  .chart-stats {
    @apply bg-gray-700;
  }
}
```

## 📊 Data Processing

### Correlation Calculation

The component calculates Pearson correlation coefficient:

```javascript
function calculateCorrelation(x, y) {
  const n = x.length
  const sumX = x.reduce((sum, val) => sum + val, 0)
  const sumY = y.reduce((sum, val) => sum + val, 0)
  const sumXY = x.reduce((sum, val, i) => sum + val * y[i], 0)
  const sumX2 = x.reduce((sum, val) => sum + val * val, 0)
  const sumY2 = y.reduce((sum, val) => sum + val * val, 0)
  
  const numerator = n * sumXY - sumX * sumY
  const denominator = Math.sqrt((n * sumX2 - sumX * sumX) * (n * sumY2 - sumY * sumY))
  
  return denominator === 0 ? 0 : numerator / denominator
}
```

### Mock Data Generation

When Coingecko API is disabled, the component generates realistic mock data:

```javascript
const generateMockPriceData = () => {
  const basePrice = selectedCoin.value === 'bitcoin' ? 45000 : 3000
  const data = []
  
  for (let i = 0; i < timeRange.value; i++) {
    const randomChange = (Math.random() - 0.5) * 0.1
    const price = basePrice * (1 + randomChange * i / timeRange.value)
    
    data.push({
      date: date.toISOString().split('T')[0],
      price: Math.max(price, basePrice * 0.5)
    })
  }
  
  return data
}
```

## 🚀 Demo

### Live HTML Demo

Access the standalone demo at: `http://localhost:8000/sentiment-chart-demo.html`

### Vue Component Demo

Visit the integrated demo: `http://localhost:8000/sentiment-chart-demo`

### Features Demonstrated

- ✅ Interactive cryptocurrency selection
- ✅ Time range controls (7 days to 1 year)
- ✅ Real-time correlation calculation
- ✅ Dual-axis price and sentiment visualization
- ✅ Statistical analysis display
- ✅ Responsive design and tooltips
- ✅ Mock data fallback
- ✅ Loading states and error handling

## 🔧 Troubleshooting

### Common Issues

1. **Chart not rendering**
   ```bash
   # Ensure Chart.js is properly installed
   npm install chart.js chartjs-adapter-date-fns
   ```

2. **API CORS errors**
   ```javascript
   // Add to your Laravel cors.php config
   'allowed_origins' => ['*'],
   'allowed_headers' => ['*'],
   ```

3. **Date formatting issues**
   ```javascript
   // Ensure proper date format (YYYY-MM-DD)
   date: new Date().toISOString().split('T')[0]
   ```

4. **Vue 3 Composition API**
   ```javascript
   // Use proper imports
   import { ref, onMounted, computed } from 'vue'
   ```

## 📊 Performance Tips

- Use `watch` with debouncing for frequent updates
- Implement virtual scrolling for large datasets
- Cache API responses to reduce network calls
- Use Chart.js update methods instead of recreating charts

## 🤝 Contributing

1. Follow Vue 3 Composition API patterns
2. Use TypeScript for better type safety
3. Add comprehensive tests for new features
4. Update documentation for any API changes

---

**Built with ❤️ for AI Blockchain Analytics** 