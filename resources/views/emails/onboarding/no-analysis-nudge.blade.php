<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Still haven't tried our analyzer? Here's a 2-minute demo...</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .header { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); padding: 40px 30px; text-align: center; }
        .header h1 { color: white; font-size: 28px; margin: 0; font-weight: 700; }
        .header p { color: #FEF3C7; font-size: 16px; margin: 10px 0 0 0; }
        .content { padding: 40px 30px; }
        .demo-video { background: #FEF3C7; border-radius: 12px; padding: 24px; margin: 24px 0; text-align: center; border: 2px solid #F59E0B; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; text-decoration: none; padding: 18px 36px; border-radius: 8px; font-weight: 600; font-size: 18px; margin: 20px 0; }
        .cta-button.secondary { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); }
        .success-story { background: #F0FDF4; border-radius: 12px; padding: 24px; margin: 24px 0; border-left: 4px solid #10B981; }
        .testimonial { background: #F8FAFC; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #3B82F6; font-style: italic; }
        .famous-contracts { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 24px 0; }
        .contract-card { background: #F8FAFC; border-radius: 8px; padding: 16px; text-align: center; border: 2px solid #E5E7EB; transition: all 0.2s; }
        .contract-card:hover { border-color: #3B82F6; transform: translateY(-2px); }
        .contract-card.exploited { border-color: #EF4444; background: #FEF2F2; }
        .footer { background: #1F2937; color: #9CA3AF; padding: 30px; text-align: center; font-size: 14px; }
        .footer a { color: #60A5FA; text-decoration: none; }
        .urgency-banner { background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: white; padding: 16px; text-align: center; font-weight: bold; }
        .stats { display: flex; justify-content: space-around; background: #F0F9FF; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .stat { text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #1D4ED8; }
        .stat-label { font-size: 12px; color: #6B7280; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Urgency Banner -->
        <div class="urgency-banner">
            ⏰ Don't miss out! Join 15,200+ developers using our analyzer
        </div>

        <!-- Header -->
        <div class="header">
            <h1>🚀 Still Haven't Tried It?</h1>
            <p>Here's what you're missing in just 2 minutes...</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p>Hi {{ $user->name ?? 'there' }}! 👋</p>
            
            <p>We noticed you haven't tried our <strong>Live Contract Analyzer</strong> yet. No worries! We know you're busy building amazing things. 🛠️</p>

            <p>But here's the thing - <strong>it takes literally 60 seconds</strong> to analyze a smart contract and get professional-grade security insights. Let us show you why over 15,200 developers are already using it:</p>

            <!-- Platform Success Stats -->
            <div class="stats">
                <div class="stat">
                    <div class="stat-number">15.2K+</div>
                    <div class="stat-label">Active Developers</div>
                </div>
                <div class="stat">
                    <div class="stat-number">1,847</div>
                    <div class="stat-label">Vulnerabilities Caught</div>
                </div>
                <div class="stat">
                    <div class="stat-number">$25B+</div>
                    <div class="stat-label">TVL Protected</div>
                </div>
            </div>

            <!-- 2-Minute Demo Video -->
            <div class="demo-video">
                <h3 style="color: #92400E; margin: 0 0 16px 0;">📹 Watch Our 2-Minute Demo</h3>
                <p style="color: #78350F; margin: 0 0 20px 0; font-size: 16px;">
                    See exactly how we analyze the Uniswap V3 contract and catch potential vulnerabilities in real-time.
                </p>
                <div style="background: white; border-radius: 8px; padding: 20px; margin: 16px 0;">
                    <div style="background: #F3F4F6; height: 200px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #6B7280; font-size: 18px; margin-bottom: 16px;">
                        ▶️ Demo Video Thumbnail<br>
                        <span style="font-size: 14px;">(Uniswap V3 Analysis)</span>
                    </div>
                    <a href="{{ $tutorialUrl }}" class="cta-button secondary">
                        📺 Watch Demo (2 minutes)
                    </a>
                </div>
            </div>

            <!-- Success Story -->
            <div class="success-story">
                <h3 style="color: #065F46; margin: 0 0 16px 0;">💡 Real Success Story</h3>
                <p style="color: #064E3B; margin: 0 0 16px 0; font-weight: 600;">
                    "We caught a critical reentrancy vulnerability that could have cost us $2.3M"
                </p>
                <p style="color: #047857; margin: 0; font-size: 14px;">
                    - Sarah Chen, Lead Developer at DeFi Protocol XYZ
                </p>
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #D1FAE5;">
                    <p style="color: #065F46; margin: 0; font-size: 14px;">
                        <strong>The vulnerability:</strong> Missing reentrancy guard in withdrawal function<br>
                        <strong>Potential impact:</strong> Complete drain of protocol reserves<br>
                        <strong>Detection time:</strong> 23 seconds with our analyzer
                    </p>
                </div>
            </div>

            <!-- Try These Famous Contracts -->
            <h3 style="color: #1F2937; text-align: center;">🏆 Try These One-Click Examples:</h3>
            <p style="color: #6B7280; text-align: center; margin-bottom: 24px;">
                No need to find contract addresses - just click and analyze!
            </p>

            <div class="famous-contracts">
                <div class="contract-card">
                    <div style="font-size: 32px; margin-bottom: 8px;">🦄</div>
                    <strong>Uniswap V3</strong><br>
                    <span style="color: #059669; font-size: 12px;">✅ Secure ($3.5B TVL)</span>
                </div>
                <div class="contract-card">
                    <div style="font-size: 32px; margin-bottom: 8px;">🏦</div>
                    <strong>Aave V3</strong><br>
                    <span style="color: #059669; font-size: 12px;">✅ Low Risk ($2.8B TVL)</span>
                </div>
                <div class="contract-card exploited">
                    <div style="font-size: 32px; margin-bottom: 8px;">🚨</div>
                    <strong>Euler Finance</strong><br>
                    <span style="color: #DC2626; font-size: 12px;">🚨 $197M Exploit</span>
                </div>
                <div class="contract-card exploited">
                    <div style="font-size: 32px; margin-bottom: 8px;">💥</div>
                    <strong>BSC Bridge</strong><br>
                    <span style="color: #DC2626; font-size: 12px;">💥 $570M Loss</span>
                </div>
            </div>

            <!-- Main CTA -->
            <div style="text-align: center; margin: 40px 0;">
                <h3 style="color: #1F2937;">Ready to Try It? (Seriously, 60 seconds!)</h3>
                <a href="{{ $analyzeUrl }}#live-analyzer" class="cta-button" style="font-size: 20px; padding: 20px 40px;">
                    🚀 Analyze a Contract Now (60 sec)
                </a>
                <p style="color: #6B7280; margin-top: 16px; font-size: 14px;">
                    No registration required • Instant results • Professional-grade analysis
                </p>
            </div>

            <!-- Testimonials -->
            <div class="testimonial">
                <p style="margin: 0 0 12px 0; color: #374151;">
                    "I was skeptical at first, but the analysis quality is incredible. Found 3 gas optimizations that saved us $50K in deployment costs."
                </p>
                <p style="margin: 0; color: #6B7280; font-size: 14px;">
                    - Marcus Rodriguez, Smart Contract Auditor
                </p>
            </div>

            <div class="testimonial">
                <p style="margin: 0 0 12px 0; color: #374151;">
                    "The famous contract examples are brilliant for learning. Analyzed the Euler exploit and finally understood donation attacks."
                </p>
                <p style="margin: 0; color: #6B7280; font-size: 14px;">
                    - Alex Kim, Blockchain Developer
                </p>
            </div>

            <!-- What You'll Get -->
            <div style="background: #F0F9FF; border-radius: 12px; padding: 24px; margin: 30px 0;">
                <h3 style="color: #1E40AF; margin: 0 0 16px 0; text-align: center;">🎯 What You Get in 60 Seconds:</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-size: 14px;">
                    <div style="color: #1E3A8A;">🛡️ Security vulnerability scan</div>
                    <div style="color: #1E3A8A;">⚡ Gas optimization suggestions</div>
                    <div style="color: #1E3A8A;">📊 Risk score (0-100)</div>
                    <div style="color: #1E3A8A;">📋 Detailed findings report</div>
                    <div style="color: #1E3A8A;">🔍 Code quality analysis</div>
                    <div style="color: #1E3A8A;">📄 Downloadable PDF report</div>
                </div>
            </div>

            <!-- Last Chance -->
            <div style="background: #FEF2F2; border-radius: 8px; padding: 20px; margin: 24px 0; border-left: 4px solid #EF4444;">
                <h4 style="color: #991B1B; margin: 0 0 12px 0;">⏰ Don't Wait for an Exploit</h4>
                <p style="color: #7C2D12; margin: 0; font-size: 14px;">
                    Every day we delay security analysis is another day vulnerabilities go undetected. 
                    The blockchain industry lost <strong>$3.8 billion</strong> to exploits in 2023. 
                    Don't let your project be next.
                </p>
            </div>

            <!-- Alternative Support -->
            <div style="text-align: center; margin: 30px 0; color: #6B7280;">
                <p><strong>Need help getting started?</strong></p>
                <p>
                    <a href="mailto:{{ config('onboarding.content.support_email') }}" style="color: #3B82F6; text-decoration: none;">
                        📧 Email us
                    </a> | 
                    <a href="{{ $communityUrl }}" style="color: #3B82F6; text-decoration: none;">
                        💬 Join Discord
                    </a> | 
                    <a href="{{ $tutorialUrl }}" style="color: #3B82F6; text-decoration: none;">
                        📚 View Tutorial
                    </a>
                </p>
            </div>

            <p style="color: #6B7280; font-size: 14px; margin-top: 30px;">
                We're here to help you build secure smart contracts! 🛡️<br>
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
