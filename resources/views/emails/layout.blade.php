<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'AI Blockchain Analytics' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 40px 30px;
        }
        .content h2 {
            color: #2d3748;
            margin-bottom: 20px;
            font-size: 22px;
        }
        .content p {
            margin-bottom: 16px;
            font-size: 16px;
            line-height: 1.7;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            transition: transform 0.2s ease;
        }
        .cta-button:hover {
            transform: translateY(-2px);
        }
        .feature-box {
            border-left: 4px solid #667eea;
            padding: 20px;
            background-color: #f8fafc;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .feature-box h3 {
            color: #2d3748;
            margin-top: 0;
            font-size: 18px;
        }
        .stats-container {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
            text-align: center;
        }
        .stat-item {
            flex: 1;
            padding: 0 10px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            display: block;
        }
        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-top: 5px;
        }
        .footer {
            background-color: #2d3748;
            color: white;
            text-align: center;
            padding: 30px 20px;
        }
        .footer p {
            margin: 5px 0;
            font-size: 14px;
            opacity: 0.8;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        .social-links {
            margin: 20px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #667eea;
            font-size: 18px;
            text-decoration: none;
        }
        .unsubscribe {
            margin-top: 20px;
            font-size: 12px;
            opacity: 0.6;
        }
        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                box-shadow: none;
            }
            .content {
                padding: 30px 20px;
            }
            .stats-container {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>🔍 AI Blockchain Analytics</h1>
            <p>Smart Contract Security & Analysis Platform</p>
        </div>
        
        <div class="content">
            @yield('content')
        </div>
        
        <div class="footer">
            <div class="social-links">
                <a href="https://twitter.com/sentimentshield" title="Twitter" target="_blank" rel="noopener">🐦</a>
                <a href="https://github.com/mobinpo/ai-blockchain-analytics" title="GitHub" target="_blank" rel="noopener">🐱</a>
                <a href="https://discord.gg/sentimentshield" title="Discord" target="_blank" rel="noopener">💬</a>
            </div>
            <p>&copy; {{ date('Y') }} AI Blockchain Analytics. All rights reserved.</p>
            <p>
                <a href="{{ $dashboardUrl ?? '#' }}">Dashboard</a> | 
                <a href="{{ $supportUrl ?? '#' }}">Support</a> | 
                <a href="{{ $docsUrl ?? '#' }}">Documentation</a>
            </p>
            @if(isset($unsubscribeUrl))
            <div class="unsubscribe">
                <p>Don't want to receive these emails? <a href="{{ $unsubscribeUrl }}">Unsubscribe here</a></p>
            </div>
            @endif
        </div>
    </div>
</body>
</html>