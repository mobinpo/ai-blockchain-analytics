<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Preview Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 2rem;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-icon svg {
            width: 40px;
            height: 40px;
            color: #ef4444;
        }
        
        h1 {
            color: #1f2937;
            margin: 0 0 1rem;
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .error-message {
            color: #6b7280;
            margin: 0 0 2rem;
            font-size: 1.125rem;
            line-height: 1.6;
        }
        
        .error-details {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: left;
        }
        
        .error-details strong {
            color: #374151;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .error-details code {
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.875rem;
            color: #ef4444;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }
        
        .troubleshooting {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
            text-align: left;
        }
        
        .troubleshooting h3 {
            color: #374151;
            margin: 0 0 1rem;
            font-size: 1.125rem;
        }
        
        .troubleshooting ul {
            color: #6b7280;
            padding-left: 1.5rem;
            margin: 0;
        }
        
        .troubleshooting li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <svg fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
        </div>
        
        <h1>PDF Preview Error</h1>
        
        <p class="error-message">
            Sorry, we couldn't generate the PDF preview for this component. This usually happens when the preview is accessed without a valid token.
        </p>
        
        <div class="error-details">
            <strong>Component:</strong> <code>{{ $component }}</code><br>
            <strong>Error:</strong> <code>{{ $error }}</code><br>
            <strong>Time:</strong> {{ now()->format('Y-m-d H:i:s') }}
        </div>
        
        <div class="action-buttons">
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                </svg>
                Back to Dashboard
            </a>
            <button onclick="history.back()" class="btn btn-secondary">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0L2.586 11l3.707-3.707a1 1 0 011.414 1.414L5.414 11l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                </svg>
                Go Back
            </button>
        </div>
        
        <div class="troubleshooting">
            <h3>🔧 Troubleshooting</h3>
            <ul>
                <li>Make sure you're accessing the PDF preview through the proper generation workflow</li>
                <li>Check that your session hasn't expired</li>
                <li>Try generating a new PDF from the dashboard</li>
                <li>If the problem persists, contact support with the error code above</li>
            </ul>
        </div>
    </div>
</body>
</html>