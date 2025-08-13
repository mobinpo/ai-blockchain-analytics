<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $component_name }} Report - {{ $generated_at }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background: #ffffff;
            font-size: 14px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .header .subtitle {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .section {
            margin-bottom: 30px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #e2e8f0;
        }
        
        .section h2 {
            color: #1e293b;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .metric-card {
            background: white;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .metric-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        .data-table th {
            background: #f1f5f9;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 12px;
            border-top: 1px solid #f1f5f9;
            font-size: 13px;
        }
        
        .data-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-completed { background: #dcfce7; color: #166534; }
        .status-processing { background: #fef3c7; color: #92400e; }
        .status-high { background: #fecaca; color: #b91c1c; }
        .status-medium { background: #fed7aa; color: #c2410c; }
        .status-low { background: #dbeafe; color: #1d4ed8; }
        
        .footer {
            margin-top: 40px;
            padding: 20px;
            text-align: center;
            color: #64748b;
            font-size: 12px;
            border-top: 1px solid #e2e8f0;
        }
        
        .demo-badge {
            display: inline-block;
            background: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 12px;
        }
        
        @media print {
            .header {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .section {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ str_replace(['_', '-'], ' ', \Illuminate\Support\Str::title($component_name)) }} Report</h1>
        <div class="subtitle">
            Generated on {{ $generated_at }}
            @if($demo_mode ?? false)
                <span class="demo-badge">Demo Mode</span>
            @endif
        </div>
    </div>

    <div class="container">
        @if(isset($data['metrics']))
            <div class="section">
                <h2>📊 Key Metrics</h2>
                <div class="metrics-grid">
                    @foreach($data['metrics'] as $key => $value)
                        <div class="metric-card">
                            <div class="metric-value">{{ is_numeric($value) ? number_format($value, is_float($value) ? 1 : 0) : $value }}</div>
                            <div class="metric-label">{{ str_replace(['_', '-'], ' ', \Illuminate\Support\Str::title($key)) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(isset($data['recent_analyses']))
            <div class="section">
                <h2>🔍 Recent Analyses</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Contract</th>
                            <th>Status</th>
                            <th>Risk Level</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['recent_analyses'] as $analysis)
                            <tr>
                                <td><code>{{ $analysis['contract'] }}</code></td>
                                <td><span class="status-badge status-{{ $analysis['status'] }}">{{ $analysis['status'] }}</span></td>
                                <td><span class="status-badge status-{{ $analysis['risk_level'] }}">{{ $analysis['risk_level'] }}</span></td>
                                <td>{{ Carbon\Carbon::parse($analysis['timestamp'])->format('M j, Y g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if(isset($data['threat_feed']))
            <div class="section">
                <h2>🚨 Threat Intelligence Feed</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Target</th>
                            <th>Severity</th>
                            <th>Detected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['threat_feed'] as $threat)
                            <tr>
                                <td>{{ str_replace('_', ' ', \Illuminate\Support\Str::title($threat['type'])) }}</td>
                                <td>{{ $threat['target'] }}</td>
                                <td><span class="status-badge status-{{ $threat['severity'] }}">{{ $threat['severity'] }}</span></td>
                                <td>{{ Carbon\Carbon::parse($threat['timestamp'])->format('M j, Y g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if(isset($data['sentiment_data']))
            <div class="section">
                <h2>📈 Sentiment Analysis</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sentiment Score</th>
                            <th>Volume</th>
                            <th>Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['sentiment_data'] as $item)
                            <tr>
                                <td>{{ Carbon\Carbon::parse($item['date'])->format('M j, Y') }}</td>
                                <td>
                                    <span class="status-badge status-{{ $item['sentiment'] > 0.6 ? 'completed' : ($item['sentiment'] > 0.4 ? 'medium' : 'high') }}">
                                        {{ number_format($item['sentiment'], 2) }}
                                    </span>
                                </td>
                                <td>{{ number_format($item['volume']) }}</td>
                                <td>{{ $item['sentiment'] > 0.6 ? '↗️ Positive' : ($item['sentiment'] > 0.4 ? '→ Neutral' : '↘️ Negative') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if(isset($data['platforms']))
            <div class="section">
                <h2>🌐 Platform Coverage</h2>
                <div class="metrics-grid">
                    @foreach($data['platforms'] as $platform)
                        <div class="metric-card">
                            <div class="metric-value">✓</div>
                            <div class="metric-label">{{ \Illuminate\Support\Str::title($platform) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(isset($data['analytics']))
            <div class="section">
                <h2>📊 Analytics Summary</h2>
                <div class="metrics-grid">
                    @foreach($data['analytics'] as $key => $value)
                        <div class="metric-card">
                            <div class="metric-value">{{ is_numeric($value) ? number_format($value, is_float($value) ? 1 : 0) : $value }}{{ str_contains($key, 'rate') ? '%' : '' }}</div>
                            <div class="metric-label">{{ str_replace(['_', '-'], ' ', \Illuminate\Support\Str::title($key)) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(isset($data['charts']))
            <div class="section">
                <h2>📈 Chart Data</h2>
                @foreach($data['charts'] as $chartName => $chartData)
                    <h3>{{ str_replace('_', ' ', \Illuminate\Support\Str::title($chartName)) }}</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                @if(isset($chartData[0]))
                                    @foreach(array_keys($chartData[0]) as $column)
                                        <th>{{ str_replace('_', ' ', \Illuminate\Support\Str::title($column)) }}</th>
                                    @endforeach
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($chartData as $row)
                                <tr>
                                    @foreach($row as $value)
                                        <td>{{ is_numeric($value) ? number_format($value) : $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
        @endif

        <div class="footer">
            <p><strong>Blockchain Analytics Platform</strong></p>
            <p>This report was automatically generated on {{ $generated_at }}</p>
            @if($demo_mode ?? false)
                <p><em>Note: This report contains demonstration data for presentation purposes.</em></p>
            @endif
        </div>
    </div>
</body>
</html>