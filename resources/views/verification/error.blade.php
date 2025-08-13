<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Failed</title>
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

        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #EF4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-icon svg {
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

        .error-details {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            border-radius: 12px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }

        .error-details h3 {
            color: #991B1B;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .error-message {
            color: #7F1D1D;
            font-size: 14px;
            line-height: 1.5;
        }

        .common-issues {
            background: #F9FAFB;
            border-radius: 12px;
            padding: 24px;
            margin: 30px 0;
            text-align: left;
        }

        .common-issues h3 {
            color: #374151;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .common-issues ul {
            list-style: none;
        }

        .common-issues li {
            color: #6B7280;
            font-size: 14px;
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .common-issues li::before {
            content: '•';
            color: #EF4444;
            position: absolute;
            left: 0;
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

        .btn-secondary {
            background: #F3F4F6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #E5E7EB;
        }

        .help-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
            color: #6B7280;
            font-size: 14px;
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
        <div class="error-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </div>

        <h1>Verification Failed</h1>
        
        <p class="subtitle">
            The verification link could not be processed. Please check the details below.
        </p>

        <div class="error-details">
            <h3>Error Details</h3>
            <div class="error-message">
                {{ $error ?? 'An unknown error occurred during verification.' }}
            </div>
        </div>

        <div class="common-issues">
            <h3>Common Issues</h3>
            <ul>
                <li>The verification link may have expired (links are valid for 1 hour)</li>
                <li>The link may have been tampered with or corrupted</li>
                <li>You may have already used this verification link</li>
                <li>The contract may have already been verified by another user</li>
                <li>There may be a temporary server issue</li>
            </ul>
        </div>

        <div class="actions">
            <button class="btn btn-secondary" onclick="history.back()">
                Go Back
            </button>
            <button class="btn btn-primary" onclick="requestNewLink()">
                Request New Link
            </button>
        </div>

        <div class="help-section">
            <strong>Need Help?</strong><br>
            If you continue to experience issues, please contact support with the error details above.
        </div>
    </div>

    <script>
        function requestNewLink() {
            // In a real implementation, this would redirect to a form
            // or make an API call to request a new verification link
            alert('Please contact the contract owner to request a new verification link.');
        }
    </script>
</body>
</html>