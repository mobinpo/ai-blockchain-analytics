<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Generation Error</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background: #ffffff;
            padding: 40px 20px;
        }
        
        .error-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .error-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }
        
        .error-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .error-header .subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .error-content {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-top: none;
            padding: 30px;
            border-radius: 0 0 12px 12px;
        }
        
        .error-details {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #f3f4f6;
            margin-bottom: 20px;
        }
        
        .error-details h3 {
            color: #dc2626;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .error-details p {
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .error-details strong {
            color: #374151;
            font-weight: 600;
        }
        
        .troubleshooting {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 20px;
            border-radius: 8px;
        }
        
        .troubleshooting h3 {
            color: #1d4ed8;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .troubleshooting ul {
            list-style-type: none;
            padding-left: 0;
        }
        
        .troubleshooting li {
            margin-bottom: 8px;
            font-size: 14px;
            position: relative;
            padding-left: 20px;
        }
        
        .troubleshooting li:before {
            content: "•";
            color: #1d4ed8;
            font-weight: bold;
            position: absolute;
            left: 0;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        @media print {
            .error-header, .troubleshooting {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <h1>📄 PDF Generation Error</h1>
            <div class="subtitle">Unable to generate PDF document</div>
        </div>
        
        <div class="error-content">
            <div class="error-details">
                <h3>Error Details</h3>
                <p><strong>Component:</strong> {{ $component ?? 'Unknown' }}</p>
                <p><strong>Error Message:</strong> {{ $error ?? 'No error message provided' }}</p>
                <p><strong>Timestamp:</strong> {{ now()->format('F j, Y \a\t g:i:s A') }}</p>
                <p><strong>Request ID:</strong> {{ \Illuminate\Support\Str::random(8) }}</p>
            </div>
            
            <div class="troubleshooting">
                <h3>🔧 Troubleshooting Steps</h3>
                <ul>
                    <li>Verify that the component exists and is properly configured</li>
                    <li>Check that demo data is available for this component type</li>
                    <li>Ensure the PDF generation service is running correctly</li>
                    <li>Try refreshing the page and attempting the download again</li>
                    <li>Contact system administrator if the issue persists</li>
                </ul>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Blockchain Analytics Platform</strong></p>
            <p>Error reported at {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
    
    {!! $html_content ?? '' !!}
</body>
</html>