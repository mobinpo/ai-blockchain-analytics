@extends('emails.layout')

@section('content')
<h2>= Discover Advanced Features & Multi-Chain Support</h2>

<p>Hi {{ $user->name }},</p>

<p>
    You've started analyzing contracts - that's awesome! Now let's explore the advanced features that make AI Blockchain Analytics a powerful security tool.
</p>

<div class="feature-box">
    <h3>=p Real-Time Analysis Engine</h3>
    <p>Our AI-powered engine analyzes contracts in real-time, providing instant feedback on:</p>
    <ul>
        <li>Reentrancy vulnerabilities</li>
        <li>Integer overflow risks</li>
        <li>Access control issues</li>
        <li>Gas optimization opportunities</li>
    </ul>
</div>

<div class="feature-box">
    <h3>< Multi-Chain Intelligence</h3>
    <p>Seamlessly analyze contracts across multiple blockchains:</p>
    <ul>
        <li>=5 <strong>Ethereum:</strong> The original smart contract platform</li>
        <li>=ã <strong>Polygon:</strong> Fast, low-cost layer 2 solution</li>
        <li>=á <strong>BSC:</strong> Binance's high-performance blockchain</li>
        <li>=7 <strong>Arbitrum:</strong> Optimistic rollup scaling solution</li>
        <li>=4 <strong>Optimism:</strong> Another layer 2 scaling solution</li>
        <li>=à <strong>Fantom:</strong> High-speed, low-cost platform</li>
    </ul>
</div>

<div class="feature-box">
    <h3>=È Professional Reporting</h3>
    <p>Generate comprehensive reports for:</p>
    <ul>
        <li>Security audits and compliance</li>
        <li>Development team reviews</li>
        <li>Investor due diligence</li>
        <li>Regulatory submissions</li>
    </ul>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{ $featuresUrl }}" class="cta-button">
        =€ Explore All Features
    </a>
</div>

<h3>=Ê This Week's Platform Stats</h3>
<div class="stats-container">
    <div class="stat-item">
        <span class="stat-number">1,247</span>
        <div class="stat-label">Contracts Analyzed</div>
    </div>
    <div class="stat-item">
        <span class="stat-number">89</span>
        <div class="stat-label">Critical Issues Found</div>
    </div>
    <div class="stat-item">
        <span class="stat-number">$2.4M</span>
        <div class="stat-label">Gas Savings Identified</div>
    </div>
</div>

<p>
    Want to see these features in action? Join our weekly demo sessions or check out our video tutorials.
</p>

<p>
    Keep building secure smart contracts!<br>
    <strong>The AI Blockchain Analytics Team</strong>
</p>
@endsection