<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Insights: Learn from Real DeFi Exploits</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .header { background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); padding: 40px 30px; text-align: center; }
        .header h1 { color: white; font-size: 28px; margin: 0; font-weight: 700; }
        .header p { color: #FEE2E2; font-size: 16px; margin: 10px 0 0 0; }
        .content { padding: 40px 30px; }
        .exploit-case { background: #FEF2F2; border-radius: 12px; padding: 24px; margin: 24px 0; border-left: 4px solid #DC2626; }
        .exploit-header { display: flex; align-items: center; margin-bottom: 16px; }
        .exploit-icon { font-size: 32px; margin-right: 12px; }
        .exploit-title { font-size: 20px; font-weight: bold; color: #991B1B; margin: 0; }
        .exploit-amount { color: #DC2626; font-size: 18px; font-weight: bold; }
        .prevention-box { background: #F0FDF4; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #10B981; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); color: white; text-decoration: none; padding: 16px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 24px 0; }
        .stat-card { background: #F8FAFC; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid #E5E7EB; }
        .stat-number { font-size: 28px; font-weight: bold; color: #DC2626; }
        .stat-label { font-size: 14px; color: #6B7280; margin-top: 4px; }
        .footer { background: #1F2937; color: #9CA3AF; padding: 30px; text-align: center; font-size: 14px; }
        .footer a { color: #60A5FA; text-decoration: none; }
        .vulnerability-list { margin: 20px 0; }
        .vulnerability-item { background: white; border-radius: 8px; padding: 16px; margin: 12px 0; border-left: 4px solid #F59E0B; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .severity-critical { border-left-color: #DC2626; }
        .severity-high { border-left-color: #EA580C; }
        .severity-medium { border-left-color: #F59E0B; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🔒 Security Insights</h1>
            <p>Learn from Real DeFi Exploits - $570M+ Analyzed</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p>Hi {{ $user->name ?? 'there' }}! 👋</p>
            
            <p>The blockchain industry has lost over <strong>$3.8 billion</strong> to smart contract exploits in 2023 alone. Today, let's dive deep into some of the most significant exploits and learn how to prevent them.</p>

            <!-- Industry Impact Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">$3.8B</div>
                    <div class="stat-label">Lost in 2023</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">463</div>
                    <div class="stat-label">Major Exploits</div>
                </div>
            </div>

            <!-- Featured Exploit Case Study: BSC Token Hub -->
            <div class="exploit-case">
                <div class="exploit-header">
                    <div class="exploit-icon">💥</div>
                    <div>
                        <h3 class="exploit-title">BSC Token Hub Bridge Exploit</h3>
                        <div class="exploit-amount">$570+ Million Stolen</div>
                    </div>
                </div>
                
                <p><strong>Date:</strong> October 7, 2022</p>
                <p><strong>Root Cause:</strong> Merkle proof verification vulnerability</p>
                
                <h4 style="color: #991B1B;">How It Happened:</h4>
                <ol style="color: #7C2D12; line-height: 1.6;">
                    <li>Attacker exploited weak merkle proof verification in the cross-chain bridge</li>
                    <li>Created fake withdrawal proofs using forged IAVL merkle proofs</li>
                    <li>Drained over 2 million BNB (~$570M) from the bridge contract</li>
                    <li>BNB Chain was halted within hours to prevent further damage</li>
                </ol>

                <div class="prevention-box">
                    <h4 style="color: #065F46; margin: 0 0 12px 0;">🛡️ How Our Analysis Would Have Caught This:</h4>
                    <ul style="color: #064E3B; margin: 0; line-height: 1.6;">
                        <li><strong>Cross-chain Validation:</strong> Detects insufficient proof verification</li>
                        <li><strong>Merkle Tree Analysis:</strong> Identifies weak cryptographic implementations</li>
                        <li><strong>Bridge Security Patterns:</strong> Flags common bridge vulnerabilities</li>
                        <li><strong>Access Control Review:</strong> Ensures proper validation mechanisms</li>
                    </ul>
                </div>
            </div>

            <!-- Featured Exploit Case Study: Euler Finance -->
            <div class="exploit-case">
                <div class="exploit-header">
                    <div class="exploit-icon">🚨</div>
                    <div>
                        <h3 class="exploit-title">Euler Finance Donation Attack</h3>
                        <div class="exploit-amount">$197 Million Stolen</div>
                    </div>
                </div>
                
                <p><strong>Date:</strong> March 13, 2023</p>
                <p><strong>Root Cause:</strong> Liquidation mechanism vulnerability</p>
                
                <h4 style="color: #991B1B;">The Attack Vector:</h4>
                <ol style="color: #7C2D12; line-height: 1.6;">
                    <li>Attacker used flash loans to manipulate account health scores</li>
                    <li>Exploited donation attack to inflate liquidation discount calculations</li>
                    <li>Self-liquidated positions for profit due to flawed health calculations</li>
                    <li>Repeated the process to drain protocol reserves</li>
                </ol>

                <div class="prevention-box">
                    <h4 style="color: #065F46; margin: 0 0 12px 0;">🛡️ Prevention Strategies:</h4>
                    <ul style="color: #064E3B; margin: 0; line-height: 1.6;">
                        <li><strong>Health Score Validation:</strong> Prevent manipulation through donations</li>
                        <li><strong>Liquidation Logic Review:</strong> Ensure discount calculations are bulletproof</li>
                        <li><strong>Flash Loan Protection:</strong> Implement proper reentrancy guards</li>
                        <li><strong>Economic Model Analysis:</strong> Test edge cases in liquidation mechanisms</li>
                    </ul>
                </div>
            </div>

            <!-- Common Vulnerability Patterns -->
            <h3 style="color: #1F2937; margin: 30px 0 20px 0;">🎯 Most Critical Vulnerability Patterns We Detect:</h3>
            
            <div class="vulnerability-list">
                <div class="vulnerability-item severity-critical">
                    <h4 style="margin: 0 0 8px 0; color: #991B1B;">🚨 Reentrancy Attacks</h4>
                    <p style="margin: 0; color: #7C2D12; font-size: 14px;">
                        <strong>$180M+ stolen</strong> - Functions that call external contracts without proper guards
                    </p>
                </div>
                
                <div class="vulnerability-item severity-critical">
                    <h4 style="margin: 0 0 8px 0; color: #991B1B;">💰 Flash Loan Manipulations</h4>
                    <p style="margin: 0; color: #7C2D12; font-size: 14px;">
                        <strong>$320M+ stolen</strong> - Price oracle manipulation through flash loans
                    </p>
                </div>
                
                <div class="vulnerability-item severity-high">
                    <h4 style="margin: 0 0 8px 0; color: #C2410C;">🔐 Access Control Issues</h4>
                    <p style="margin: 0; color: #9A3412; font-size: 14px;">
                        <strong>$95M+ stolen</strong> - Missing or improper permission checks
                    </p>
                </div>
                
                <div class="vulnerability-item severity-medium">
                    <h4 style="margin: 0 0 8px 0; color: #A16207;">⚖️ Integer Overflow/Underflow</h4>
                    <p style="margin: 0; color: #92400E; font-size: 14px;">
                        <strong>$45M+ stolen</strong> - Arithmetic operations without proper bounds checking
                    </p>
                </div>
            </div>

            <!-- Your Security Checklist -->
            <div style="background: #F0F9FF; border-radius: 12px; padding: 24px; margin: 30px 0;">
                <h3 style="color: #1E40AF; margin: 0 0 16px 0;">✅ Your Smart Contract Security Checklist:</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 14px;">
                    <div style="color: #1E3A8A;">☑️ Reentrancy guards implemented</div>
                    <div style="color: #1E3A8A;">☑️ Access controls properly configured</div>
                    <div style="color: #1E3A8A;">☑️ Integer overflow protection</div>
                    <div style="color: #1E3A8A;">☑️ External call validations</div>
                    <div style="color: #1E3A8A;">☑️ Oracle price manipulation protection</div>
                    <div style="color: #1E3A8A;">☑️ Flash loan attack mitigation</div>
                    <div style="color: #1E3A8A;">☑️ Proper event emission</div>
                    <div style="color: #1E3A8A;">☑️ Gas optimization implemented</div>
                </div>
            </div>

            <!-- CTA -->
            <div style="text-align: center; margin: 40px 0;">
                <h3 style="color: #1F2937;">🔍 Analyze Your Contract's Security</h3>
                <p style="color: #6B7280; margin-bottom: 24px;">
                    Don't wait for an exploit to discover vulnerabilities. Get a comprehensive security analysis today.
                </p>
                <a href="{{ $analyzeUrl }}#live-analyzer" class="cta-button">
                    🛡️ Analyze My Contract Now
                </a>
            </div>

            <!-- Educational Resources -->
            <div style="background: #FFFBEB; border-radius: 8px; padding: 20px; margin: 24px 0; border-left: 4px solid #F59E0B;">
                <h4 style="color: #92400E; margin: 0 0 12px 0;">📚 Deep Dive Resources:</h4>
                <ul style="color: #78350F; margin: 0; line-height: 1.6;">
                    <li><a href="{{ $docsUrl }}/security-guide" style="color: #92400E;">Complete Smart Contract Security Guide</a></li>
                    <li><a href="{{ $docsUrl }}/exploit-database" style="color: #92400E;">Historical Exploit Database</a></li>
                    <li><a href="{{ $docsUrl }}/best-practices" style="color: #92400E;">Development Best Practices</a></li>
                    <li><a href="{{ $communityUrl }}" style="color: #92400E;">Join Our Security Community</a></li>
                </ul>
            </div>

            <!-- Coming Next -->
            <div style="background: #F0F9FF; border-radius: 8px; padding: 20px; margin: 24px 0;">
                <h4 style="color: #1E40AF; margin: 0 0 12px 0;">🔮 Coming Next...</h4>
                <p style="color: #1E3A8A; margin: 0; font-size: 14px;">
                    Tomorrow we'll explore advanced features like gas optimization, multi-chain analysis, 
                    and automated monitoring. Plus, we'll show you how to set up continuous security monitoring for your protocols! 🚀
                </p>
            </div>

            <p style="color: #6B7280; font-size: 14px; margin-top: 30px;">
                Stay secure! 🛡️<br>
                The AI Blockchain Analytics Security Team
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
