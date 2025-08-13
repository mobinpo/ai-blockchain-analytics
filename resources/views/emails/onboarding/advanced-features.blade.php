<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>🚀 Unlock powerful advanced features!</title>
    <style>
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
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
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 20px 0;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }
        .feature-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            position: relative;
        }
        .feature-badge {
            position: absolute;
            top: -12px;
            left: 25px;
            background: #8b5cf6;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .comparison-table {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin: 20px 0;
        }
        .comparison-header {
            background: #f1f5f9;
            padding: 15px;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .comparison-row {
            display: flex;
            border-bottom: 1px solid #f1f5f9;
        }
        .comparison-cell {
            flex: 1;
            padding: 12px 15px;
            text-align: center;
            font-size: 14px;
        }
        .comparison-cell:first-child {
            text-align: left;
            background: #fafafa;
            font-weight: 500;
        }
        .check-mark {
            color: #10b981;
            font-weight: bold;
        }
        .x-mark {
            color: #ef4444;
            font-weight: bold;
        }
        .pro-highlight {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }
        .api-code {
            background: #1f2937;
            color: #e5e7eb;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 15px 0;
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
            <h1>🚀 Power User Features</h1>
            <p>Take your smart contract analysis to the next level</p>
        </div>

        <div class="content">
            <h2>Hey {{ $user->name ?? 'there' }}! 🎯</h2>
            
            <p>You've been using AI Blockchain Analytics for a few days now, and we're excited to show you some powerful features that can supercharge your workflow!</p>

            <div class="feature-card">
                <div class="feature-badge">ADVANCED</div>
                <h3 style="color: #1f2937; margin: 15px 0 10px 0;">📊 Batch Analysis</h3>
                <p style="margin: 0; color: #4b5563;">Analyze multiple contracts simultaneously and compare results side-by-side. Perfect for auditing entire DeFi protocols or NFT collections.</p>
            </div>

            <div class="feature-card">
                <div class="feature-badge">API ACCESS</div>
                <h3 style="color: #1f2937; margin: 15px 0 10px 0;">🔌 REST API Integration</h3>
                <p style="margin: 0 0 15px 0; color: #4b5563;">Integrate security analysis directly into your CI/CD pipeline:</p>
                <div class="api-code">
curl -X POST https://api.blockchain-analytics.com/v1/analyze \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "contract_address": "0x...",
    "network": "ethereum",
    "analysis_type": "comprehensive"
  }'</div>
            </div>

            <div class="feature-card">
                <div class="feature-badge">COLLABORATION</div>
                <h3 style="color: #1f2937; margin: 15px 0 10px 0;">👥 Team Workspaces</h3>
                <p style="margin: 0; color: #4b5563;">Share analyses with your team, assign reviews, and track remediation progress. Built for security teams and development organizations.</p>
            </div>

            <div class="feature-card">
                <div class="feature-badge">MONITORING</div>
                <h3 style="color: #1f2937; margin: 15px 0 10px 0;">🔔 Real-time Alerts</h3>
                <p style="margin: 0; color: #4b5563;">Get notified when contracts in your portfolio are upgraded, when new vulnerabilities are discovered, or when unusual activity is detected.</p>
            </div>

            <div class="pro-highlight">
                <h3 style="color: #92400e; margin: 0 0 15px 0;">✨ Professional vs. Free Comparison</h3>
                <div class="comparison-table">
                    <div class="comparison-header" style="display: flex;">
                        <div style="flex: 1; text-align: left; padding-left: 20px;">Feature</div>
                        <div style="flex: 1;">Free</div>
                        <div style="flex: 1;">Professional</div>
                    </div>
                    <div class="comparison-row">
                        <div class="comparison-cell">Basic Security Analysis</div>
                        <div class="comparison-cell"><span class="check-mark">✓</span></div>
                        <div class="comparison-cell"><span class="check-mark">✓</span></div>
                    </div>
                    <div class="comparison-row">
                        <div class="comparison-cell">PDF Reports</div>
                        <div class="comparison-cell"><span class="check-mark">✓</span></div>
                        <div class="comparison-cell"><span class="check-mark">✓</span></div>
                    </div>
                    <div class="comparison-row">
                        <div class="comparison-cell">Batch Analysis</div>
                        <div class="comparison-cell"><span class="x-mark">✗</span></div>
                        <div class="comparison-cell"><span class="check-mark">✓</span></div>
                    </div>
                    <div class="comparison-row">
                        <div class="comparison-cell">API Access</div>
                        <div class="comparison-cell"><span class="x-mark">✗</span></div>
                        <div class="comparison-cell"><span class="check-mark">✓</span></div>
                    </div>
                    <div class="comparison-row">
                        <div class="comparison-cell">Team Collaboration</div>
                        <div class="comparison-cell"><span class="x-mark">✗</span></div>
                        <div class="comparison-cell"><span class="check-mark">✓</span></div>
                    </div>
                    <div class="comparison-row">
                        <div class="comparison-cell">Real-time Monitoring</div>
                        <div class="comparison-cell"><span class="x-mark">✗</span></div>
                        <div class="comparison-cell"><span class="check-mark">✓</span></div>
                    </div>
                    <div class="comparison-row">
                        <div class="comparison-cell">Priority Support</div>
                        <div class="comparison-cell"><span class="x-mark">✗</span></div>
                        <div class="comparison-cell"><span class="check-mark">✓</span></div>
                    </div>
                </div>
            </div>

            <h3>🎯 Use Cases That Love These Features</h3>
            <ul>
                <li><strong>🏢 DeFi Teams:</strong> Monitor your entire protocol suite with automated alerts</li>
                <li><strong>🛡️ Security Auditors:</strong> Batch process multiple client contracts efficiently</li>
                <li><strong>👩‍💻 Developers:</strong> Integrate analysis into your deployment pipeline</li>
                <li><strong>💼 Enterprise:</strong> Collaborative security reviews with team workspaces</li>
            </ul>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $pricingUrl ?? ($dashboardUrl . '/pricing') }}" class="cta-button">
                    🚀 Upgrade to Professional
                </a>
            </div>

            <div style="background: #e0f2fe; border: 1px solid #0284c7; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="color: #0c4a6e; margin: 0 0 10px 0;">🎁 Special Offer</h4>
                <p style="margin: 0; color: #0c4a6e;">
                    <strong>50% off your first month</strong> of Professional features! 
                    Use code <strong>POWERUSER50</strong> at checkout.
                    <br><em>Valid for the next 48 hours.</em>
                </p>
            </div>

            <p>Want to see these features in action? <a href="{{ $demoUrl ?? ($dashboardUrl . '/demo') }}" style="color: #8b5cf6;">Book a 15-minute demo</a> with our team!</p>

            <p>Questions about Professional features? Just reply to this email! 💪</p>
        </div>

        <div class="footer">
            <p><strong>Build with Confidence! 🔐</strong></p>
            <p>The AI Blockchain Analytics Team</p>
            
            <p style="margin-top: 30px;">
                <a href="{{ $supportUrl }}">Get Help</a> | 
                <a href="{{ $docsUrl }}">API Docs</a> | 
                <a href="{{ $pricingUrl ?? ($dashboardUrl . '/pricing') }}">View Pricing</a>
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