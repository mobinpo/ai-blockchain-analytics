<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Sentiment vs Price Timeline' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }

        .pdf-container {
            max-width: 100%;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 24px;
            color: #1e40af;
            margin-bottom: 10px;
        }

        .header .subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .metadata {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }

        .metadata-item {
            text-align: center;
        }

        .metadata-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: bold;
        }

        .metadata-value {
            font-size: 16px;
            color: #1f2937;
            font-weight: bold;
            margin-top: 5px;
        }

        .chart-placeholder {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            border: 2px dashed #3b82f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            position: relative;
        }

        .chart-placeholder::before {
            content: "📈 Interactive Chart";
            font-size: 18px;
            color: #3b82f6;
            font-weight: bold;
        }

        .chart-placeholder::after {
            content: "Best viewed with Browserless rendering";
            position: absolute;
            bottom: 20px;
            font-size: 12px;
            color: #6b7280;
            font-style: italic;
        }

        .statistics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-value.positive {
            color: #059669;
        }

        .stat-value.negative {
            color: #dc2626;
        }

        .stat-value.neutral {
            color: #6b7280;
        }

        .stat-description {
            font-size: 11px;
            color: #9ca3af;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
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

        .correlation-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            vertical-align: middle;
        }

        .correlation-strong-positive {
            background: #059669;
        }

        .correlation-moderate-positive {
            background: #10b981;
        }

        .correlation-weak-positive {
            background: #34d399;
        }

        .correlation-neutral {
            background: #6b7280;
        }

        .correlation-weak-negative {
            background: #f87171;
        }

        .correlation-moderate-negative {
            background: #ef4444;
        }

        .correlation-strong-negative {
            background: #dc2626;
        }

        .insights {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            margin: 20px 0;
        }

        .insights h3 {
            color: #92400e;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .insights ul {
            list-style-type: none;
            padding-left: 0;
        }

        .insights li {
            margin-bottom: 8px;
            color: #78350f;
            font-size: 14px;
        }

        .insights li::before {
            content: "💡 ";
            margin-right: 5px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }

        .disclaimer {
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            font-size: 12px;
            color: #991b1b;
        }

        @media print {
            .pdf-container {
                padding: 10px;
            }
            
            .chart-placeholder {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="pdf-container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $title ?? 'Sentiment vs Price Timeline Analysis' }}</h1>
            <div class="subtitle">
                {{ strtoupper($coin ?? 'Cryptocurrency') }} • {{ $days ?? 30 }} Day Analysis
            </div>
            <div class="subtitle">
                Generated on {{ now()->format('F j, Y \a\t g:i A') }}
            </div>
        </div>

        <!-- Metadata -->
        <div class="metadata">
            <div class="metadata-item">
                <div class="metadata-label">Cryptocurrency</div>
                <div class="metadata-value">{{ strtoupper($coin ?? 'N/A') }}</div>
            </div>
            <div class="metadata-item">
                <div class="metadata-label">Time Period</div>
                <div class="metadata-value">{{ $days ?? 30 }} Days</div>
            </div>
            <div class="metadata-item">
                <div class="metadata-label">Data Source</div>
                <div class="metadata-value">{{ $is_demo ? 'Demo Data' : 'Live Data' }}</div>
            </div>
            <div class="metadata-item">
                <div class="metadata-label">Generated</div>
                <div class="metadata-value">{{ now()->format('M j, Y') }}</div>
            </div>
        </div>

        <!-- Chart Placeholder -->
        <div class="chart-placeholder">
            <!-- This would be replaced with actual chart in Browserless rendering -->
        </div>

        <!-- Statistics -->
        @if(isset($stats) || isset($correlation) || isset($avg_sentiment) || isset($price_change))
        <div class="statistics">
            <div class="stat-card">
                <div class="stat-label">Correlation</div>
                <div class="stat-value {{ ($correlation ?? 0) > 0 ? 'positive' : (($correlation ?? 0) < 0 ? 'negative' : 'neutral') }}">
                    @php
                        $corr = $correlation ?? 0;
                        $corrClass = '';
                        if (abs($corr) >= 0.8) $corrClass = abs($corr) == $corr ? 'correlation-strong-positive' : 'correlation-strong-negative';
                        elseif (abs($corr) >= 0.6) $corrClass = abs($corr) == $corr ? 'correlation-moderate-positive' : 'correlation-moderate-negative';
                        elseif (abs($corr) >= 0.2) $corrClass = abs($corr) == $corr ? 'correlation-weak-positive' : 'correlation-weak-negative';
                        else $corrClass = 'correlation-neutral';
                    @endphp
                    <span class="correlation-indicator {{ $corrClass }}"></span>
                    {{ number_format($correlation ?? 0, 3) }}
                </div>
                <div class="stat-description">
                    @if(abs($correlation ?? 0) >= 0.8)
                        Very Strong
                    @elseif(abs($correlation ?? 0) >= 0.6)
                        Strong
                    @elseif(abs($correlation ?? 0) >= 0.4)
                        Moderate
                    @elseif(abs($correlation ?? 0) >= 0.2)
                        Weak
                    @else
                        Very Weak
                    @endif
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Avg Sentiment</div>
                <div class="stat-value {{ ($avg_sentiment ?? 0) >= 0 ? 'positive' : 'negative' }}">
                    {{ number_format($avg_sentiment ?? 0, 3) }}
                </div>
                <div class="stat-description">
                    @if(($avg_sentiment ?? 0) >= 0.5)
                        Very Positive
                    @elseif(($avg_sentiment ?? 0) >= 0.1)
                        Positive
                    @elseif(($avg_sentiment ?? 0) >= -0.1)
                        Neutral
                    @elseif(($avg_sentiment ?? 0) >= -0.5)
                        Negative
                    @else
                        Very Negative
                    @endif
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Price Change</div>
                <div class="stat-value {{ ($price_change ?? 0) >= 0 ? 'positive' : 'negative' }}">
                    {{ ($price_change ?? 0) >= 0 ? '+' : '' }}{{ number_format($price_change ?? 0, 2) }}%
                </div>
                <div class="stat-description">{{ $days ?? 30 }} day period</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Data Points</div>
                <div class="stat-value neutral">
                    {{ $total_data_points ?? 'N/A' }}
                </div>
                <div class="stat-description">Sentiment & Price pairs</div>
            </div>
        </div>
        @endif

        <!-- Insights -->
        @if(isset($correlation))
        <div class="insights">
            <h3>📊 Analysis Insights</h3>
            <ul>
                @if(abs($correlation) >= 0.6)
                    <li>Strong correlation ({{ number_format($correlation, 3) }}) indicates sentiment {{ $correlation > 0 ? 'positively' : 'negatively' }} influences price movements</li>
                @elseif(abs($correlation) >= 0.3)
                    <li>Moderate correlation ({{ number_format($correlation, 3) }}) suggests some relationship between sentiment and price</li>
                @else
                    <li>Weak correlation ({{ number_format($correlation, 3) }}) indicates limited direct relationship between sentiment and price</li>
                @endif
                
                @if(isset($avg_sentiment))
                    @if($avg_sentiment > 0.3)
                        <li>Overall positive sentiment ({{ number_format($avg_sentiment, 3) }}) suggests bullish market perception</li>
                    @elseif($avg_sentiment < -0.3)
                        <li>Overall negative sentiment ({{ number_format($avg_sentiment, 3) }}) suggests bearish market perception</li>
                    @else
                        <li>Neutral sentiment ({{ number_format($avg_sentiment, 3) }}) indicates balanced market perception</li>
                    @endif
                @endif
                
                @if(isset($price_change))
                    @if(abs($price_change) > 20)
                        <li>High price volatility ({{ number_format(abs($price_change), 1) }}%) indicates significant market movement</li>
                    @elseif(abs($price_change) > 10)
                        <li>Moderate price movement ({{ number_format(abs($price_change), 1) }}%) shows active trading</li>
                    @else
                        <li>Stable price movement ({{ number_format(abs($price_change), 1) }}%) indicates consolidation period</li>
                    @endif
                @endif
            </ul>
        </div>
        @endif

        <!-- Sample Data Table (if available) -->
        @if(isset($sentiment_data) && count($sentiment_data) > 0)
        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #374151;">Recent Data Points</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Sentiment Score</th>
                    <th>Price (USD)</th>
                    @if(isset($include_volume) && $include_volume)
                        <th>Volume</th>
                    @endif
                    <th>Sentiment Label</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($sentiment_data, -10) as $index => $point)
                @php
                    $pricePoint = $price_data[$index] ?? null;
                @endphp
                <tr>
                    <td>{{ $point['date'] ?? 'N/A' }}</td>
                    <td>{{ number_format($point['sentiment'] ?? 0, 3) }}</td>
                    <td>${{ number_format($pricePoint['price'] ?? 0, 2) }}</td>
                    @if(isset($include_volume) && $include_volume)
                        <td>{{ isset($pricePoint['volume']) ? number_format($pricePoint['volume']) : 'N/A' }}</td>
                    @endif
                    <td>
                        @php
                            $sentiment = $point['sentiment'] ?? 0;
                            if ($sentiment >= 0.25) echo 'Positive';
                            elseif ($sentiment <= -0.25) echo 'Negative';
                            elseif (abs($sentiment) > 0.1) echo 'Mixed';
                            else echo 'Neutral';
                        @endphp
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Disclaimer -->
        @if(isset($is_demo) && $is_demo)
        <div class="disclaimer">
            <strong>⚠️ Demo Data Notice:</strong> This report contains simulated data for demonstration purposes. 
            Real market analysis should use live data from verified sources.
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div>AI Blockchain Analytics • Sentiment vs Price Timeline Report</div>
            <div>Generated on {{ now()->format('F j, Y \a\t g:i A T') }}</div>
            @if(isset($method))
                <div>Rendering Method: {{ ucfirst($method) }}</div>
            @endif
        </div>
    </div>
</body>
</html>