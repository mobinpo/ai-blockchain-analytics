<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Limited</title>
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

        .warning-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #F59E0B;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .warning-icon svg {
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

        .rate-limit-info {
            background: #FEF3C7;
            border: 1px solid #FDE68A;
            border-radius: 12px;
            padding: 20px;
            margin: 30px 0;
        }

        .rate-limit-info h3 {
            color: #92400E;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .countdown {
            font-size: 24px;
            font-weight: 700;
            color: #F59E0B;
            margin: 16px 0;
        }

        .explanation {
            color: #6B7280;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .actions {
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            font-size: 14px;
            margin: 0 8px;
        }

        .btn-primary {
            background: #3B82F6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563EB;
        }

        .btn-primary:disabled {
            background: #9CA3AF;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #F3F4F6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #E5E7EB;
        }

        @media (max-width: 480px) {
            .container {
                padding: 24px;
            }
            
            .btn {
                display: block;
                width: 100%;
                margin: 8px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="warning-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.08 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
        </div>

        <h1>Too Many Attempts</h1>
        
        <p class="subtitle">
            You've made too many verification attempts. Please wait before trying again.
        </p>

        <div class="rate-limit-info">
            <h3>Time Remaining</h3>
            <div class="countdown" id="countdown">
                <span id="minutes">--</span>:<span id="seconds">--</span>
            </div>
            <div class="explanation">
                This security measure helps protect against automated attacks and ensures fair access for all users.
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-secondary" onclick="history.back()">
                Go Back
            </button>
            <button class="btn btn-primary" id="retryBtn" disabled onclick="location.reload()">
                Try Again
            </button>
        </div>
    </div>

    <script>
        let retryAfter = {{ $retry_after ?? 0 }};
        
        function updateCountdown() {
            if (retryAfter <= 0) {
                document.getElementById('countdown').textContent = 'Ready!';
                document.getElementById('retryBtn').disabled = false;
                document.getElementById('retryBtn').textContent = 'Try Again Now';
                return;
            }

            const minutes = Math.floor(retryAfter / 60);
            const seconds = retryAfter % 60;
            
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
            
            retryAfter--;
            setTimeout(updateCountdown, 1000);
        }

        // Start countdown
        updateCountdown();
    </script>
</body>
</html>