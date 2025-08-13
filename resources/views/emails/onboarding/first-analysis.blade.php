<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $hasAnalyzed ? 'Great job on your first analysis!' : 'Haven\'t tried our analyzer yet?' }} 🎯</title>
    <style>
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .header {
            background: {{ $hasAnalyzed ? 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)' : 'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)' }};
            padding: 40px 30px;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }
        .header h1 {
            color: white;
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .header p {
            color: rgba(255,255,255,0.9);
            margin: 10px 0 0 0;
            font-size: 16px;
        }
        .content {
            background: white;
            padding: 40px 30px;
            border-left: 1px solid #e1e5e9;
            border-right: 1px solid #e1e5e9;
        }
        .cta-button {
            display: inline-block;
            background: {{ $hasAnalyzed ? 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)' : 'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)' }};
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 20px 0;
            font-size: 16px;
            box-shadow: 0 4px 12px {{ $hasAnalyzed ? 'rgba(245, 158, 11, 0.4)' : 'rgba(139, 92, 246, 0.4)' }};
        }
        .stats-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            margin: 25px 0;
            text-align: center;
        }
        .stat-item {
            display: inline-block;
            margin: 0 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #1f2937;
            display: block;
        }
        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin-top: 5px;
        }
        .achievement-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            position: relative;
        }
        .achievement-badge {
            position: absolute;
            top: -15px;
            left: 30px;
            background: #f59e0b;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .vulnerability-highlight {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .tip-box {
            background: #e0f2fe;
            border: 1px solid #0284c7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .footer {
            background: #f8fafc;
            padding: 30px;
            text-align: center;
            border-radius: 0 0 12px 12px;
            border: 1px solid #e1e5e9;
            border-top: none;
            color: #6b7280;
        }
        .unsubscribe {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 20px;
        }
        .unsubscribe a {
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            @if($hasAnalyzed)
                <h1>🎉 Fantastic Work!</h1>
                <p>Your smart contract analysis journey is off to a great start</p>
            @else
                <h1>🎯 Ready to Discover Vulnerabilities?</h1>
                <p>Your first analysis is just one click away</p>
            @endif
        </div>

        <div class="content">
            <h2>Hi {{ $user->name ?? 'there' }}! 👋</h2>
            
            @if($hasAnalyzed)
                <p>Congratulations on completing your first smart contract analysis! 🚀 You're already ahead of 90% of developers who deploy without proper security review.</p>

                @if(isset($analysisResults))
                <div class="achievement-box">
                    <div class="achievement-badge">ACHIEVEMENT UNLOCKED</div>
                    <h3 style="color: #92400e; margin: 20px 0 15px 0;">🏆 First Analysis Complete!</h3>
                    <p style="color: #92400e; margin: 0;">You've taken the first step towards building more secure smart contracts!</p>
                </div>

                <div class="stats-box">
                    <h3>Your Analysis Results 📊</h3>
                    <div style="margin: 20px 0;">
                        <div class="stat-item">
                            <span class="stat-number" style="color: {{ $analysisResults['riskScore'] > 70 ? '#ef4444' : ($analysisResults['riskScore'] > 40 ? '#f59e0b' : '#10b981') }}">
                                {{ $analysisResults['riskScore'] ?? 'N/A' }}
                            </span>
                            <div class="stat-label">Risk Score</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" style="color: #8b5cf6">{{ $analysisResults['findingsCount'] ?? 0 }}</span>
                            <div class="stat-label">Findings</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" style="color: #10b981">{{ $analysisResults['gasEfficiency'] ?? 'N/A' }}%</span>
                            <div class="stat-label">Gas Efficiency</div>
                        </div>
                    </div>
                </div>

                @if(isset($analysisResults['criticalFindings']) && count($analysisResults['criticalFindings']) > 0)
                <div class="vulnerability-highlight">
                    <h4 style="color: #dc2626; margin: 0 0 15px 0;">🚨 Critical Findings Detected</h4>
                    @foreach(array_slice($analysisResults['criticalFindings'], 0, 2) as $finding)
                    <p style="color: #dc2626; margin: 8px 0;"><strong>{{ $finding['title'] ?? 'Security Issue' }}:</strong> {{ $finding['description'] ?? 'Review required' }}</p>
                    @endforeach
                </div>
                @endif
                @endif

                <h3>🎯 What's Next?</h3>
                <p>Now that you've completed your first analysis, here are some ways to level up:</p>
                <ul>
                    <li>📄 <strong>Download the PDF report</strong> for detailed documentation</li>
                    <li>🔧 <strong>Implement the recommendations</strong> to improve security</li>
                    <li>🔄 <strong>Run follow-up analyses</strong> after making changes</li>
                    <li>👥 <strong>Share results with your team</strong> for collaboration</li>
                </ul>

            @else
                <p>We noticed you haven't tried our smart contract analyzer yet. No worries - we know you're busy building amazing things! 🛠️</p>
                
                <p>But here's the thing: <strong>68% of smart contract exploits could have been prevented</strong> with proper security analysis. Let us help you be part of the secure 32%! 🛡️</p>

                <div class="stats-box">
                    <h3>Why Analysis Matters 📊</h3>
                    <div style="margin: 20px 0;">
                        <div class="stat-item">
                            <span class="stat-number" style="color: #ef4444">$3.8B</span>
                            <div class="stat-label">Lost in 2023 to exploits</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" style="color: #f59e0b">68%</span>
                            <div class="stat-label">Preventable with analysis</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" style="color: #10b981">30 sec</span>
                            <div class="stat-label">Average analysis time</div>
                        </div>
                    </div>
                </div>

                <div class="vulnerability-highlight">
                    <h4 style="color: #dc2626; margin: 0 0 15px 0;">⚠️ Common Vulnerabilities We Detect</h4>
                    <ul style="color: #dc2626; margin: 0;">
                        <li><strong>Reentrancy attacks</strong> (like the DAO hack)</li>
                        <li><strong>Integer overflow/underflow</strong> vulnerabilities</li>
                        <li><strong>Unchecked external calls</strong> and return values</li>
                        <li><strong>Gas optimization</strong> opportunities</li>
                    </ul>
                </div>
            @endif

            <div style="text-align: center; margin: 30px 0;">
                @if($hasAnalyzed)
                    <a href="{{ $dashboardUrl }}" class="cta-button">
                        View Your Dashboard 📊
                    </a>
                @else
                    <a href="{{ $analyzerUrl }}" class="cta-button">
                        Start Your First Analysis 🔍
                    </a>
                @endif
            </div>

            <div class="tip-box">
                <h4 style="color: #0c4a6e; margin: 0 0 10px 0;">💡 Pro Tip</h4>
                <p style="margin: 0; color: #0c4a6e;">
                    @if($hasAnalyzed)
                        Set up automated monitoring for your contracts to get alerts when new vulnerabilities are discovered or when your contracts are upgraded.
                    @else
                        Start with analyzing a famous contract like Uniswap or Aave to see how our analysis works. It's a great way to learn about security patterns!
                    @endif
                </p>
            </div>

            <p>Questions about your results or need help interpreting findings? Just reply to this email! 💪</p>
        </div>

        <div class="footer">
            <p><strong>Keep Building Securely! 🔐</strong></p>
            <p>The AI Blockchain Analytics Team</p>
            
            <p style="margin-top: 30px;">
                <a href="{{ $supportUrl }}">Get Help</a> | 
                <a href="{{ $docsUrl }}">Security Docs</a> | 
                <a href="{{ $communityUrl }}">Join Community</a>
            </p>

            <div class="unsubscribe">
                <p>
                    <a href="{{ $unsubscribeUrl }}">Manage preferences</a> | 
                    <a href="{{ $unsubscribeUrl }}&type=onboarding">Unsubscribe from onboarding emails</a>
                </p>
            </div>
        </div>
    </div>

    @if(isset($trackingPixel))
        <img src="{{ $trackingPixel }}" width="1" height="1" alt="" />
    @endif
</body>
</html>