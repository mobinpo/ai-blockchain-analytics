<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Verified Successfully</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #10B981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-icon svg {
            width: 40px;
            height: 40px;
            stroke: white;
            stroke-width: 3;
        }

        h1 {
            color: #1F2937;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #6B7280;
            font-size: 16px;
            margin-bottom: 30px;
        }

        .contract-info {
            background: #F9FAFB;
            border-radius: 12px;
            padding: 24px;
            margin: 30px 0;
            text-align: left;
        }

        .contract-info h3 {
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .contract-address {
            font-family: 'Monaco', 'Menlo', monospace;
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
            font-size: 14px;
            color: #1F2937;
            word-break: break-all;
        }

        .verification-time {
            color: #6B7280;
            font-size: 14px;
            margin-top: 8px;
        }

        .badge-preview {
            margin: 20px 0;
            padding: 20px;
            background: #F9FAFB;
            border-radius: 12px;
        }

        .badge-preview h3 {
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        {!! $this->verificationService->getBadgeCSS() !!}

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }

        .btn-primary {
            background: #3B82F6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563EB;
        }

        .btn-secondary {
            background: #F3F4F6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #E5E7EB;
        }

        .copy-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10B981;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s;
        }

        .copy-notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 480px) {
            .container {
                padding: 24px;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h1>
            @if($already_verified ?? false)
                Already Verified!
            @else
                Successfully Verified!
            @endif
        </h1>
        
        <p class="subtitle">
            @if($already_verified ?? false)
                This contract has already been verified and is displaying the verified badge.
            @else
                Your smart contract has been cryptographically verified and is now displaying the verified badge.
            @endif
        </p>

        <div class="contract-info">
            <h3>Contract Address</h3>
            <div class="contract-address" id="contractAddress">{{ $contract_address }}</div>
            
            @if($verified_at ?? false)
            <div class="verification-time">
                Verified on {{ $verified_at }}
            </div>
            @endif
        </div>

        @if($badge_html ?? false)
        <div class="badge-preview">
            <h3>Your Verification Badge</h3>
            {!! $badge_html !!}
        </div>
        @endif

        <div class="actions">
            <button class="btn btn-secondary" onclick="copyAddress()">
                Copy Address
            </button>
            <button class="btn btn-primary" onclick="copyBadgeCode()">
                Copy Badge Code
            </button>
        </div>

        <div style="margin-top: 20px; color: #6B7280; font-size: 14px;">
            <strong>How to use:</strong> Copy the badge code and embed it in your website, documentation, or DApp to display the verification status.
        </div>
    </div>

    <div class="copy-notification" id="copyNotification">
        Copied to clipboard!
    </div>

    <script>
        function copyAddress() {
            const address = document.getElementById('contractAddress').textContent;
            navigator.clipboard.writeText(address).then(() => {
                showNotification('Contract address copied!');
            });
        }

        function copyBadgeCode() {
            const badgeHtml = `{!! addslashes($badge_html ?? '') !!}`;
            navigator.clipboard.writeText(badgeHtml).then(() => {
                showNotification('Badge code copied!');
            });
        }

        function showNotification(message) {
            const notification = document.getElementById('copyNotification');
            notification.textContent = message;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>