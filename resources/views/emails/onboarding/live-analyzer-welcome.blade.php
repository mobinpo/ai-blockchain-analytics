@extends('emails.layout')

@section('content')
<div style="background: linear-gradient(135deg, #4299e1 0%, #8b5cf6 100%); margin: -40px -30px 30px; padding: 30px; text-align: center; color: white; border-radius: 8px;">
    <h2 style="color: white; margin: 0 0 10px; font-size: 28px;">🔍 Thanks for trying our Live Analyzer!</h2>
    <p style="color: white; opacity: 0.9; font-size: 18px; margin: 0;">Hello {{ $user->name }}, ready to unlock the full power of contract analysis?</p>
</div>

<p style="font-size: 18px; margin-bottom: 25px;">
    We noticed you tried our <strong>one-click live analyzer</strong> - that's awesome! 🎉 
    You've just experienced the tip of the iceberg of what our platform can do.
</p>

<div style="background: #e6fffa; padding: 25px; border-radius: 12px; border-left: 4px solid #38b2ac; margin: 25px 0;">
    <h3 style="color: #234e52; margin: 0 0 15px; font-size: 18px;">🚀 What You Just Experienced</h3>
    <ul style="color: #2d3748; margin: 0; padding-left: 20px;">
        <li><strong>Instant Analysis:</strong> Sub-second vulnerability detection</li>
        <li><strong>Famous Contracts:</strong> Pre-loaded analysis of $25B+ TVL protocols</li>
        <li><strong>Real-time Results:</strong> Live security scoring and gas optimization</li>
        <li><strong>No Registration Required:</strong> Immediate access to powerful insights</li>
    </ul>
</div>

<div class="feature-box">
    <h3>🛡️ Now Get Even More with Your Account</h3>
    <ul>
        <li><strong>Analysis History:</strong> Save and track all your contract analyses</li>
        <li><strong>Advanced Reporting:</strong> Detailed PDF reports with executive summaries</li>
        <li><strong>Multi-Project Management:</strong> Organize contracts by project or portfolio</li>
        <li><strong>Custom Alerts:</strong> Get notified about new vulnerabilities in your contracts</li>
        <li><strong>Team Collaboration:</strong> Share findings with your development team</li>
    </ul>
</div>

<div class="feature-box">
    <h3>⚡ Quick Start Guide</h3>
    <p>Since you already know how the live analyzer works, here are your next steps:</p>
    <ol>
        <li><strong>Create Your First Project:</strong> Organize your contract analyses</li>
        <li><strong>Upload Multiple Contracts:</strong> Analyze entire protocol suites</li>
        <li><strong>Set Up Monitoring:</strong> Get alerts for new vulnerabilities</li>
        <li><strong>Generate Reports:</strong> Professional PDFs for stakeholders</li>
    </ol>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{ $dashboardUrl }}" class="cta-button">
        📊 Access Your Dashboard
    </a>
</div>

<div style="background: #fff5f5; padding: 25px; border-radius: 12px; border-left: 4px solid #f56565; margin: 25px 0;">
    <h3 style="color: #742a2a; margin: 0 0 15px; font-size: 18px;">🚨 Famous Exploits You Can Analyze</h3>
    <p style="color: #4a5568; margin: 0 0 15px;">
        Since you're interested in contract security, try analyzing these famous exploits to understand what went wrong:
    </p>
    <ul style="color: #4a5568; margin: 0; padding-left: 20px;">
        <li><strong>Euler Finance:</strong> $197M exploit (March 2023) - Donation attack vulnerability</li>
        <li><strong>BSC Token Hub:</strong> $570M exploit (October 2022) - Bridge vulnerability</li>
        <li><strong>Wormhole Bridge:</strong> $320M exploit - Signature verification bypass</li>
    </ul>
    <p style="color: #4a5568; margin: 15px 0 0; font-size: 14px;">
        💡 <em>Learning from these exploits helps you build more secure contracts</em>
    </p>
</div>

<div class="stats-container" style="background: #f8fafc; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0;">
    <div style="text-align: center; margin-bottom: 15px;">
        <span style="font-size: 16px; color: #718096; font-weight: 600;">🎯 Platform Statistics</span>
    </div>
    <div class="stat-item">
        <span class="stat-number" style="color: #48bb78;">25.3K+</span>
        <div class="stat-label">Contracts Analyzed</div>
    </div>
    <div class="stat-item">
        <span class="stat-number" style="color: #ed8936;">3,247</span>
        <div class="stat-label">Vulnerabilities Found</div>
    </div>
    <div class="stat-item">
        <span class="stat-number" style="color: #9f7aea;">$25B+</span>
        <div class="stat-label">Total Value Locked Analyzed</div>
    </div>
    <div class="stat-item">
        <span class="stat-number" style="color: #38b2ac;">97.8%</span>
        <div class="stat-label">Detection Accuracy</div>
    </div>
</div>

<p style="color: #718096; font-size: 14px; margin-top: 30px;">
    Questions? Just reply to this email - we're here to help! 🤝
</p>

<div style="text-align: center; margin: 25px 0;">
    <a href="{{ $dashboardUrl }}" style="color: #4299e1; text-decoration: none; font-weight: 600;">
        🏠 Go to Dashboard
    </a>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <a href="{{ $analyzeUrl }}" style="color: #4299e1; text-decoration: none; font-weight: 600;">
        🔍 Live Analyzer
    </a>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <a href="{{ $supportUrl }}" style="color: #4299e1; text-decoration: none; font-weight: 600;">
        💬 Get Support
    </a>
</div>
@endsection
