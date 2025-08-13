@extends('emails.layout')

@section('content')
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: -40px -30px 30px; padding: 30px; text-align: center; color: white; border-radius: 8px;">
    <h2 style="color: white; margin: 0 0 10px; font-size: 28px;">🚀 Welcome to AI Blockchain Analytics!</h2>
    <p style="color: white; opacity: 0.9; font-size: 18px; margin: 0;">Hello {{ $user->name }}, you're now part of the future of smart contract security!</p>
    <p style="color: white; opacity: 0.8; font-size: 14px; margin: 10px 0 0;">🎯 You can now access our one-click live analyzer and premium features</p>
</div>

<p style="font-size: 18px; margin-bottom: 25px;">
    Thank you for joining our platform! You're now part of a community that's revolutionizing smart contract security and analysis with <strong>AI-powered insights</strong> and <strong>real-time vulnerability detection</strong>.
</p>

<p>
    With AI Blockchain Analytics, you can:
</p>

<div class="feature-box">
    <h3>🛡️ Advanced Security Analysis</h3>
    <p>Detect vulnerabilities using OWASP standards and AI-powered analysis across multiple blockchain networks.</p>
</div>

<div class="feature-box">
    <h3>⚡ Gas Optimization</h3>
    <p>Identify gas inefficiencies and receive actionable recommendations to reduce transaction costs.</p>
</div>

<div class="feature-box">
    <h3>🌐 Multi-Chain Support</h3>
    <p>Analyze contracts on Ethereum, Polygon, BSC, Arbitrum, Optimism, and more.</p>
</div>

<div style="background: #f7fafc; padding: 25px; border-radius: 12px; border-left: 4px solid #4299e1; margin: 25px 0;">
    <h3 style="color: #2d3748; margin: 0 0 15px; font-size: 18px;">🚀 NEW: One-Click Live Analyzer</h3>
    <p style="color: #4a5568; margin: 0 0 15px;">
        Since you registered, we've launched our revolutionary one-click contract analyzer! 
        No complex setup required - just paste any contract address and get instant results.
    </p>
    <p style="color: #4a5568; margin: 0; font-size: 14px;">
        ⚡ Try these famous contracts: Uniswap V3, Aave V3, or explore recent exploits like Euler Finance ($197M hack)
    </p>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{ $analyzeUrl }}" class="cta-button">
        🔍 Try One-Click Analyzer Now
    </a>
</div>

<div class="stats-container" style="background: #f8fafc; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 15px;">
        <span style="font-size: 16px; color: #718096; font-weight: 600;">🎯 Platform Statistics</span>
    </div>
    <div class="stat-item">
        <span class="stat-number" style="color: #48bb78;">15.2K+</span>
        <div class="stat-label">Contracts Analyzed</div>
    </div>
    <div class="stat-item">
        <span class="stat-number" style="color: #ed8936;">1,847</span>
        <div class="stat-label">Vulnerabilities Found</div>
    </div>
    <div class="stat-item">
        <span class="stat-number" style="color: #667eea;">96%</span>
        <div class="stat-label">Detection Accuracy</div>
    </div>
</div>

<div style="background: #e6fffa; border: 1px solid #38b2ac; border-radius: 8px; padding: 20px; margin: 25px 0;">
    <h3 style="color: #234e52; margin: 0 0 15px; font-size: 18px;">🎯 Quick Start Guide</h3>
    <ol style="margin: 0; padding-left: 20px;">
        <li style="margin-bottom: 8px;"><strong>Try our Live Analyzer:</strong> Paste any contract address or Solidity code for instant analysis</li>
        <li style="margin-bottom: 8px;"><strong>Explore Examples:</strong> Check out pre-loaded examples like Uniswap V3 and Aave V3</li>
        <li style="margin-bottom: 8px;"><strong>Review Security Findings:</strong> Understand risk scores and vulnerability categories</li>
        <li style="margin-bottom: 8px;"><strong>Export Reports:</strong> Generate PDF reports for your security audits</li>
    </ol>
</div>

<div style="background: #fef5e7; border: 1px solid #f6e05e; border-radius: 8px; padding: 20px; margin: 25px 0;">
    <h3 style="color: #744210; margin: 0 0 10px; font-size: 16px;">💡 Pro Tip</h3>
    <p style="color: #744210; margin: 0; font-size: 14px;">Start with our famous contract examples - they include real exploited contracts for educational purposes and well-audited protocols for comparison!</p>
</div>

<p>
    Need help? Our documentation covers everything from basic usage to advanced security patterns. 
    You can also reach out to our support team anytime.
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{ $docsUrl }}" class="cta-button" style="background: #48bb78; box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4); margin-right: 15px;">
        📚 Read Documentation
    </a>
    <a href="{{ $tutorialUrl }}" class="cta-button" style="background: #ed8936; box-shadow: 0 4px 12px rgba(237, 137, 54, 0.4);">
        🎓 Start Tutorial
    </a>
</div>

<div style="background: #f7fafc; border-radius: 8px; padding: 20px; margin: 30px 0; text-align: center;">
    <h3 style="color: #2d3748; margin: 0 0 10px;">🚀 What's Next?</h3>
    <p style="margin: 0 0 15px; color: #718096;">Keep an eye on your inbox - we'll send you helpful tips and updates to help you master smart contract security analysis!</p>
    <p style="margin: 0; color: #4a5568; font-size: 14px;">Expected next email: <strong>Getting Started Guide</strong> in 1 hour</p>
</div>

<p style="font-size: 18px; text-align: center; margin: 30px 0;">
    We're excited to see what you'll discover with AI Blockchain Analytics! 🎉
</p>

<p style="text-align: center;">
    Best regards,<br>
    <strong>The AI Blockchain Analytics Team</strong><br>
    <small style="color: #718096;">Building the future of smart contract security</small>
</p>
@endsection