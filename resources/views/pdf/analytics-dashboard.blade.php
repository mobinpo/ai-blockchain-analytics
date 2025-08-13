<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
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
            border-bottom: 3px solid #10b981;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            margin: 0;
            color: #065f46;
            font-size: 28px;
            font-weight: 700;
        }
        
        .header .subtitle {
            color: #6b7280;
            font-size: 16px;
            margin-top: 5px;
        }
        
        .contract-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .contract-address {
            font-family: 'Courier New', monospace;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            margin-top: 10px;
            word-break: break-all;
        }
        
        .analysis-type {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
        }
        
        .metric-card h3 {
            margin: 0 0 15px 0;
            color: #065f46;
            font-size: 16px;
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .metric-value.high { color: #dc2626; }
        .metric-value.medium { color: #d97706; }
        .metric-value.low { color: #10b981; }
        .metric-value.info { color: #3b82f6; }
        
        .metric-description {
            color: #6b7280;
            font-size: 14px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #065f46;
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        
        .findings-list {
            background: #f9fafb;
            border-radius: 8px;
            padding: 20px;
        }
        
        .finding-item {
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .finding-item:last-child {
            border-bottom: none;
        }
        
        .finding-severity {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .finding-severity.critical {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .finding-severity.high {
            background: #fef3c7;
            color: #d97706;
        }
        
        .finding-severity.medium {
            background: #fef3c7;
            color: #d97706;
        }
        
        .finding-severity.low {
            background: #d1fae5;
            color: #10b981;
        }
        
        .finding-title {
            font-weight: 600;
            margin: 5px 0;
        }
        
        .finding-description {
            color: #6b7280;
            font-size: 14px;
        }
        
        .chart-placeholder {
            background: #f3f4f6;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-style: italic;
            margin: 20px 0;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Smart Contract Analytics Dashboard</h1>
        <div class="subtitle">
            Comprehensive Analysis Report • Generated on {{ now()->format('F j, Y \a\t g:i A') }}
        </div>
    </div>

    <div class="contract-info">
        <strong>Contract Analysis:</strong>
        @if(isset($contract_address))
            <div class="contract-address">{{ $contract_address }}</div>
        @endif
        @if(isset($analysis_type))
            <div class="analysis-type">{{ $analysis_type }} Analysis</div>
        @endif
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <h3>Security Score</h3>
            <div class="metric-value {{ isset($security_score) && $security_score > 80 ? 'low' : (isset($security_score) && $security_score > 60 ? 'medium' : 'high') }}">
                {{ $security_score ?? 'N/A' }}{{ isset($security_score) ? '/100' : '' }}
            </div>
            <div class="metric-description">
                Overall security assessment based on vulnerability analysis
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Gas Efficiency</h3>
            <div class="metric-value info">
                {{ $gas_efficiency ?? 'N/A' }}{{ isset($gas_efficiency) ? '%' : '' }}
            </div>
            <div class="metric-description">
                Optimization level compared to similar contracts
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Code Quality</h3>
            <div class="metric-value {{ isset($code_quality) && $code_quality > 80 ? 'low' : (isset($code_quality) && $code_quality > 60 ? 'medium' : 'high') }}">
                {{ $code_quality ?? 'N/A' }}{{ isset($code_quality) ? '/100' : '' }}
            </div>
            <div class="metric-description">
                Code structure, documentation, and best practices
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Total Findings</h3>
            <div class="metric-value info">
                {{ isset($findings) ? count($findings) : '0' }}
            </div>
            <div class="metric-description">
                Security issues and optimization opportunities identified
            </div>
        </div>
    </div>

    @if(isset($findings) && is_array($findings) && count($findings) > 0)
    <div class="section">
        <h2>Security Findings</h2>
        <div class="findings-list">
            @foreach(array_slice($findings, 0, 10) as $finding)
            <div class="finding-item">
                <div class="finding-severity {{ strtolower($finding['severity'] ?? 'medium') }}">
                    {{ $finding['severity'] ?? 'Medium' }}
                </div>
                <div class="finding-title">
                    {{ $finding['title'] ?? 'Security Finding' }}
                </div>
                <div class="finding-description">
                    {{ $finding['description'] ?? 'No description available' }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if(isset($include_charts) && $include_charts)
    <div class="section">
        <h2>Security Analysis Visualization</h2>
        <div class="chart-placeholder">
            Interactive security analysis charts would appear here in Browserless rendering
        </div>
    </div>
    
    <div class="section">
        <h2>Gas Usage Analysis</h2>
        <div class="chart-placeholder">
            Gas usage optimization charts would appear here in Browserless rendering
        </div>
    </div>
    @endif

    @if(isset($recommendations) && is_array($recommendations))
    <div class="section">
        <h2>Recommendations</h2>
        <div class="findings-list">
            @foreach($recommendations as $recommendation)
            <div class="finding-item">
                <div class="finding-title">
                    {{ $recommendation['title'] ?? 'Recommendation' }}
                </div>
                <div class="finding-description">
                    {{ $recommendation['description'] ?? 'No description available' }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="footer">
        <div>Generated by AI Blockchain Analytics Platform</div>
        <div>Smart Contract Analytics Dashboard • {{ now()->format('Y-m-d H:i:s') }}</div>
        @if(isset($generated_at))
            <div>Analysis completed: {{ $generated_at }}</div>
        @endif
    </div>
</body>
</html>
