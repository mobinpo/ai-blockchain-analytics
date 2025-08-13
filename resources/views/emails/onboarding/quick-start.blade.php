<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Start: Analyze Your First Contract in 60 Seconds</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .header { background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%); padding: 40px 30px; text-align: center; }
        .header h1 { color: white; font-size: 28px; margin: 0; font-weight: 700; }
        .header p { color: #E0E7FF; font-size: 16px; margin: 10px 0 0 0; }
        .content { padding: 40px 30px; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; text-decoration: none; padding: 16px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0; }
        .cta-button:hover { transform: translateY(-2px); transition: all 0.2s; }
        .famous-contracts { background: #F8FAFC; border-radius: 12px; padding: 24px; margin: 24px 0; }
        .contract-item { background: white; border-radius: 8px; padding: 16px; margin: 12px 0; border-left: 4px solid #3B82F6; }
        .contract-item.exploited { border-left-color: #EF4444; }
        .steps { margin: 30px 0; }
        .step { display: flex; align-items: flex-start; margin: 20px 0; }
        .step-number { background: #3B82F6; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 16px; flex-shrink: 0; }
        .footer { background: #1F2937; color: #9CA3AF; padding: 30px; text-align: center; font-size: 14px; }
        .footer a { color: #60A5FA; text-decoration: none; }
        .stats { display: flex; justify-content: space-around; background: #F0F9FF; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .stat { text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #1D4ED8; }
        .stat-label { font-size: 12px; color: #6B7280; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>⚡ Quick Start Guide</h1>
            <p>Analyze your first smart contract in just 60 seconds</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p>Hi {{ $user->name ?? 'there' }}! 👋</p>
            
            <p>Ready to dive into smart contract security analysis? Let's get you started with your first analysis in under a minute!</p>

            <!-- Platform Stats -->
            <div class="stats">
                <div class="stat">
                    <div class="stat-number">15.2K+</div>
                    <div class="stat-label">Contracts Analyzed</div>
                </div>
                <div class="stat">
                    <div class="stat-number">1,847</div>
                    <div class="stat-label">Vulnerabilities Found</div>
                </div>
                <div class="stat">
                    <div class="stat-number">95%</div>
                    <div class="stat-label">Detection Accuracy</div>
                </div>
            </div>

            <!-- 3-Step Quick Start -->
            <div class="steps">
                <h3 style="color: #1F2937; margin-bottom: 24px;">🚀 3 Simple Steps to Your First Analysis:</h3>
                
                <div class="step">
                    <div class="step-number">1</div>
                    <div>
                        <strong>Visit Our Live Analyzer</strong><br>
                        <span style="color: #6B7280;">Go to our homepage and find the prominent "Live Contract Analyzer" section</span>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div>
                        <strong>Paste a Contract Address</strong><br>
                        <span style="color: #6B7280;">Copy any Ethereum contract address (starts with 0x...) or try our famous examples below</span>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div>
                        <strong>Click "Analyze Now"</strong><br>
                        <span style="color: #6B7280;">Get professional-grade security analysis in seconds - no registration required!</span>
                    </div>
                </div>
            </div>

            <!-- CTA Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $analyzeUrl }}#live-analyzer" class="cta-button">
                    🚀 Start Analyzing Now (60 seconds)
                </a>
            </div>

            <!-- Famous Contracts to Try -->
            <div class="famous-contracts">
                <h3 style="color: #1F2937; margin-bottom: 16px;">🏆 Try These Famous Contracts (One-Click Examples):</h3>
                
                <div class="contract-item">
                    <strong>🦄 Uniswap V3 Router</strong> - $3.5B TVL<br>
                    <code style="background: #F3F4F6; padding: 2px 6px; border-radius: 4px; font-size: 12px;">0xE592427A0AEce92De3Edee1F18E0157C05861564</code><br>
                    <span style="color: #059669; font-size: 14px;">✅ Low Risk (15/100) - Premier DEX Protocol</span>
                </div>
                
                <div class="contract-item">
                    <strong>🏦 Aave V3 Pool</strong> - $2.8B TVL<br>
                    <code style="background: #F3F4F6; padding: 2px 6px; border-radius: 4px; font-size: 12px;">0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2</code><br>
                    <span style="color: #059669; font-size: 14px;">✅ Low Risk (25/100) - Leading Lending Protocol</span>
                </div>
                
                <div class="contract-item exploited">
                    <strong>🚨 Euler Finance</strong> - $197M Exploit (Educational)<br>
                    <code style="background: #F3F4F6; padding: 2px 6px; border-radius: 4px; font-size: 12px;">0x27182842E098f60e3D576794A5bFFb0777E025d3</code><br>
                    <span style="color: #DC2626; font-size: 14px;">🚨 Critical Risk (95/100) - Learn from Real Exploit</span>
                </div>
            </div>

            <!-- Video Tutorial -->
            <div style="background: #FEF3C7; border-radius: 8px; padding: 20px; margin: 24px 0; border-left: 4px solid #F59E0B;">
                <h4 style="color: #92400E; margin: 0 0 8px 0;">📹 2-Minute Video Tutorial</h4>
                <p style="color: #78350F; margin: 0; font-size: 14px;">
                    Prefer to watch? Check out our quick video tutorial showing the entire analysis process step-by-step.
                </p>
                <div style="margin-top: 12px;">
                    <a href="{{ $tutorialUrl }}" style="color: #92400E; font-weight: 600; text-decoration: none;">
                        ▶️ Watch Tutorial (2 min)
                    </a>
                </div>
            </div>

            <!-- What You'll Get -->
            <div style="margin: 30px 0;">
                <h3 style="color: #1F2937;">🎯 What You'll Get from Your Analysis:</h3>
                <ul style="color: #4B5563; line-height: 1.6;">
                    <li><strong>Risk Score (0-100):</strong> Overall security assessment</li>
                    <li><strong>Vulnerability Detection:</strong> Critical, high, medium, and low-risk findings</li>
                    <li><strong>Gas Optimization:</strong> Suggestions to reduce transaction costs</li>
                    <li><strong>Code Quality Analysis:</strong> Best practices and recommendations</li>
                    <li><strong>Detailed Report:</strong> Professional PDF you can download and share</li>
                </ul>
            </div>

            <!-- Next Steps -->
            <div style="background: #F0F9FF; border-radius: 8px; padding: 20px; margin: 24px 0;">
                <h4 style="color: #1E40AF; margin: 0 0 12px 0;">🔮 Coming Up Next...</h4>
                <p style="color: #1E3A8A; margin: 0; font-size: 14px;">
                    Over the next few days, we'll send you educational content about smart contract security, 
                    real-world exploit case studies, and advanced analysis techniques. Stay tuned! 📚
                </p>
            </div>

            <!-- Support -->
            <div style="text-align: center; margin: 30px 0; color: #6B7280;">
                <p>Questions? We're here to help!</p>
                <p>
                    <a href="mailto:{{ config('onboarding.content.support_email') }}" style="color: #3B82F6; text-decoration: none;">
                        📧 {{ config('onboarding.content.support_email') }}
                    </a>
                </p>
            </div>

            <p style="color: #6B7280; font-size: 14px; margin-top: 30px;">
                Happy analyzing! 🚀<br>
                The AI Blockchain Analytics Team
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>{{ config('onboarding.content.platform_name') }}</strong></p>
            <p>Professional Smart Contract Security Analysis</p>
            <p>
                <a href="{{ config('onboarding.content.social_links.twitter') }}">Twitter</a> | 
                <a href="{{ config('onboarding.content.social_links.github') }}">GitHub</a> | 
                <a href="{{ config('onboarding.content.social_links.discord') }}">Discord</a>
            </p>
            <p style="margin-top: 20px; font-size: 12px;">
                <a href="{{ $unsubscribeUrl }}">Unsubscribe</a> | 
                <a href="{{ config('onboarding.content.platform_url') }}/privacy">Privacy Policy</a>
            </p>
        </div>
    </div>
</body>
</html>
