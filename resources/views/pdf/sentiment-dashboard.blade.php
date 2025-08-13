<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sentiment Analysis Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 30px;
            background: #ffffff;
            color: #1f2937;
            line-height: 1.6;
        }
        
        .header {
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            margin: 0;
            color: #1e40af;
            font-size: 28px;
            font-weight: 700;
        }
        
        .header .subtitle {
            color: #6b7280;
            font-size: 16px;
            margin-top: 5px;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .metric-value.positive { color: #10b981; }
        .metric-value.negative { color: #ef4444; }
        .metric-value.neutral { color: #6b7280; }
        
        .metric-label {
            color: #6b7280;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #1e40af;
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        
        .sentiment-breakdown {
            background: #f9fafb;
            border-radius: 8px;
            padding: 20px;
        }
        
        .sentiment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .sentiment-item:last-child {
            border-bottom: none;
        }
        
        .sentiment-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            flex: 1;
            margin: 0 15px;
        }
        
        .sentiment-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .sentiment-fill.positive { background: #10b981; }
        .sentiment-fill.negative { background: #ef4444; }
        .sentiment-fill.neutral { background: #6b7280; }
        
        .timeframe-info {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .symbols-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .symbol-tag {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
        
        .chart-placeholder {
            background: #f3f4f6;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-style: italic;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sentiment Analysis Dashboard</h1>
        <div class="subtitle">
            Generated on {{ now()->format('F j, Y \a\t g:i A') }}
            @if(isset($timeframe))
                • Timeframe: {{ strtoupper($timeframe) }}
            @endif
        </div>
    </div>

    @if(isset($timeframe) || isset($symbols))
    <div class="timeframe-info">
        <strong>Analysis Configuration:</strong>
        @if(isset($timeframe))
            <div>Timeframe: {{ strtoupper($timeframe) }}</div>
        @endif
        @if(isset($symbols) && is_array($symbols))
            <div>
                Symbols Analyzed:
                <div class="symbols-list">
                    @foreach($symbols as $symbol)
                        <span class="symbol-tag">{{ $symbol }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
    @endif

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-value positive">{{ $positive_count ?? '0' }}</div>
            <div class="metric-label">Positive Posts</div>
        </div>
        <div class="metric-card">
            <div class="metric-value negative">{{ $negative_count ?? '0' }}</div>
            <div class="metric-label">Negative Posts</div>
        </div>
        <div class="metric-card">
            <div class="metric-value neutral">{{ $neutral_count ?? '0' }}</div>
            <div class="metric-label">Neutral Posts</div>
        </div>
    </div>

    <div class="section">
        <h2>Sentiment Breakdown</h2>
        <div class="sentiment-breakdown">
            @php
                $total = ($positive_count ?? 0) + ($negative_count ?? 0) + ($neutral_count ?? 0);
                $positivePercent = $total > 0 ? round(($positive_count ?? 0) / $total * 100, 1) : 0;
                $negativePercent = $total > 0 ? round(($negative_count ?? 0) / $total * 100, 1) : 0;
                $neutralPercent = $total > 0 ? round(($neutral_count ?? 0) / $total * 100, 1) : 0;
            @endphp
            
            <div class="sentiment-item">
                <span>Positive</span>
                <div class="sentiment-bar">
                    <div class="sentiment-fill positive" style="width: {{ $positivePercent }}%"></div>
                </div>
                <span>{{ $positivePercent }}%</span>
            </div>
            
            <div class="sentiment-item">
                <span>Negative</span>
                <div class="sentiment-bar">
                    <div class="sentiment-fill negative" style="width: {{ $negativePercent }}%"></div>
                </div>
                <span>{{ $negativePercent }}%</span>
            </div>
            
            <div class="sentiment-item">
                <span>Neutral</span>
                <div class="sentiment-bar">
                    <div class="sentiment-fill neutral" style="width: {{ $neutralPercent }}%"></div>
                </div>
                <span>{{ $neutralPercent }}%</span>
            </div>
        </div>
    </div>

    @if(isset($include_charts) && $include_charts)
    <div class="section">
        <h2>Sentiment Timeline Chart</h2>
        <div class="chart-placeholder">
            Chart visualization would appear here in Browserless rendering
        </div>
    </div>
    @endif

    @if(isset($top_keywords) && is_array($top_keywords))
    <div class="section">
        <h2>Top Keywords</h2>
        <div class="sentiment-breakdown">
            @foreach(array_slice($top_keywords, 0, 10) as $keyword => $count)
            <div class="sentiment-item">
                <span>{{ $keyword }}</span>
                <div class="sentiment-bar">
                    <div class="sentiment-fill neutral" style="width: {{ min(100, ($count / max($top_keywords)) * 100) }}%"></div>
                </div>
                <span>{{ $count }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="footer">
        <div>Generated by AI Blockchain Analytics Platform</div>
        <div>Sentiment Analysis Dashboard • {{ now()->format('Y-m-d H:i:s') }}</div>
        @if(isset($generated_at))
            <div>Data as of: {{ $generated_at }}</div>
        @endif
    </div>
</body>
</html>
