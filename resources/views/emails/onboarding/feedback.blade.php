<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>💭 How has your experience been so far?</title>
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
        .secondary-button {
            display: inline-block;
            background: white;
            color: #10b981;
            border: 2px solid #10b981;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 10px 10px;
            font-size: 16px;
        }
        .stats-summary {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }
        .stat-item {
            display: inline-block;
            margin: 0 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #059669;
            display: block;
        }
        .stat-label {
            font-size: 14px;
            color: #065f46;
            margin-top: 5px;
        }
        .testimonial-box {
            background: #f8fafc;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
            font-style: italic;
        }
        .feature-request {
            background: #fffbeb;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .quick-feedback {
            text-align: center;
            margin: 30px 0;
        }
        .rating-button {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 25px;
            margin: 5px;
            font-size: 18px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .rating-button:hover {
            background: #10b981;
            color: white;
            border-color: #059669;
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
            <h1>💭 We'd Love Your Feedback</h1>
            <p>Help us make smart contract security even better</p>
        </div>

        <div class="content">
            <h2>Hi {{ $user->name ?? 'there' }}! 👋</h2>
            
            <p>It's been a week since you joined AI Blockchain Analytics, and we hope you've had a chance to explore our smart contract security tools!</p>

            @if(isset($userStats))
            <div class="stats-summary">
                <h3>Your Journey So Far 📊</h3>
                <div style="margin: 20px 0;">
                    <div class="stat-item">
                        <span class="stat-number">{{ $userStats['analysesCount'] ?? 0 }}</span>
                        <div class="stat-label">Analyses Complete</div>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">{{ $userStats['vulnerabilitiesFound'] ?? 0 }}</span>
                        <div class="stat-label">Vulnerabilities Found</div>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">{{ $userStats['gasOptimizations'] ?? 0 }}</span>
                        <div class="stat-label">Gas Optimizations</div>
                    </div>
                </div>
            </div>
            @endif

            <p>Your experience matters tremendously to us. Whether you've found our platform incredibly useful or encountered challenges, we want to hear about it!</p>

            <div class="quick-feedback">
                <h3>Quick Rating ⭐</h3>
                <p style="margin: 10px 0 20px 0;">How would you rate your overall experience so far?</p>
                <a href="{{ $feedbackUrl ?? $supportUrl }}?rating=5&user={{ $user->id ?? '' }}" class="rating-button">🌟 Excellent</a>
                <a href="{{ $feedbackUrl ?? $supportUrl }}?rating=4&user={{ $user->id ?? '' }}" class="rating-button">👍 Good</a>
                <a href="{{ $feedbackUrl ?? $supportUrl }}?rating=3&user={{ $user->id ?? '' }}" class="rating-button">👌 Okay</a>
                <a href="{{ $feedbackUrl ?? $supportUrl }}?rating=2&user={{ $user->id ?? '' }}" class="rating-button">👎 Poor</a>
            </div>

            <h3>📝 Help Us Improve</h3>
            <p>We'd love to know more about your experience:</p>

            <ul>
                <li><strong>What do you love most</strong> about our platform?</li>
                <li><strong>What challenges</strong> have you encountered?</li>
                <li><strong>What features</strong> would you like to see next?</li>
                <li><strong>How can we</strong> make your security workflow better?</li>
            </ul>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $surveyUrl ?? $supportUrl }}" class="cta-button">
                    📋 Take 2-Minute Survey
                </a>
                <br>
                <a href="{{ $callUrl ?? $supportUrl }}" class="secondary-button">
                    📞 Schedule 15-Min Call
                </a>
            </div>

            <div class="testimonial-box">
                <p>"The vulnerability detection caught a critical reentrancy issue in our DeFi protocol that we completely missed. Saved us from a potential exploit!"</p>
                <p style="margin: 10px 0 0 0; font-weight: bold; font-style: normal;">— Sarah K., Smart Contract Developer</p>
            </div>

            <div class="feature-request">
                <h4 style="color: #92400e; margin: 0 0 15px 0;">🚀 What's Coming Next</h4>
                <p style="margin: 0; color: #92400e;">Based on user feedback, we're working on:</p>
                <ul style="color: #92400e;">
                    <li><strong>Real-time monitoring</strong> for deployed contracts</li>
                    <li><strong>Integration with GitHub</strong> for automated PR checks</li>
                    <li><strong>Advanced gas optimization</strong> suggestions</li>
                    <li><strong>Team collaboration</strong> features</li>
                </ul>
            </div>

            <h3>🎁 Thank You Gift</h3>
            <p>As a token of appreciation for your feedback, everyone who completes our survey gets:</p>
            <ul>
                <li>🆓 <strong>One month free</strong> of Professional features</li>
                <li>📚 <strong>Exclusive access</strong> to our Smart Contract Security Guide</li>
                <li>🎯 <strong>Priority support</strong> for any questions</li>
                <li>👥 <strong>Early access</strong> to new features</li>
            </ul>

            <div style="background: #e0f2fe; border: 1px solid #0284c7; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="color: #0c4a6e; margin: 0 0 10px 0;">💡 Pro Tip</h4>
                <p style="margin: 0; color: #0c4a6e;">
                    Your feedback directly influences our roadmap. Many of our most popular features came from user suggestions!
                </p>
            </div>

            <p>Have specific questions or need help with anything? Just reply to this email - I personally read every response! 💪</p>

            <p style="margin-top: 30px;">
                Thanks for being part of our community and helping make smart contracts more secure! 🛡️
            </p>
        </div>

        <div class="footer">
            <p><strong>Building Together! 🤝</strong></p>
            <p>The AI Blockchain Analytics Team</p>
            <p style="margin-top: 15px; font-style: italic;">P.S. Seriously, reply to this email if you have any thoughts - we love hearing from our users!</p>
            
            <p style="margin-top: 30px;">
                <a href="{{ $supportUrl }}">Contact Support</a> | 
                <a href="{{ $communityUrl ?? $supportUrl }}">Join Community</a> | 
                <a href="{{ $docsUrl }}">Documentation</a>
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