<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ready to analyze your first smart contract? 📊</title>
    <style>
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 20px 0;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        .step-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 15px;
            font-size: 14px;
        }
        .code-block {
            background: #1f2937;
            color: #d1d5db;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 15px 0;
            overflow-x: auto;
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
        .quick-links {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
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
            <h1>📊 Let's Get Started!</h1>
            <p>Your complete guide to smart contract analysis</p>
        </div>

        <div class="content">
            <h2>Hi {{ $user->name ?? 'there' }}! 🚀</h2>
            
            <p>Ready to dive into smart contract analysis? We've made it incredibly simple to get started. Here's everything you need to know:</p>

            <div class="step-box">
                <h3><span class="step-number">1</span>Choose Your Analysis Method</h3>
                <p>You can analyze contracts in two ways:</p>
                <ul>
                    <li><strong>Contract Address:</strong> Paste any deployed contract address (0x...)</li>
                    <li><strong>Source Code:</strong> Copy and paste Solidity code directly</li>
                </ul>
            </div>

            <div class="step-box">
                <h3><span class="step-number">2</span>Select Your Network</h3>
                <p>We support all major blockchain networks:</p>
                <ul>
                    <li>🔵 Ethereum Mainnet</li>
                    <li>🟣 Polygon</li>
                    <li>🟡 Binance Smart Chain</li>
                    <li>🔷 Arbitrum</li>
                    <li>🔴 Optimism</li>
                </ul>
            </div>

            <div class="step-box">
                <h3><span class="step-number">3</span>Understand Your Results</h3>
                <p>Our AI provides:</p>
                <ul>
                    <li><strong>Risk Score (0-100):</strong> Overall security assessment</li>
                    <li><strong>Security Findings:</strong> Detailed vulnerability reports</li>
                    <li><strong>Gas Optimization:</strong> Cost-saving recommendations</li>
                    <li><strong>Best Practices:</strong> Code improvement suggestions</li>
                </ul>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $analyzerUrl }}" class="cta-button">
                    Try the Live Analyzer Now 🔍
                </a>
            </div>

            <div class="quick-links">
                <h4 style="color: #92400e; margin: 0 0 15px 0;">🎯 Quick Start Examples</h4>
                <p style="margin: 0; color: #92400e;">Try analyzing these famous contracts:</p>
                <div class="code-block">
                    Uniswap V3: 0x1F98431c8aD98523631AE4a59f267346ea31F984<br>
                    Aave V3: 0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2<br>
                    OpenSea: 0x00000000000000ADc04C56Bf30aC9d3c0aAF14dC
                </div>
            </div>

            <h3>🔥 Pro Features Available</h3>
            <ul>
                <li>✅ <strong>Detailed PDF Reports:</strong> Professional audit documentation</li>
                <li>✅ <strong>Historical Analysis:</strong> Track changes over time</li>
                <li>✅ <strong>Batch Processing:</strong> Analyze multiple contracts</li>
                <li>✅ <strong>API Access:</strong> Integrate with your workflow</li>
                <li>✅ <strong>Team Collaboration:</strong> Share findings with your team</li>
            </ul>

            <p>Questions? Just reply to this email - we're here to help! 💪</p>

            <div style="background: #e0f2fe; border: 1px solid #0284c7; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="color: #0c4a6e; margin: 0 0 10px 0;">📚 Learning Resources</h4>
                <p style="margin: 0; color: #0c4a6e;">
                    Check out our <a href="{{ $docsUrl }}" style="color: #0284c7;">comprehensive documentation</a> 
                    and <a href="{{ $tutorialsUrl }}" style="color: #0284c7;">video tutorials</a> 
                    to become a smart contract security expert!
                </p>
            </div>
        </div>

        <div class="footer">
            <p><strong>Happy Analyzing! 🎉</strong></p>
            <p>The AI Blockchain Analytics Team</p>
            
            <p style="margin-top: 30px;">
                <a href="{{ $supportUrl }}">Get Help</a> | 
                <a href="{{ $docsUrl }}">Documentation</a> | 
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