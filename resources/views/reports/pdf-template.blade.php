<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $component ?? 'Report' }} - PDF Export</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
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
            font-size: 14px;
        }
        
        .pdf-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .pdf-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 12px;
        }
        
        .pdf-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .pdf-header .subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        @media print {
            .pdf-header {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            body {
                font-size: 12px;
            }
            
            .pdf-container {
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="pdf-container">
        <div class="pdf-header">
            <h1>{{ \Illuminate\Support\Str::title(str_replace(['_', '-'], ' ', $component ?? 'Report')) }}</h1>
            <div class="subtitle">Generated on {{ now()->format('F j, Y \a\t g:i A') }}</div>
        </div>
        
        <div class="pdf-content">
            {!! $html_content ?? '<p>No content provided for PDF generation.</p>' !!}
        </div>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 12px;">
            <p><strong>Blockchain Analytics Platform</strong> | Generated {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>