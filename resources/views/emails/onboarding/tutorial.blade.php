@extends('emails.layout')

@section('content')
<h2>=Ú Quick Tutorial: Analyze Your First Smart Contract</h2>

<p>Hi {{ $user->name }},</p>

<p>
    Ready to dive in? Let's walk through your first smart contract analysis in just 3 simple steps!
</p>

<div class="feature-box">
    <h3>Step 1: Choose Your Input Method</h3>
    <p>You can analyze contracts in two ways:</p>
    <ul>
        <li><strong>Contract Address:</strong> Paste any deployed contract address (starts with 0x...)</li>
        <li><strong>Source Code:</strong> Copy and paste Solidity source code directly</li>
    </ul>
</div>

<div class="feature-box">
    <h3>Step 2: Select Your Network</h3>
    <p>Choose from supported networks:</p>
    <ul>
        <li>=5 Ethereum Mainnet</li>
        <li>=ã Polygon</li>
        <li>=á BSC (Binance Smart Chain)</li>
        <li>=7 Arbitrum</li>
    </ul>
</div>

<div class="feature-box">
    <h3>Step 3: Review Results</h3>
    <p>Get comprehensive analysis including:</p>
    <ul>
        <li>=¨ Security vulnerabilities by severity</li>
        <li>¡ Gas optimization suggestions</li>
        <li>=Ê Risk score and metrics</li>
        <li>=Ä Downloadable PDF reports</li>
    </ul>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{ $tutorialUrl }}" class="cta-button">
        <¯ Try Interactive Tutorial
    </a>
</div>

<h3>=Ë Popular Contract Examples</h3>
<p>Not sure where to start? Try analyzing these well-known contracts:</p>

<ul>
    <li><strong>Uniswap V3 Factory:</strong> 0x1F98431c8aD98523631AE4a59f267346ea31F984</li>
    <li><strong>Aave V3 Pool:</strong> 0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2</li>
    <li><strong>OpenSea Seaport:</strong> 0x00000000000000ADc04C56Bf30aC9d3c0aAF14dC</li>
</ul>

<p>
    Each analysis takes just a few seconds and provides professional-grade security insights.
</p>

<p>
    Questions? Reply to this email or check our documentation for detailed guides.
</p>

<p>
    Happy analyzing!<br>
    <strong>The AI Blockchain Analytics Team</strong>
</p>
@endsection