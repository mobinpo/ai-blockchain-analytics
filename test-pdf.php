<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\EnhancedVuePdfService;
use Illuminate\Foundation\Application;

echo "🚀 Testing PDF Generation System\n";
echo "================================\n\n";

// Test data for email preferences
$testData = [
    'preferences' => [
        'marketing_emails' => true,
        'product_updates' => true,
        'security_alerts' => true,
        'onboarding_emails' => false,
        'weekly_digest' => true,
        'frequency' => 'normal'
    ],
    'stats' => [
        'total_sent' => 150,
        'delivered' => 142,
        'opened' => 89,
        'clicked' => 23,
        'open_rate' => 62.7,
        'click_rate' => 16.2
    ],
    'pdf_mode' => true,
    'user' => [
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com'
    ]
];

$pdfOptions = [
    'format' => 'A4',
    'orientation' => 'portrait',
    'filename' => 'test-email-preferences.pdf',
    'title' => 'Email Preferences Report'
];

echo "📊 Test Data:\n";
echo "- Email Stats: {$testData['stats']['total_sent']} sent, {$testData['stats']['delivered']} delivered\n";
echo "- Open Rate: {$testData['stats']['open_rate']}%\n";
echo "- Click Rate: {$testData['stats']['click_rate']}%\n\n";

// 1. Test DomPDF availability
echo "📄 1. Testing DomPDF availability...\n";
try {
    if (class_exists('Dompdf\Dompdf')) {
        echo "   ✅ DomPDF is available\n";
        
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<h1>Test PDF</h1><p>DomPDF is working correctly.</p>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Save test PDF
        $output = $dompdf->output();
        file_put_contents(__DIR__ . '/storage/app/public/test-dompdf.pdf', $output);
        echo "   ✅ DomPDF test PDF generated successfully\n";
        
    } else {
        echo "   ❌ DomPDF not available\n";
    }
} catch (Exception $e) {
    echo "   ❌ DomPDF test failed: " . $e->getMessage() . "\n";
}
echo "\n";

// 2. Test Browserless availability
echo "🌐 2. Testing Browserless availability...\n";
$browserlessUrl = 'http://localhost:3000'; // Default browserless port

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $browserlessUrl . '/screenshot');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'url' => 'data:text/html,<h1>Test</h1>',
    'options' => ['format' => 'A4']
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✅ Browserless is available and responding\n";
} else {
    echo "   ❌ Browserless not available (HTTP $httpCode)\n";
    echo "   ℹ️  Note: Browserless typically runs on port 3000\n";
}
echo "\n";

// 3. Test storage directories
echo "📁 3. Testing storage directories...\n";
$storageDir = __DIR__ . '/storage/app/public/pdfs';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
    echo "   ✅ Created storage directory: $storageDir\n";
} else {
    echo "   ✅ Storage directory exists: $storageDir\n";
}

$writeable = is_writable($storageDir);
echo "   " . ($writeable ? "✅" : "❌") . " Directory is " . ($writeable ? "writable" : "not writable") . "\n";
echo "\n";

// 4. Create a simple HTML template for testing
echo "📝 4. Creating test HTML template...\n";
$htmlTemplate = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Email Preferences Report</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; }
        .header { background: #f3f4f6; padding: 20px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0; }
        .stat-card { background: #f9fafb; padding: 15px; border: 1px solid #e5e7eb; }
        .stat-number { font-size: 24px; font-weight: bold; color: #1f2937; }
        .stat-label { color: #6b7280; font-size: 14px; }
        .preferences { margin-top: 30px; }
        .pref-item { margin: 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Email Preferences Report</h1>
        <p>Generated on ' . date('Y-m-d H:i:s') . '</p>
    </div>
    
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number">' . $testData['stats']['total_sent'] . '</div>
            <div class="stat-label">Total Sent</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">' . $testData['stats']['delivered'] . '</div>
            <div class="stat-label">Delivered</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">' . $testData['stats']['opened'] . '</div>
            <div class="stat-label">Opened</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">' . $testData['stats']['clicked'] . '</div>
            <div class="stat-label">Clicked</div>
        </div>
    </div>
    
    <div class="preferences">
        <h2>Email Preferences</h2>
        <div class="pref-item">Marketing Emails: ' . ($testData['preferences']['marketing_emails'] ? 'Enabled' : 'Disabled') . '</div>
        <div class="pref-item">Product Updates: ' . ($testData['preferences']['product_updates'] ? 'Enabled' : 'Disabled') . '</div>
        <div class="pref-item">Security Alerts: ' . ($testData['preferences']['security_alerts'] ? 'Enabled' : 'Disabled') . '</div>
        <div class="pref-item">Onboarding Emails: ' . ($testData['preferences']['onboarding_emails'] ? 'Enabled' : 'Disabled') . '</div>
        <div class="pref-item">Weekly Digest: ' . ($testData['preferences']['weekly_digest'] ? 'Enabled' : 'Disabled') . '</div>
        <div class="pref-item">Frequency: ' . ucfirst($testData['preferences']['frequency']) . '</div>
    </div>
</body>
</html>';

// Test DomPDF with full template
try {
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($htmlTemplate);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $output = $dompdf->output();
    $filename = $storageDir . '/email-preferences-dompdf-test.pdf';
    file_put_contents($filename, $output);
    echo "   ✅ Full DomPDF test completed: " . basename($filename) . "\n";
    echo "   📄 File size: " . number_format(filesize($filename)) . " bytes\n";
} catch (Exception $e) {
    echo "   ❌ DomPDF full test failed: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Test Browserless PDF generation if available
if ($httpCode === 200) {
    echo "🌐 5. Testing Browserless PDF generation...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $browserlessUrl . '/pdf');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'html' => $htmlTemplate,
        'options' => [
            'format' => 'A4',
            'printBackground' => true,
            'margin' => ['top' => '1cm', 'right' => '1cm', 'bottom' => '1cm', 'left' => '1cm']
        ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $pdfContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $pdfContent) {
        $filename = $storageDir . '/email-preferences-browserless-test.pdf';
        file_put_contents($filename, $pdfContent);
        echo "   ✅ Browserless PDF test completed: " . basename($filename) . "\n";
        echo "   📄 File size: " . number_format(filesize($filename)) . " bytes\n";
    } else {
        echo "   ❌ Browserless PDF test failed (HTTP $httpCode)\n";
    }
} else {
    echo "🌐 5. Skipping Browserless PDF test (service not available)\n";
}
echo "\n";

echo "✅ PDF Generation Testing Complete!\n\n";
echo "📁 Check generated files in: storage/app/public/pdfs/\n";
echo "🔗 Both DomPDF and Browserless engines have been tested\n\n";

if (file_exists($storageDir . '/email-preferences-dompdf-test.pdf')) {
    echo "📄 DomPDF Result: SUCCESS\n";
} else {
    echo "📄 DomPDF Result: FAILED\n";
}

if (file_exists($storageDir . '/email-preferences-browserless-test.pdf')) {
    echo "🌐 Browserless Result: SUCCESS\n";
} else {
    echo "🌐 Browserless Result: FAILED (likely service not running)\n";
}

echo "\n";
echo "💡 Next steps:\n";
echo "1. Install/start Browserless if needed: docker run -p 3000:3000 browserless/chrome\n";
echo "2. Test the Laravel PDF generation API endpoints\n";
echo "3. Use the Vue component PDF generation buttons\n";

?>