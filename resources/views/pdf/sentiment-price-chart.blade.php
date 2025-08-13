<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $coin_symbol ?? 'BTC' }} Sentiment vs Price Analysis</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #374151;
            background: white;
        }
        
        .container {
            max-width: 100%;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
        }
        
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .subtitle {
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
        }
        
        .positive { color: #059669; }
        .negative { color: #dc2626; }
        .neutral { color: #6b7280; }
        
        .chart-placeholder {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 60px 20px;
            text-align: center;
            margin-bottom: 30px;
            background: #f9fafb;
        }
        
        .chart-message {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .chart-note {
            font-size: 12px;
            color: #9ca3af;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        
        .correlation-section {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .correlation-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8px;
        }
        
        .correlation-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .correlation-description {
            font-size: 12px;
            color: #6b7280;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
        
        @media print {
            body { font-size: 10px; }
            .container { padding: 10px; }
            .summary-grid { grid-template-columns: repeat(4, 1fr); }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="title">
                {{ strtoupper($coin_symbol ?? 'BTC') }} Sentiment vs Price Analysis
            </h1>
            <p class="subtitle">
                Generated on {{ date('F j, Y \a\t g:i A') }}
                @if(isset($summary['total_data_points']))
                    • {{ $summary['total_data_points'] }} Data Points
                @endif
            </p>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Average Sentiment</div>
                <div class="summary-value {{ $summary['avg_sentiment'] > 0.2 ? 'positive' : ($summary['avg_sentiment'] < -0.2 ? 'negative' : 'neutral') }}">
                    {{ number_format($summary['avg_sentiment'] ?? 0, 3) }}
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Price Change</div>
                <div class="summary-value {{ $summary['price_change'] > 0 ? 'positive' : ($summary['price_change'] < 0 ? 'negative' : 'neutral') }}">
                    {{ ($summary['price_change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($summary['price_change'] ?? 0, 2) }}%
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Volatility</div>
                <div class="summary-value">
                    {{ number_format($summary['volatility'] ?? 0, 2) }}%
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-label">Data Points</div>
                <div class="summary-value">
                    {{ number_format($summary['total_data_points'] ?? 0) }}
                </div>
            </div>
        </div>

        <!-- Correlation Analysis -->
        @if(isset($correlation_coefficient) && $correlation_coefficient !== null)
        <div class="correlation-section">
            <div class="correlation-title">Correlation Analysis</div>
            <div class="correlation-value {{ $correlation_coefficient > 0.3 ? 'positive' : ($correlation_coefficient < -0.3 ? 'negative' : 'neutral') }}">
                {{ $correlation_coefficient >= 0 ? '+' : '' }}{{ number_format($correlation_coefficient, 3) }}
            </div>
            <div class="correlation-description">
                @if(abs($correlation_coefficient) > 0.7)
                    Strong {{ $correlation_coefficient > 0 ? 'positive' : 'negative' }} correlation between sentiment and price.
                @elseif(abs($correlation_coefficient) > 0.3)
                    Moderate {{ $correlation_coefficient > 0 ? 'positive' : 'negative' }} correlation between sentiment and price.
                @else
                    Weak correlation between sentiment and price movements.
                @endif
            </div>
        </div>
        @endif

        <!-- Chart Placeholder (since we can't render JavaScript charts in DomPDF) -->
        <div class="chart-placeholder">
            <div class="chart-message">📊 Interactive Chart</div>
            <div class="chart-note">
                Interactive charts are not available in PDF format.<br>
                For full chart visualization, please use the web version or Browserless PDF generation.
            </div>
        </div>

        <!-- Data Table -->
        @if(isset($chart_data) && isset($chart_data['sentiment_timeline']) && isset($chart_data['price_timeline']))
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Sentiment Score</th>
                    <th>Sentiment Category</th>
                    <th>Price (USD)</th>
                    <th>Price Change %</th>
                    <th>Volume</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chart_data['sentiment_timeline'] as $index => $sentiment)
                    @php
                        $price = $chart_data['price_timeline'][$index] ?? null;
                        $prevPrice = $index > 0 ? ($chart_data['price_timeline'][$index - 1]['price'] ?? 0) : ($price['price'] ?? 0);
                        $priceChange = $prevPrice > 0 ? (($price['price'] ?? 0) - $prevPrice) / $prevPrice * 100 : 0;
                        
                        $sentimentCategory = 'Neutral';
                        if ($sentiment['sentiment'] > 0.6) $sentimentCategory = 'Very Positive';
                        elseif ($sentiment['sentiment'] > 0.2) $sentimentCategory = 'Positive';
                        elseif ($sentiment['sentiment'] < -0.6) $sentimentCategory = 'Very Negative';
                        elseif ($sentiment['sentiment'] < -0.2) $sentimentCategory = 'Negative';
                        
                        $sentimentClass = $sentiment['sentiment'] > 0.2 ? 'positive' : ($sentiment['sentiment'] < -0.2 ? 'negative' : 'neutral');
                        $priceChangeClass = $priceChange > 0 ? 'positive' : ($priceChange < 0 ? 'negative' : 'neutral');
                    @endphp
                    <tr>
                        <td>{{ date('M j, Y', strtotime($sentiment['date'])) }}</td>
                        <td class="{{ $sentimentClass }}">{{ number_format($sentiment['sentiment'], 3) }}</td>
                        <td class="{{ $sentimentClass }}">{{ $sentimentCategory }}</td>
                        <td>${{ number_format($price['price'] ?? 0, 2) }}</td>
                        <td class="{{ $priceChangeClass }}">
                            {{ $priceChange >= 0 ? '+' : '' }}{{ number_format($priceChange, 2) }}%
                        </td>
                        <td>
                            @if(isset($price['volume']))
                                @if($price['volume'] >= 1000000000)
                                    {{ number_format($price['volume'] / 1000000000, 2) }}B
                                @elseif($price['volume'] >= 1000000)
                                    {{ number_format($price['volume'] / 1000000, 2) }}M
                                @elseif($price['volume'] >= 1000)
                                    {{ number_format($price['volume'] / 1000, 2) }}K
                                @else
                                    {{ number_format($price['volume']) }}
                                @endif
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>Generated by AI Blockchain Analytics Platform • Sentiment data from Google Cloud NLP • Price data from CoinGecko API</p>
            <p>This report was generated using DomPDF fallback mode. For interactive charts, use Browserless PDF generation.</p>
        </div>
    </div>
</body>
</html>