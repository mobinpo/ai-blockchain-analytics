# Sentiment vs Price Chart Component Guide

A comprehensive Vue.js chart component that visualizes the correlation between social media sentiment and cryptocurrency prices using Chart.js and Coingecko API integration.

## Overview

The Sentiment vs Price Chart component provides real-time visualization of:
- **Sentiment Timeline**: Social media sentiment scores over time
- **Price Correlation**: Cryptocurrency price movements aligned with sentiment
- **Statistical Analysis**: Correlation coefficients and trend analysis
- **Interactive Charts**: Multiple chart types (line, scatter, dual-axis)
- **Data Export**: Export capabilities for further analysis

## Architecture

### Backend Components

#### Services
- **`CoingeckoService`** - Fetches cryptocurrency price data from Coingecko API
- **`SentimentChartController`** - API endpoints for chart data
- **`SentimentAnalysisController`** - Web controllers for chart pages

#### API Endpoints
```
GET /api/sentiment-charts/data              - Get sentiment vs price correlation data
GET /api/sentiment-charts/coins             - Get available cryptocurrencies  
GET /api/sentiment-charts/coins/search      - Search for specific cryptocurrencies
GET /api/sentiment-charts/sentiment-summary - Get sentiment summary statistics
```

#### Web Routes
```
GET /sentiment-analysis/                    - Main dashboard
GET /sentiment-analysis/chart              - Sentiment vs price chart page
GET /sentiment-analysis/platform           - Platform-specific analysis
GET /sentiment-analysis/trends             - Long-term trend analysis
GET /sentiment-analysis/correlations       - Multi-coin correlation analysis
```

### Frontend Components

#### Vue Components
- **`SentimentPriceChart.vue`** - Main interactive chart component
- **`SentimentPriceChart.vue`** (Page) - Full page wrapper with additional features

#### Dependencies
- **Chart.js 4.4.0** - Charting library for interactive visualizations
- **Vue 3 Composition API** - Reactive state management
- **Axios** - HTTP client for API requests
- **TailwindCSS** - Styling and responsive design

## Features

### Chart Types

#### 1. Line Chart
- **Purpose**: Show sentiment and price trends over time
- **Best For**: Identifying general trends and patterns
- **Data**: Dual y-axis with sentiment (-1 to +1) and price change (%)

#### 2. Scatter Plot
- **Purpose**: Visualize correlation between sentiment and price changes
- **Best For**: Identifying correlation strength and outliers
- **Data**: X-axis = sentiment, Y-axis = price change, bubble size = post volume

#### 3. Dual Axis Chart
- **Purpose**: Direct comparison of sentiment and price movements
- **Best For**: Analyzing immediate correlation and timing
- **Data**: Normalized sentiment and price on separate scales

### Interactive Features

#### Date Range Selection
```javascript
// Quick range buttons
const quickRanges = [
    { key: '7d', label: 'Last 7 Days', days: 7 },
    { key: '30d', label: 'Last 30 Days', days: 30 },
    { key: '90d', label: 'Last 90 Days', days: 90 },
    { key: '180d', label: 'Last 6 Months', days: 180 },
    { key: '365d', label: 'Last Year', days: 365 }
]
```

#### Filtering Options
- **Cryptocurrency**: Select from 20+ popular coins
- **Platform**: All, Twitter, Reddit, Telegram
- **Category**: All, Blockchain, Security, Contracts, DeFi
- **Time Bucket**: Full day or hourly granularity

#### Real-time Statistics
- **Correlation Coefficient**: Pearson correlation (-1 to +1)
- **Average Sentiment**: Mean sentiment score for period
- **Average Price Change**: Mean daily price change percentage
- **Data Points**: Number of days with complete data

## Setup and Installation

### 1. Install Dependencies

```bash
# Backend dependencies (already included in Laravel)
composer install

# Frontend dependencies
npm install chart.js@^4.4.0
```

### 2. Environment Configuration

Add to `.env`:
```env
# Coingecko API (optional - free tier works without API key)
COINGECKO_API_KEY=your_api_key_here
COINGECKO_RATE_LIMIT=50
COINGECKO_CACHE_TTL=5

# Google Cloud NLP (required for sentiment analysis)
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account-key.json
```

### 3. Database Setup

Ensure sentiment pipeline tables are migrated:
```bash
php artisan migrate
```

### 4. Cache Configuration

Configure Redis for API caching:
```bash
# In .env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Usage

### Basic Implementation

#### 1. Page Integration
```vue
<template>
    <SentimentPriceChart 
        :initial-coin="'bitcoin'"
        :initial-days="30"
    />
</template>

<script setup>
import SentimentPriceChart from '@/Components/Charts/SentimentPriceChart.vue'
</script>
```

#### 2. API Data Fetching
```javascript
// Get sentiment vs price data
const response = await axios.get('/api/sentiment-charts/data', {
    params: {
        coin_id: 'bitcoin',
        start_date: '2024-01-01',
        end_date: '2024-01-31',
        platforms: ['twitter', 'reddit'],
        categories: ['blockchain']
    }
})

const { sentiment_data, price_data, correlation_data, statistics } = response.data
```

#### 3. Chart Rendering
```javascript
import { Chart, registerables } from 'chart.js'
Chart.register(...registerables)

const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates,
        datasets: [
            {
                label: 'Sentiment Score',
                data: sentimentData,
                borderColor: 'rgb(59, 130, 246)',
                yAxisID: 'y'
            },
            {
                label: 'Price Change %',
                data: priceChangeData,
                borderColor: 'rgb(16, 185, 129)',
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: { position: 'left', title: { text: 'Sentiment' }},
            y1: { position: 'right', title: { text: 'Price Change %' }}
        }
    }
})
```

### Advanced Usage

#### Custom Correlation Analysis
```php
// Backend: Calculate custom correlations
$correlationData = $coingeckoService->getCoinSentimentCorrelationData(
    'ethereum',
    Carbon::now()->subDays(90),
    Carbon::now(),
    ['twitter', 'reddit'],
    ['defi', 'security']
);

// Extract correlation metrics
$correlation = $this->calculatePearsonCorrelation(
    collect($correlationData)->pluck('sentiment_data.average_sentiment')->toArray(),
    collect($correlationData)->pluck('price_data.price_change_percent')->toArray()
);
```

#### Real-time Updates
```javascript
// Frontend: Auto-refresh data
const autoRefresh = () => {
    setInterval(() => {
        if (hasValidParams.value) {
            fetchChartData()
        }
    }, 300000) // Refresh every 5 minutes
}
```

## Chart Configuration

### Chart.js Options

#### Line Chart Configuration
```javascript
const lineChartConfig = {
    type: 'line',
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                title: { display: true, text: 'Date' }
            },
            y: {
                position: 'left',
                title: { display: true, text: 'Sentiment Score' },
                min: -1,
                max: 1
            },
            y1: {
                position: 'right',
                title: { display: true, text: 'Price Change %' },
                grid: { drawOnChartArea: false }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    afterBody: (tooltipItems) => {
                        // Custom tooltip content
                        const index = tooltipItems[0].dataIndex
                        return [`Posts: ${data[index].posts}`, `Volatility: ${data[index].volatility}`]
                    }
                }
            }
        }
    }
}
```

#### Scatter Plot Configuration
```javascript
const scatterConfig = {
    type: 'scatter',
    data: {
        datasets: [{
            data: data.map(d => ({
                x: d.sentiment,
                y: d.price_change,
                r: Math.max(3, Math.min(15, d.posts / 10)) // Bubble size
            }))
        }]
    },
    options: {
        scales: {
            x: { title: { text: 'Sentiment Score' }},
            y: { title: { text: 'Price Change %' }}
        }
    }
}
```

### Responsive Design

#### Breakpoint Handling
```css
/* Mobile optimization */
@media (max-width: 768px) {
    .chart-controls {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .chart-container {
        height: 300px; /* Reduced height for mobile */
    }
}

/* Desktop optimization */
@media (min-width: 1024px) {
    .chart-container {
        height: 500px;
    }
}
```

## API Reference

### Get Chart Data
```http
GET /api/sentiment-charts/data

Parameters:
- coin_id (required): Cryptocurrency identifier (e.g., 'bitcoin')
- start_date (required): Start date (YYYY-MM-DD)
- end_date (required): End date (YYYY-MM-DD)
- platforms (optional): Array of platforms ['all', 'twitter', 'reddit', 'telegram']
- categories (optional): Array of categories ['all', 'blockchain', 'security', 'contracts', 'defi']
- vs_currency (optional): Base currency ['usd', 'eur', 'btc', 'eth']
- include_price (optional): Include price data (boolean, default: true)
- include_volume (optional): Include volume data (boolean, default: false)

Response:
{
    "metadata": {
        "coin_id": "bitcoin",
        "start_date": "2024-01-01",
        "end_date": "2024-01-31",
        "total_days": 31
    },
    "sentiment_data": [
        {
            "date": "2024-01-01",
            "sentiment": 0.245,
            "magnitude": 0.8,
            "total_posts": 1250,
            "sentiment_distribution": {...}
        }
    ],
    "price_data": [
        {
            "date": "2024-01-01",
            "price_avg": 45000.50,
            "price_change_percent": 2.5,
            "volume": 25000000000
        }
    ],
    "correlation_data": [
        {
            "date": "2024-01-01",
            "sentiment": 0.245,
            "price": 45000.50,
            "price_change": 2.5,
            "posts": 1250
        }
    ],
    "statistics": {
        "correlation_coefficient": 0.342,
        "correlation_strength": "Moderate Positive",
        "data_points": 31,
        "sentiment_stats": {
            "average": 0.156,
            "min": -0.234,
            "max": 0.567,
            "std_dev": 0.145
        },
        "price_stats": {
            "average_change": 1.23,
            "min_change": -8.45,
            "max_change": 12.67,
            "std_dev": 3.45
        }
    }
}
```

### Get Available Coins
```http
GET /api/sentiment-charts/coins

Response:
{
    "popular_coins": [
        {
            "id": "bitcoin",
            "symbol": "btc",
            "name": "Bitcoin"
        },
        {
            "id": "ethereum", 
            "symbol": "eth",
            "name": "Ethereum"
        }
    ],
    "total_supported": 12000
}
```

### Search Coins
```http
GET /api/sentiment-charts/coins/search?query=ethereum

Response:
{
    "results": [
        {
            "id": "ethereum",
            "symbol": "eth", 
            "name": "Ethereum",
            "market_cap_rank": 2
        }
    ],
    "query": "ethereum"
}
```

## Performance Optimization

### Caching Strategy

#### API Response Caching
```php
// Cache price data for 1 hour
Cache::remember("coingecko_price_{$coinId}_{$date}", 3600, function() {
    return $this->fetchPriceData($coinId, $date);
});

// Cache sentiment data for 5 minutes
Cache::remember("sentiment_data_{$date}_{$platform}", 300, function() {
    return $this->getSentimentData($date, $platform);
});
```

#### Frontend Data Caching
```javascript
// Cache chart data in component
const chartDataCache = new Map()

const getCachedData = (cacheKey) => {
    const cached = chartDataCache.get(cacheKey)
    if (cached && Date.now() - cached.timestamp < 300000) { // 5 minutes
        return cached.data
    }
    return null
}
```

### Lazy Loading

#### Dynamic Chart.js Import
```javascript
// Load Chart.js only when needed
const loadChart = async () => {
    if (!chartLoaded.value) {
        const { Chart, registerables } = await import('chart.js')
        Chart.register(...registerables)
        chartLoaded.value = true
        return Chart
    }
}
```

#### Component Code Splitting
```javascript
// Lazy load chart page
const SentimentPriceChart = defineAsyncComponent(() =>
    import('@/Pages/SentimentAnalysis/SentimentPriceChart.vue')
)
```

## Error Handling

### API Error Handling
```javascript
const fetchChartData = async () => {
    try {
        loading.value = true
        const response = await axios.get('/api/sentiment-charts/data', { params })
        chartData.value = response.data
    } catch (error) {
        if (error.response?.status === 422) {
            error.value = 'Invalid parameters: ' + error.response.data.message
        } else if (error.response?.status === 429) {
            error.value = 'Rate limit exceeded. Please try again later.'
        } else {
            error.value = 'Failed to load chart data. Please try again.'
        }
    } finally {
        loading.value = false
    }
}
```

### Chart Rendering Error Handling
```javascript
const renderChart = async () => {
    try {
        if (!chartCanvas.value) {
            throw new Error('Chart canvas not available')
        }
        
        const Chart = await loadChart()
        // ... render chart
        
    } catch (error) {
        console.error('Chart rendering failed:', error)
        error.value = 'Chart rendering failed. Please refresh the page.'
    }
}
```

## Testing

### Unit Tests
```php
// Test correlation calculation
public function test_calculates_pearson_correlation_correctly()
{
    $x = [1, 2, 3, 4, 5];
    $y = [2, 4, 6, 8, 10];
    
    $correlation = $this->service->calculatePearsonCorrelation($x, $y);
    
    $this->assertEquals(1.0, $correlation, '', 0.001);
}
```

### Integration Tests
```php
// Test API endpoint
public function test_sentiment_price_data_endpoint()
{
    $response = $this->getJson('/api/sentiment-charts/data', [
        'coin_id' => 'bitcoin',
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31'
    ]);
    
    $response->assertStatus(200)
            ->assertJsonStructure([
                'metadata',
                'sentiment_data',
                'price_data',
                'correlation_data',
                'statistics'
            ]);
}
```

### Frontend Tests
```javascript
// Test Vue component
import { mount } from '@vue/test-utils'
import SentimentPriceChart from '@/Components/Charts/SentimentPriceChart.vue'

describe('SentimentPriceChart', () => {
    it('renders chart with valid data', async () => {
        const wrapper = mount(SentimentPriceChart, {
            props: { initialCoin: 'bitcoin', initialDays: 30 }
        })
        
        await wrapper.vm.$nextTick()
        
        expect(wrapper.find('.chart-container').exists()).toBe(true)
    })
})
```

## Deployment Considerations

### Environment Setup
```bash
# Production environment variables
COINGECKO_API_KEY=your_production_api_key
COINGECKO_RATE_LIMIT=100
REDIS_URL=redis://your-redis-instance
```

### CDN Integration
```javascript
// Load Chart.js from CDN for better performance
const loadChartFromCDN = () => {
    return new Promise((resolve) => {
        if (window.Chart) {
            resolve(window.Chart)
            return
        }
        
        const script = document.createElement('script')
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js'
        script.onload = () => resolve(window.Chart)
        document.head.appendChild(script)
    })
}
```

### Monitoring
```php
// Add monitoring for API usage
Log::info('Sentiment chart data requested', [
    'coin_id' => $coinId,
    'date_range' => $days,
    'user_id' => auth()->id(),
    'response_time' => microtime(true) - $startTime
]);
```

## Troubleshooting

### Common Issues

#### 1. Chart Not Rendering
- **Cause**: Chart.js not loaded or canvas element missing
- **Solution**: Ensure dynamic import completes before rendering

#### 2. API Rate Limits
- **Cause**: Too many requests to Coingecko API
- **Solution**: Implement proper caching and rate limiting

#### 3. Missing Sentiment Data
- **Cause**: Sentiment pipeline not running or no data for date range
- **Solution**: Check sentiment batch processing and data availability

#### 4. Performance Issues
- **Cause**: Large datasets or inefficient queries
- **Solution**: Implement pagination, caching, and query optimization

### Debug Tools
```javascript
// Enable debug mode for component
const debugMode = ref(process.env.NODE_ENV === 'development')

// Log chart data for debugging
if (debugMode.value) {
    console.log('Chart data:', chartData.value)
    console.log('Statistics:', statistics.value)
}
```

This comprehensive implementation provides a robust, scalable solution for visualizing sentiment vs price correlations with excellent performance and user experience.