<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sentiment Analysis Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #fff;
        }
        
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 25px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .header h1 {
            font-size: 22px;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .container {
            padding: 0 25px;
        }
        
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #10b981;
            padding-bottom: 5px;
            margin-bottom: 12px;
        }
        
        .metrics-grid {
            display: table;
            width: 100%;
            border-spacing: 8px;
        }
        
        .metric-card {
            display: table-cell;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            width: 25%;
        }
        
        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        
        .metric-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        
        .sentiment-positive { color: #10b981; }
        .sentiment-negative { color: #ef4444; }
        .sentiment-neutral { color: #6b7280; }
        
        .chart-placeholder {
            background: #f8f9fa;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            color: #6b7280;
            margin: 15px 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .data-table th,
        .data-table td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: left;
        }
        
        .data-table th {
            background: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }
        
        .data-table tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .timeframe-info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 6px;
            padding: 12px;
            margin: 10px 0;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Sentiment Analysis Dashboard</h1>
        <div>Real-time Cryptocurrency Sentiment Tracking</div>
    </div>

    <div class="container">
        <!-- Timeframe Information -->
        <div class="section">
            <div class="timeframe-info">
                <strong>Analysis Period:</strong> {{ $timeframe }} 
                | <strong>Generated:</strong> {{ $generated_at->format('F j, Y \a\t g:i A') }}
                | <strong>Data Points:</strong> {{ is_array($sentiment_data) ? count($sentiment_data) : 0 }}
            </div>
        </div>

        <!-- Summary Metrics -->
        <div class="section">
            <h2 class="section-title">Sentiment Overview</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value sentiment-positive">
                        @php
                            $avgSentiment = 0;
                            if (is_array($sentiment_data) && count($sentiment_data) > 0) {
                                $sentiments = array_column($sentiment_data, 'sentiment', 0);
                                $avgSentiment = array_sum($sentiments) / count($sentiments);
                            }
                        @endphp
                        {{ number_format($avgSentiment, 3) }}
                    </div>
                    <div class="metric-label">Average Sentiment</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        {{ is_array($sentiment_data) ? count(array_filter($sentiment_data, fn($d) => $d['sentiment'] > 0)) : 0 }}
                    </div>
                    <div class="metric-label">Positive Periods</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        {{ is_array($sentiment_data) ? count(array_filter($sentiment_data, fn($d) => $d['sentiment'] < 0)) : 0 }}
                    </div>
                    <div class="metric-label">Negative Periods</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        @php
                            $totalVolume = 0;
                            if (is_array($sentiment_data)) {
                                $totalVolume = array_sum(array_column($sentiment_data, 'volume'));
                            }
                        @endphp
                        {{ number_format($totalVolume) }}
                    </div>
                    <div class="metric-label">Total Volume</div>
                </div>
            </div>
        </div>

        <!-- Sentiment Timeline Chart Placeholder -->
        <div class="section">
            <h2 class="section-title">Sentiment vs Price Timeline</h2>
            <div class="chart-placeholder">
                <h3>📊 Sentiment & Price Correlation Chart</h3>
                <p>This chart would display the correlation between sentiment scores and price movements over time.</p>
                <p><em>Chart rendering requires JavaScript execution (available in Vue component version)</em></p>
            </div>
        </div>

        <!-- Recent Sentiment Data -->
        @if(is_array($sentiment_data) && count($sentiment_data) > 0)
        <div class="section">
            <h2 class="section-title">Recent Sentiment Data</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Sentiment Score</th>
                        <th>Volume</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($sentiment_data, -10) as $point)
                    <tr>
                        <td>{{ $point['timestamp'] ?? 'N/A' }}</td>
                        <td>
                            <span class="{{ $point['sentiment'] > 0 ? 'sentiment-positive' : ($point['sentiment'] < 0 ? 'sentiment-negative' : 'sentiment-neutral') }}">
                                {{ number_format($point['sentiment'], 3) }}
                            </span>
                        </td>
                        <td>{{ number_format($point['volume'] ?? 0) }}</td>
                        <td>
                            @if($point['sentiment'] > 0.1)
                                📈 Bullish
                            @elseif($point['sentiment'] < -0.1)
                                📉 Bearish
                            @else
                                ➡️ Neutral
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Price Data -->
        @if(isset($price_data) && is_array($price_data) && count($price_data) > 0)
        <div class="section">
            <h2 class="section-title">Price Movement Summary</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value">
                        ${{ number_format($price_data[0]['price'] ?? 0, 2) }}
                    </div>
                    <div class="metric-label">Starting Price</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        ${{ number_format(end($price_data)['price'] ?? 0, 2) }}
                    </div>
                    <div class="metric-label">Current Price</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        @php
                            $priceChange = 0;
                            if (count($price_data) >= 2) {
                                $start = $price_data[0]['price'] ?? 0;
                                $end = end($price_data)['price'] ?? 0;
                                $priceChange = $start > 0 ? (($end - $start) / $start) * 100 : 0;
                            }
                        @endphp
                        <span class="{{ $priceChange >= 0 ? 'sentiment-positive' : 'sentiment-negative' }}">
                            {{ $priceChange >= 0 ? '+' : '' }}{{ number_format($priceChange, 2) }}%
                        </span>
                    </div>
                    <div class="metric-label">Price Change</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        @php
                            $avgVolume = count($price_data) > 0 ? array_sum(array_column($price_data, 'volume')) / count($price_data) : 0;
                        @endphp
                        {{ number_format($avgVolume) }}
                    </div>
                    <div class="metric-label">Avg Volume</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Analysis Insights -->
        <div class="section">
            <h2 class="section-title">Key Insights</h2>
            <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 6px; padding: 15px;">
                <h3 style="color: #0c4a6e; margin-bottom: 10px;">📊 Sentiment Analysis Summary</h3>
                
                @php
                    $insights = [];
                    if ($avgSentiment > 0.1) {
                        $insights[] = "Overall sentiment is positive with an average score of " . number_format($avgSentiment, 3);
                    } elseif ($avgSentiment < -0.1) {
                        $insights[] = "Overall sentiment is negative with an average score of " . number_format($avgSentiment, 3);
                    } else {
                        $insights[] = "Sentiment remains neutral with minor fluctuations";
                    }
                    
                    if (isset($priceChange)) {
                        if ($avgSentiment > 0 && $priceChange > 0) {
                            $insights[] = "Positive sentiment correlates with price increase of " . number_format($priceChange, 2) . "%";
                        } elseif ($avgSentiment < 0 && $priceChange < 0) {
                            $insights[] = "Negative sentiment aligns with price decrease of " . number_format(abs($priceChange), 2) . "%";
                        } else {
                            $insights[] = "Sentiment and price movements show mixed correlation";
                        }
                    }
                    
                    $insights[] = "Analysis based on " . (is_array($sentiment_data) ? count($sentiment_data) : 0) . " data points over " . $timeframe;
                @endphp
                
                <ul style="margin-left: 20px;">
                    @foreach($insights as $insight)
                    <li style="margin: 8px 0;">{{ $insight }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>AI Blockchain Analytics Platform - Sentiment Analysis Report</p>
        <p>Data sources: Social media platforms, news feeds, and market indicators</p>
        <p>Report generated: {{ $generated_at->format('Y-m-d H:i:s T') }}</p>
    </div>
</body>
</html>
