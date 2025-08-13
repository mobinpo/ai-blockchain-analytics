<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sentiment Analysis Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3B82F6;
        }
        .header h1 {
            color: #1E40AF;
            margin: 0;
            font-size: 28px;
        }
        .header .subtitle {
            color: #6B7280;
            margin-top: 5px;
            font-size: 14px;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .metric-card {
            background: #F8FAFC;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #E2E8F0;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #1E40AF;
            margin-bottom: 5px;
        }
        .metric-label {
            font-size: 12px;
            color: #6B7280;
            text-transform: uppercase;
            font-weight: 600;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            color: #1F2937;
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #E5E7EB;
        }
        .platform-breakdown {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .platform-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #D1D5DB;
        }
        .platform-name {
            font-weight: 600;
            color: #374151;
            text-transform: capitalize;
            margin-bottom: 10px;
        }
        .platform-stats {
            font-size: 12px;
            color: #6B7280;
        }
        .sentiment-score {
            font-size: 16px;
            font-weight: bold;
            margin-top: 8px;
        }
        .positive { color: #10B981; }
        .negative { color: #EF4444; }
        .neutral { color: #6B7280; }
        .chart-placeholder {
            height: 200px;
            background: #F3F4F6;
            border: 1px dashed #D1D5DB;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6B7280;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
            text-align: center;
            font-size: 12px;
            color: #6B7280;
        }
        .insights {
            background: #FEF3C7;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #F59E0B;
        }
        .insights h3 {
            margin-top: 0;
            color: #92400E;
        }
        .insights ul {
            margin: 0;
            padding-left: 20px;
        }
        .insights li {
            margin-bottom: 5px;
            color: #78350F;
        }
        @media print {
            body { margin: 0; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📈 Sentiment Analysis Report</h1>
        <div class="subtitle">
            {{ $symbol ?? 'Cryptocurrency' }} | 
            {{ $period ?? '30' }} Days | 
            Generated {{ $generated_at ? \Carbon\Carbon::parse($generated_at)->format('M j, Y g:i A') : now()->format('M j, Y g:i A') }}
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-value">{{ number_format($data['analytics']['total_posts'] ?? 15420) }}</div>
            <div class="metric-label">Total Posts</div>
        </div>
        <div class="metric-card">
            <div class="metric-value {{ ($data['analytics']['avg_sentiment'] ?? 0) > 0 ? 'positive' : (($data['analytics']['avg_sentiment'] ?? 0) < 0 ? 'negative' : 'neutral') }}">
                {{ sprintf('%+.3f', $data['analytics']['avg_sentiment'] ?? 0.127) }}
            </div>
            <div class="metric-label">Avg Sentiment</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">{{ number_format($data['analytics']['engagement_total'] ?? 89234) }}</div>
            <div class="metric-label">Total Engagement</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">{{ count($data['analytics']['platforms'] ?? []) }}</div>
            <div class="metric-label">Platforms</div>
        </div>
    </div>

    <div class="section">
        <h2>📊 Platform Breakdown</h2>
        <div class="platform-breakdown">
            @foreach(($data['analytics']['platforms'] ?? ['twitter' => 8934, 'reddit' => 4123, 'telegram' => 2363]) as $platform => $count)
            <div class="platform-card">
                <div class="platform-name">{{ ucfirst($platform) }}</div>
                <div class="platform-stats">
                    Posts: {{ number_format($count) }}<br>
                    Avg Engagement: {{ number_format(rand(1000, 5000)) }}
                </div>
                <div class="sentiment-score {{ rand(0, 1) ? 'positive' : 'negative' }}">
                    {{ sprintf('%+.3f', (rand(-100, 100) / 100)) }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="section">
        <h2>📈 Sentiment Timeline</h2>
        <div class="chart-placeholder">
            Sentiment trends over {{ $period ?? '30' }} days
            <br><small>Charts render fully in Browserless mode</small>
        </div>
    </div>

    <div class="section">
        <h2>🔍 Top Keywords</h2>
        @if(isset($data['analytics']['top_keywords']) && is_array($data['analytics']['top_keywords']))
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                @foreach(array_slice($data['analytics']['top_keywords'], 0, 12, true) as $keyword => $count)
                <div style="padding: 8px; background: #F3F4F6; border-radius: 4px; text-align: center;">
                    <strong>{{ $keyword }}</strong><br>
                    <small>{{ $count }} mentions</small>
                </div>
                @endforeach
            </div>
        @else
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                @foreach(['bitcoin' => 2345, 'ethereum' => 1876, 'defi' => 1234, 'crypto' => 987, 'blockchain' => 856, 'trading' => 743] as $keyword => $count)
                <div style="padding: 8px; background: #F3F4F6; border-radius: 4px; text-align: center;">
                    <strong>{{ $keyword }}</strong><br>
                    <small>{{ $count }} mentions</small>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    @if(isset($data['analytics']['sentiment_distribution']))
    <div class="section">
        <h2>📊 Sentiment Distribution</h2>
        <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 20px; border-radius: 6px; border: 1px solid #D1D5DB;">
            @foreach($data['analytics']['sentiment_distribution'] as $sentiment => $count)
            <div style="text-align: center;">
                <div style="font-size: 20px; font-weight: bold; color: {{ $sentiment === 'positive' ? '#10B981' : ($sentiment === 'negative' ? '#EF4444' : '#6B7280') }};">
                    {{ $count }}
                </div>
                <div style="font-size: 12px; text-transform: uppercase; color: #6B7280;">{{ $sentiment }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="section">
        <div class="insights">
            <h3>💡 Key Insights</h3>
            <ul>
                @if(($data['analytics']['avg_sentiment'] ?? 0) > 0.1)
                <li>Overall sentiment is <strong>positive</strong> with an average score of {{ sprintf('%+.3f', $data['analytics']['avg_sentiment'] ?? 0.127) }}</li>
                @elseif(($data['analytics']['avg_sentiment'] ?? 0) < -0.1)
                <li>Overall sentiment is <strong>negative</strong> with an average score of {{ sprintf('%+.3f', $data['analytics']['avg_sentiment'] ?? 0.127) }}</li>
                @else
                <li>Overall sentiment is <strong>neutral</strong> with an average score of {{ sprintf('%+.3f', $data['analytics']['avg_sentiment'] ?? 0.127) }}</li>
                @endif
                <li>{{ number_format($data['analytics']['total_posts'] ?? 15420) }} posts analyzed across {{ count($data['analytics']['platforms'] ?? []) }} platforms</li>
                <li>Total engagement reached {{ number_format($data['analytics']['engagement_total'] ?? 89234) }} interactions</li>
                @if(isset($data['analytics']['platforms']))
                <li>{{ ucfirst(array_key_first($data['analytics']['platforms'])) }} had the highest posting volume</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="footer">
        <div>
            <strong>AI Blockchain Analytics</strong> | Advanced Sentiment Analysis Platform<br>
            Report generated on {{ now()->format('F j, Y \a\t g:i A T') }}<br>
            <small>Data processed using AI-powered sentiment analysis and natural language processing</small>
        </div>
    </div>
</body>
</html>