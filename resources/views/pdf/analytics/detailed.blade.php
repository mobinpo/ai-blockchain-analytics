<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Contract Analysis Report - {{ $contract['name'] ?? 'Unknown Contract' }}</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .header .subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .container {
            padding: 0 30px;
            max-width: 100%;
        }
        
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        
        .contract-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            border-spacing: 0;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: bold;
            color: #555;
            padding: 8px 15px 8px 0;
            width: 30%;
        }
        
        .info-value {
            display: table-cell;
            padding: 8px 0;
            word-break: break-all;
        }
        
        .metrics-grid {
            display: table;
            width: 100%;
            border-spacing: 10px;
        }
        
        .metric-card {
            display: table-cell;
            background: #fff;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            width: 25%;
        }
        
        .metric-value {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .metric-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .score-high { color: #27ae60; }
        .score-medium { color: #f39c12; }
        .score-low { color: #e74c3c; }
        
        .issues-list {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
        }
        
        .issue-item {
            margin: 8px 0;
            padding: 8px;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #f39c12;
        }
        
        .recommendations {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
        }
        
        .recommendation-item {
            margin: 8px 0;
            padding: 8px;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #17a2b8;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .analysis-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin: 15px 0;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        
        .code-block {
            background: #f8f8f8;
            border: 1px solid #e1e1e1;
            border-radius: 4px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 10px;
            overflow-wrap: break-word;
            margin: 10px 0;
        }
        
        @media print {
            .page-break {
                page-break-before: always;
            }
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Smart Contract Security Analysis</h1>
        <div class="subtitle">
            Comprehensive Security Assessment Report
            <br>Generated on {{ $generated_at->format('F j, Y \a\t g:i A') }}
        </div>
    </div>

    <div class="container">
        <!-- Contract Information -->
        <div class="section">
            <h2 class="section-title">Contract Information</h2>
            <div class="contract-info">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Contract Name:</div>
                        <div class="info-value">{{ $contract['name'] ?? 'Unknown Contract' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Contract Address:</div>
                        <div class="info-value">{{ $contract['address'] ?? 'N/A' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Network:</div>
                        <div class="info-value">{{ ucfirst($contract['network'] ?? 'ethereum') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Analysis Date:</div>
                        <div class="info-value">{{ $generated_at->format('F j, Y') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Report Type:</div>
                        <div class="info-value">{{ ucfirst($format ?? 'detailed') }} Analysis</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Metrics -->
        <div class="section">
            <h2 class="section-title">Security Metrics</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value {{ $analysis['vulnerability_score'] >= 80 ? 'score-high' : ($analysis['vulnerability_score'] >= 60 ? 'score-medium' : 'score-low') }}">
                        {{ $analysis['vulnerability_score'] ?? 0 }}/100
                    </div>
                    <div class="metric-label">Vulnerability Score</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value {{ $analysis['gas_efficiency'] >= 80 ? 'score-high' : ($analysis['gas_efficiency'] >= 60 ? 'score-medium' : 'score-low') }}">
                        {{ $analysis['gas_efficiency'] ?? 0 }}/100
                    </div>
                    <div class="metric-label">Gas Efficiency</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value {{ $analysis['code_quality'] >= 80 ? 'score-high' : ($analysis['code_quality'] >= 60 ? 'score-medium' : 'score-low') }}">
                        {{ $analysis['code_quality'] ?? 0 }}/100
                    </div>
                    <div class="metric-label">Code Quality</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">
                        @php
                            $overallScore = (($analysis['vulnerability_score'] ?? 0) + ($analysis['gas_efficiency'] ?? 0) + ($analysis['code_quality'] ?? 0)) / 3;
                        @endphp
                        <span class="{{ $overallScore >= 80 ? 'score-high' : ($overallScore >= 60 ? 'score-medium' : 'score-low') }}">
                            {{ round($overallScore) }}/100
                        </span>
                    </div>
                    <div class="metric-label">Overall Score</div>
                </div>
            </div>
        </div>

        <!-- Security Issues -->
        @if(isset($analysis['security_issues']) && count($analysis['security_issues']) > 0)
        <div class="section">
            <h2 class="section-title">Security Issues Identified</h2>
            <div class="issues-list">
                @foreach($analysis['security_issues'] as $issue)
                <div class="issue-item">
                    <span class="badge badge-warning">Issue</span>
                    {{ $issue }}
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Recommendations -->
        @if(isset($analysis['recommendations']) && count($analysis['recommendations']) > 0)
        <div class="section">
            <h2 class="section-title">Recommendations</h2>
            <div class="recommendations">
                @foreach($analysis['recommendations'] as $recommendation)
                <div class="recommendation-item">
                    <span class="badge badge-success">Recommendation</span>
                    {{ $recommendation }}
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Detailed Analysis -->
        @if(isset($analysis['detailed_analysis']))
        <div class="section page-break">
            <h2 class="section-title">Detailed Analysis</h2>
            <div class="analysis-section">
                @if(is_array($analysis['detailed_analysis']))
                    @foreach($analysis['detailed_analysis'] as $section => $content)
                    <h3 style="color: #2c3e50; margin: 15px 0 10px 0;">{{ ucfirst(str_replace('_', ' ', $section)) }}</h3>
                    @if(is_array($content))
                        <ul style="margin-left: 20px;">
                        @foreach($content as $item)
                            <li style="margin: 5px 0;">{{ $item }}</li>
                        @endforeach
                        </ul>
                    @else
                        <p style="margin: 10px 0;">{{ $content }}</p>
                    @endif
                    @endforeach
                @else
                    <p>{{ $analysis['detailed_analysis'] }}</p>
                @endif
            </div>
        </div>
        @endif

        <!-- AI Insights -->
        @if(isset($analysis['ai_insights']))
        <div class="section">
            <h2 class="section-title">AI-Powered Insights</h2>
            <div class="analysis-section">
                @if(is_array($analysis['ai_insights']))
                    @foreach($analysis['ai_insights'] as $insight)
                    <div style="margin: 15px 0; padding: 10px; background: white; border-radius: 6px; border-left: 3px solid #9b59b6;">
                        {{ $insight }}
                    </div>
                    @endforeach
                @else
                    <p>{{ $analysis['ai_insights'] }}</p>
                @endif
            </div>
        </div>
        @endif

        <!-- Summary -->
        <div class="section">
            <h2 class="section-title">Executive Summary</h2>
            <div class="analysis-section">
                <p><strong>Contract Analysis Completion:</strong> The smart contract has been thoroughly analyzed using advanced AI-powered security scanning tools.</p>
                
                <p><strong>Overall Assessment:</strong> 
                @php $overallScore = (($analysis['vulnerability_score'] ?? 0) + ($analysis['gas_efficiency'] ?? 0) + ($analysis['code_quality'] ?? 0)) / 3; @endphp
                @if($overallScore >= 80)
                    The contract demonstrates excellent security practices with minimal risks identified.
                @elseif($overallScore >= 60)
                    The contract shows good security practices but has areas for improvement.
                @else
                    The contract requires immediate attention to address significant security concerns.
                @endif
                </p>

                <p><strong>Key Findings:</strong></p>
                <ul style="margin-left: 20px;">
                    <li>Vulnerability Score: {{ $analysis['vulnerability_score'] ?? 0 }}/100 - {{ $analysis['vulnerability_score'] >= 80 ? 'Excellent' : ($analysis['vulnerability_score'] >= 60 ? 'Good' : 'Needs Improvement') }}</li>
                    <li>Gas Efficiency: {{ $analysis['gas_efficiency'] ?? 0 }}/100 - {{ $analysis['gas_efficiency'] >= 80 ? 'Highly Optimized' : ($analysis['gas_efficiency'] >= 60 ? 'Well Optimized' : 'Optimization Required') }}</li>
                    <li>Code Quality: {{ $analysis['code_quality'] ?? 0 }}/100 - {{ $analysis['code_quality'] >= 80 ? 'Excellent' : ($analysis['code_quality'] >= 60 ? 'Good' : 'Needs Improvement') }}</li>
                </ul>

                @if(isset($analysis['security_issues']) && count($analysis['security_issues']) > 0)
                <p><strong>Priority Actions:</strong> Address the {{ count($analysis['security_issues']) }} security issue(s) identified in this report before deployment.</p>
                @else
                <p><strong>Status:</strong> No critical security issues identified. Contract is ready for deployment.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated by AI Blockchain Analytics Platform</p>
        <p>For questions or support, please contact our security team</p>
        <p>Report ID: {{ strtoupper(substr(md5($contract['address'] ?? 'unknown' . time()), 0, 8)) }} | Generated: {{ $generated_at->format('Y-m-d H:i:s T') }}</p>
    </div>
</body>
</html>
