<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Sentiment Analysis Report' }}</title>
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
        
        .header .subtitle {
            font-size: 14px;
            opacity: 0.9;
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
            width: 20%;
        }
        
        .metric-value {
            font-size: 16px;
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
        
        .info-box {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 6px;
            padding: 12px;
            margin: 10px 0;
        }
        
        .insights-box {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 15px;
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
            font-size: 11px;
        }
        
        .data-table th {
            background: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }
        
        .data-table tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .platform-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px;
            margin: 8px 0;
        }
        
        .topic-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .topic-item:last-child {
            border-bottom: none;
        }
        
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .trend-stable { color: #6b7280; }
        
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
        <h1>{{ $sentiment_data['title'] ?? 'Sentiment Analysis Dashboard Report' }}</h1>
        <div class="subtitle">
            @if(isset($sentiment_data['period']))
                Analysis Period: {{ $sentiment_data['period']['start'] }} to {{ $sentiment_data['period']['end'] }}
                ({{ $sentiment_data['period']['duration_days'] }} days)
            @else
                Comprehensive Sentiment Analysis Report
            @endif
            <br>Generated on {{ $generated_at->format('F j, Y \a\t g:i A') }}
        </div>
    </div>

    <div class="container">
        <!-- Period Information -->
        @if(isset($sentiment_data['period']))
        <div class="section">
            <div class="info-box">
                <strong>Analysis Period:</strong> {{ $sentiment_data['period']['start'] }} to {{ $sentiment_data['period']['end'] }}
                | <strong>Duration:</strong> {{ $sentiment_data['period']['duration_days'] }} days
                | <strong>Platforms:</strong> {{ is_array($sentiment_data['platforms_included']) ? implode(', ', array_map('ucfirst', $sentiment_data['platforms_included'])) : 'All' }}
            </div>
        </div>
        @endif

        <!-- Overall Metrics -->
        @if(isset($sentiment_data['overall_metrics']))
        <div class="section">
            <h2 class="section-title">Overall Performance Metrics</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value">
                        {{ number_format($sentiment_data['overall_metrics']['total_posts_analyzed'] ?? 0) }}
                    </div>
                    <div class="metric-label">Posts Analyzed</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value {{ ($sentiment_data['overall_metrics']['average_sentiment'] ?? 0) >= 0 ? 'sentiment-positive' : 'sentiment-negative' }}">
                        {{ number_format($sentiment_data['overall_metrics']['average_sentiment'] ?? 0, 3) }}
                    </div>
                    <div class="metric-label">Avg Sentiment</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        {{ number_format(($sentiment_data['overall_metrics']['sentiment_volatility'] ?? 0) * 100, 1) }}%
                    </div>
                    <div class="metric-label">Volatility</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        {{ $sentiment_data['overall_metrics']['engagement_score'] ?? 0 }}/100
                    </div>
                    <div class="metric-label">Engagement</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        {{ $sentiment_data['overall_metrics']['data_quality_score'] ?? 0 }}/100
                    </div>
                    <div class="metric-label">Data Quality</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Sentiment Trends -->
        @if(isset($sentiment_data['sentiment_trends']) && is_array($sentiment_data['sentiment_trends']))
        <div class="section">
            <h2 class="section-title">Recent Sentiment Trends</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Sentiment Score</th>
                        <th>Volume</th>
                        <th>Engagement</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($sentiment_data['sentiment_trends'], -10) as $trend)
                    <tr>
                        <td>{{ $trend['date'] ?? 'N/A' }}</td>
                        <td>
                            <span class="{{ ($trend['sentiment_score'] ?? 0) > 0 ? 'sentiment-positive' : (($trend['sentiment_score'] ?? 0) < 0 ? 'sentiment-negative' : 'sentiment-neutral') }}">
                                {{ number_format($trend['sentiment_score'] ?? 0, 3) }}
                            </span>
                        </td>
                        <td>{{ number_format($trend['volume'] ?? 0) }}</td>
                        <td>{{ number_format($trend['engagement'] ?? 0) }}</td>
                        <td>
                            @if(($trend['sentiment_score'] ?? 0) > 0.1)
                                📈 <span class="trend-up">Bullish</span>
                            @elseif(($trend['sentiment_score'] ?? 0) < -0.1)
                                📉 <span class="trend-down">Bearish</span>
                            @else
                                ➡️ <span class="trend-stable">Neutral</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Platform Breakdown -->
        @if(isset($sentiment_data['platform_breakdown']) && is_array($sentiment_data['platform_breakdown']))
        <div class="section">
            <h2 class="section-title">Platform Analysis</h2>
            @foreach($sentiment_data['platform_breakdown'] as $platform => $data)
            <div class="platform-card">
                <h3 style="color: #2c3e50; margin-bottom: 8px;">{{ ucfirst($platform) }}</h3>
                <div style="display: table; width: 100%;">
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 4px 8px;"><strong>Posts:</strong> {{ number_format($data['posts'] ?? 0) }}</div>
                        <div style="display: table-cell; padding: 4px 8px;"><strong>Sentiment:</strong> 
                            <span class="{{ ($data['avg_sentiment'] ?? 0) >= 0 ? 'sentiment-positive' : 'sentiment-negative' }}">
                                {{ number_format($data['avg_sentiment'] ?? 0, 3) }}
                            </span>
                        </div>
                        <div style="display: table-cell; padding: 4px 8px;"><strong>Engagement:</strong> {{ number_format($data['engagement'] ?? 0) }}</div>
                    </div>
                </div>
                @if(isset($data['top_keywords']) && is_array($data['top_keywords']))
                <div style="margin-top: 8px;">
                    <strong>Top Keywords:</strong> {{ implode(', ', $data['top_keywords']) }}
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <!-- Sentiment Categories -->
        @if(isset($sentiment_data['sentiment_categories']))
        <div class="section">
            <h2 class="section-title">Sentiment Distribution</h2>
            <div class="metrics-grid">
                @foreach($sentiment_data['sentiment_categories'] as $category => $percentage)
                <div class="metric-card">
                    <div class="metric-value">{{ $percentage }}%</div>
                    <div class="metric-label">{{ ucwords(str_replace('_', ' ', $category)) }}</div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Top Topics -->
        @if(isset($sentiment_data['top_topics']) && is_array($sentiment_data['top_topics']))
        <div class="section">
            <h2 class="section-title">Trending Topics</h2>
            @foreach($sentiment_data['top_topics'] as $topic)
            <div class="topic-item">
                <div>
                    <strong>{{ $topic['topic'] }}</strong>
                    <span style="color: #6b7280; font-size: 10px;">({{ number_format($topic['mentions']) }} mentions)</span>
                </div>
                <div>
                    <span class="{{ $topic['sentiment'] >= 0 ? 'sentiment-positive' : 'sentiment-negative' }}">
                        {{ number_format($topic['sentiment'], 2) }}
                    </span>
                    @if($topic['trend'] === 'up')
                        📈 <span class="trend-up">Rising</span>
                    @elseif($topic['trend'] === 'down')
                        📉 <span class="trend-down">Falling</span>
                    @else
                        ➡️ <span class="trend-stable">Stable</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Key Insights -->
        @if(isset($sentiment_data['key_insights']) && is_array($sentiment_data['key_insights']))
        <div class="section">
            <h2 class="section-title">Key Insights & Analysis</h2>
            <div class="insights-box">
                <h3 style="color: #0c4a6e; margin-bottom: 10px;">📊 AI-Generated Insights</h3>
                <ul style="margin-left: 20px;">
                    @foreach($sentiment_data['key_insights'] as $insight)
                    <li style="margin: 8px 0;">{{ $insight }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <!-- Recommendations -->
        @if(isset($sentiment_data['recommendations']) && is_array($sentiment_data['recommendations']))
        <div class="section">
            <h2 class="section-title">Strategic Recommendations</h2>
            <div style="background: #ecfdf5; border: 1px solid #10b981; border-radius: 6px; padding: 15px;">
                <h3 style="color: #064e3b; margin-bottom: 10px;">💡 Actionable Recommendations</h3>
                <ul style="margin-left: 20px;">
                    @foreach($sentiment_data['recommendations'] as $recommendation)
                    <li style="margin: 8px 0;">{{ $recommendation }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <!-- Executive Summary -->
        <div class="section">
            <h2 class="section-title">Executive Summary</h2>
            <div class="insights-box">
                @php
                    $avgSentiment = $sentiment_data['overall_metrics']['average_sentiment'] ?? 0;
                    $totalPosts = $sentiment_data['overall_metrics']['total_posts_analyzed'] ?? 0;
                    $duration = $sentiment_data['period']['duration_days'] ?? 0;
                @endphp
                <p><strong>Analysis Overview:</strong> This report analyzes {{ number_format($totalPosts) }} social media posts 
                over {{ $duration }} days to provide comprehensive sentiment insights for cryptocurrency markets.</p>
                
                <p style="margin-top: 10px;"><strong>Key Findings:</strong></p>
                <ul style="margin-left: 20px; margin-top: 5px;">
                    <li>Overall sentiment is {{ $avgSentiment > 0.1 ? 'positive' : ($avgSentiment < -0.1 ? 'negative' : 'neutral') }} 
                        with an average score of {{ number_format($avgSentiment, 3) }}</li>
                    @if(isset($sentiment_data['platform_breakdown']))
                        <li>{{ ucfirst(array_keys($sentiment_data['platform_breakdown'])[0]) }} shows the highest engagement 
                            with {{ number_format(reset($sentiment_data['platform_breakdown'])['engagement']) }} interactions</li>
                    @endif
                    @if(isset($sentiment_data['top_topics'][0]))
                        <li>{{ $sentiment_data['top_topics'][0]['topic'] }} is the most discussed topic 
                            with {{ number_format($sentiment_data['top_topics'][0]['mentions']) }} mentions</li>
                    @endif
                    <li>Data quality score: {{ $sentiment_data['overall_metrics']['data_quality_score'] ?? 'N/A' }}/100</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>{{ $metadata['type'] ?? 'AI Blockchain Analytics Platform' }} - Sentiment Analysis Report</p>
        @if(isset($sentiment_data['metadata']['analysis_engine']))
            <p>Analysis Engine: {{ $sentiment_data['metadata']['analysis_engine'] }} 
               | Confidence: {{ $sentiment_data['metadata']['confidence_score'] ?? 'N/A' }}%</p>
        @endif
        <p>Report Generated: {{ $generated_at->format('Y-m-d H:i:s T') }} 
           | Version: {{ $metadata['version'] ?? '1.0' }}</p>
    </div>
</body>
</html>