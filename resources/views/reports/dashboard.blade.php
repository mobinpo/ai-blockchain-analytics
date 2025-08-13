<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Dashboard Report' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #1f2937;
            background: white;
        }

        .report-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 20mm;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }

        .report-meta {
            text-align: right;
            font-size: 12px;
            color: #6b7280;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .metric-card {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .metric-value {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }

        .metric-label {
            font-size: 12px;
            color: #6b7280;
        }

        .platform-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .platform-card {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .platform-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .platform-icon {
            font-size: 20px;
            margin-right: 10px;
        }

        .platform-name {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }

        .platform-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }

        .stat-label {
            display: block;
            font-size: 11px;
            color: #6b7280;
        }

        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .insight-card {
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .insight-card.positive {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .insight-card.neutral {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
        }

        .insight-card.warning {
            background: #fffbeb;
            border: 1px solid #fed7aa;
        }

        .insight-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .insight-content h4 {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 5px;
        }

        .insight-content p {
            font-size: 12px;
            color: #4b5563;
        }

        .report-footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #6b7280;
        }

        @media print {
            .report-container {
                max-width: none;
                margin: 0;
                padding: 15mm;
            }

            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header -->
        <div class="report-header">
            <div>
                <h1 class="report-title">{{ $title ?? 'Blockchain Analytics Dashboard Report' }}</h1>
                <div class="report-meta">
                    <div>{{ isset($date_range) ? implode(' to ', $date_range) : 'N/A' }}</div>
                    <div>Generated: {{ now()->format('Y-m-d H:i:s') }}</div>
                </div>
            </div>
        </div>

        <!-- Executive Summary -->
        <div class="section">
            <h2 class="section-title">Executive Summary</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($metrics['total_posts'] ?? 0) }}</div>
                    <div class="metric-label">Total Posts Analyzed</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($metrics['sentiment_score'] ?? 0, 3) }}</div>
                    <div class="metric-label">Overall Sentiment</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($metrics['engagement'] ?? 0) }}</div>
                    <div class="metric-label">Total Engagement</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ count($metrics['platforms'] ?? []) }}</div>
                    <div class="metric-label">Platforms Monitored</div>
                </div>
            </div>
        </div>

        <!-- Platform Breakdown -->
        <div class="section">
            <h2 class="section-title">Platform Breakdown</h2>
            <div class="platform-grid">
                @foreach($metrics['platforms'] ?? [] as $platform => $count)
                <div class="platform-card">
                    <div class="platform-header">
                        <div class="platform-icon">
                            @switch($platform)
                                @case('twitter') 🐦 @break
                                @case('reddit') 📋 @break
                                @case('telegram') 📢 @break
                                @default 📱
                            @endswitch
                        </div>
                        <div class="platform-name">{{ ucfirst($platform) }}</div>
                    </div>
                    <div class="platform-stats">
                        <div class="stat">
                            <span class="stat-value">{{ number_format($count) }}</span>
                            <span class="stat-label">Posts</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value">{{ round(($count / ($metrics['total_posts'] ?: 1)) * 100, 1) }}%</span>
                            <span class="stat-label">Share</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Key Insights -->
        <div class="section">
            <h2 class="section-title">Key Insights</h2>
            <div class="insights-grid">
                @if(($metrics['sentiment_score'] ?? 0) > 0.1)
                <div class="insight-card positive">
                    <div class="insight-icon">📈</div>
                    <div class="insight-content">
                        <h4>Positive Sentiment Growth</h4>
                        <p>Overall sentiment is positive ({{ number_format($metrics['sentiment_score'] ?? 0, 3) }}), indicating favorable market perception.</p>
                    </div>
                </div>
                @endif

                <div class="insight-card neutral">
                    <div class="insight-icon">📊</div>
                    <div class="insight-content">
                        <h4>Platform Diversification</h4>
                        <p>Engagement is well-distributed across {{ count($metrics['platforms'] ?? []) }} social media platforms.</p>
                    </div>
                </div>

                @if(($metrics['sentiment_score'] ?? 0) < -0.1)
                <div class="insight-card warning">
                    <div class="insight-icon">⚠️</div>
                    <div class="insight-content">
                        <h4>Market Concerns</h4>
                        <p>Negative sentiment ({{ number_format($metrics['sentiment_score'] ?? 0, 3) }}) suggests market uncertainty that requires attention.</p>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="report-footer">
            <div>
                <div><strong>AI Blockchain Analytics</strong></div>
                <div>Dashboard Report</div>
            </div>
            <div>
                <div>Page 1 of 1</div>
            </div>
        </div>
    </div>
</body>
</html>