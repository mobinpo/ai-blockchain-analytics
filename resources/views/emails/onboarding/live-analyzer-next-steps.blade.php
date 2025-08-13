@extends('emails.layout')

@section('content')
<div style="background: linear-gradient(135deg, #48bb78 0%, #38b2ac 100%); margin: -40px -30px 30px; padding: 30px; text-align: center; color: white; border-radius: 8px;">
    <h2 style="color: white; margin: 0 0 10px; font-size: 28px;">🎯 Ready for Your Next Analysis?</h2>
    <p style="color: white; opacity: 0.9; font-size: 18px; margin: 0;">You've analyzed {{ $analysis_count ?? 1 }} contract{{ $analysis_count > 1 ? 's' : '' }} - let's take it to the next level!</p>
</div>

<p style="font-size: 18px; margin-bottom: 25px;">
    Hi {{ $user->name }}! 👋 We saw you've been exploring our live analyzer. 
    That's exactly how the best security researchers start - by getting their hands dirty with real contracts!
</p>

<div style="background: #edf2f7; padding: 25px; border-radius: 12px; margin: 25px 0;">
    <h3 style="color: #2d3748; margin: 0 0 15px; font-size: 18px;">📊 Your Analysis Summary</h3>
    @if(isset($previous_analyses) && count($previous_analyses) > 0)
        <ul style="color: #4a5568; margin: 0; padding-left: 20px;">
            @foreach($previous_analyses as $analysis)
                <li>
                    <strong>{{ $analysis['network'] ?? 'ethereum' }}:</strong> 
                    {{ substr($analysis['contract_address'], 0, 10) }}... 
                    <span style="color: #718096; font-size: 12px;">({{ \Carbon\Carbon::parse($analysis['timestamp'])->diffForHumans() }})</span>
                </li>
            @endforeach
        </ul>
    @else
        <p style="color: #4a5568; margin: 0;">You've started exploring contract analysis - great choice!</p>
    @endif
</div>

<div class="feature-box">
    <h3>🚀 What's Next? Unlock Premium Features</h3>
    <p>Now that you've seen our live analyzer in action, here's what you can do with your account:</p>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
        <div style="background: #f0fff4; padding: 20px; border-radius: 8px; border: 1px solid #9ae6b4;">
            <h4 style="color: #22543d; margin: 0 0 10px;">📁 Project Management</h4>
            <p style="color: #2f855a; margin: 0; font-size: 14px;">Organize contracts by project, track analysis history, and collaborate with team members.</p>
        </div>
        
        <div style="background: #fef5e7; padding: 20px; border-radius: 8px; border: 1px solid #f6e05e;">
            <h4 style="color: #744210; margin: 0 0 10px;">📊 Advanced Reports</h4>
            <p style="color: #b7791f; margin: 0; font-size: 14px;">Generate professional PDF reports with executive summaries and detailed findings.</p>
        </div>
        
        <div style="background: #e6fffa; padding: 20px; border-radius: 8px; border: 1px solid #81e6d9;">
            <h4 style="color: #234e52; margin: 0 0 10px;">🔔 Smart Alerts</h4>
            <p style="color: #2c7a7b; margin: 0; font-size: 14px;">Get notified when new vulnerabilities are discovered in your monitored contracts.</p>
        </div>
        
        <div style="background: #faf5ff; padding: 20px; border-radius: 8px; border: 1px solid #d6bcfa;">
            <h4 style="color: #44337a; margin: 0 0 10px;">🤖 AI Insights</h4>
            <p style="color: #6b46c1; margin: 0; font-size: 14px;">Advanced AI analysis with custom focus areas and personalized recommendations.</p>
        </div>
    </div>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{ $dashboardUrl }}" class="cta-button">
        🎯 Start Your First Project
    </a>
</div>

<div style="background: #fff5f5; padding: 25px; border-radius: 12px; border-left: 4px solid #fc8181; margin: 25px 0;">
    <h3 style="color: #742a2a; margin: 0 0 15px; font-size: 18px;">💡 Pro Tip: Learn from the Best</h3>
    <p style="color: #4a5568; margin: 0 0 15px;">
        The most successful DeFi protocols didn't just launch and hope for the best. They:
    </p>
    <ol style="color: #4a5568; margin: 0; padding-left: 20px;">
        <li><strong>Analyzed competitors:</strong> Study successful protocols like Uniswap and Aave</li>
        <li><strong>Learned from exploits:</strong> Understand what went wrong in major hacks</li>
        <li><strong>Iterated quickly:</strong> Test, analyze, improve, repeat</li>
        <li><strong>Built security-first:</strong> Made security a core feature, not an afterthought</li>
    </ol>
</div>

<div style="background: #f7fafc; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; margin: 25px 0;">
    <h3 style="color: #2d3748; margin: 0 0 15px;">🎯 Quick Actions</h3>
    <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <a href="{{ $analyzeUrl }}" style="background: #4299e1; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">
            🔍 Analyze Another Contract
        </a>
        <a href="{{ $dashboardUrl }}" style="background: #48bb78; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">
            📊 View Dashboard
        </a>
        <a href="{{ $tutorialsUrl }}" style="background: #9f7aea; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">
            📚 Learn More
        </a>
    </div>
</div>

<p style="color: #718096; font-size: 14px; margin-top: 30px; text-align: center;">
    Questions about your analysis results? Reply to this email - our security experts are here to help! 🛡️
</p>
@endsection
