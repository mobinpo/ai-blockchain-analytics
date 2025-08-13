# 📄 Complete PDF Generation Guide for Vue Applications

## Overview

This comprehensive PDF generation system supports converting Vue components to high-quality PDFs using both **Browserless** (headless Chrome) and **DomPDF** as a fallback. The system is designed for enterprise-grade applications with features like caching, monitoring, and automatic failover.

## 🚀 Quick Start

### 1. Basic Setup

The PDF generation system is already configured and ready to use. You can start generating PDFs immediately:

```vue
<template>
  <div>
    <EnhancedPdfExportButton
      export-type="route"
      export-target="sentiment-timeline-demo"
      :export-data="{ coin: 'bitcoin', days: 30 }"
      @export-completed="handlePdfGenerated"
    />
  </div>
</template>

<script setup>
import EnhancedPdfExportButton from '@/Components/EnhancedPdfExportButton.vue'

const handlePdfGenerated = (result) => {
  console.log('PDF generated:', result.download_url)
}
</script>
```

### 2. Environment Configuration

Add these variables to your `.env` file:

```env
# Browserless Configuration (High-quality PDFs)
BROWSERLESS_ENABLED=false
BROWSERLESS_URL=http://localhost:3000
BROWSERLESS_TIMEOUT=30
BROWSERLESS_HEALTH_CHECK_INTERVAL=300
BROWSERLESS_MAX_RETRIES=2

# DomPDF Configuration (Always enabled as fallback)
DOMPDF_ENABLED=true

# PDF Storage and Management
PDF_STORAGE_DISK=public
PDF_STORAGE_PATH=pdfs
PDF_CLEANUP_ENABLED=true
PDF_CLEANUP_MAX_AGE_DAYS=30

# Performance Settings
PDF_CONCURRENT_JOBS=3
PDF_QUEUE_CONNECTION=default
PDF_CACHE_ENABLED=true
PDF_CACHE_TTL=3600

# Monitoring and Logging
PDF_LOGGING_ENABLED=true
PDF_MONITORING_ENABLED=true
```

## 🔧 Setting Up Browserless (Optional but Recommended)

### Option 1: Docker Compose Integration

Use the provided Browserless configuration:

```bash
# Start Browserless service
cd docker/browserless
docker-compose -f docker-compose.browserless.yml up -d

# Enable Browserless in your .env
BROWSERLESS_ENABLED=true
BROWSERLESS_URL=http://browserless:3000
```

### Option 2: Hosted Browserless Service

```env
BROWSERLESS_ENABLED=true
BROWSERLESS_URL=https://chrome.browserless.io
BROWSERLESS_API_KEY=your-api-key-here
```

### Option 3: Local Installation

```bash
# Install Browserless locally
npm install -g browserless

# Start the service
browserless --port 3000
```

## 📚 API Reference

### Routes

#### 1. Generate PDF from Vue Route
```http
POST /enhanced-pdf/generate/route
Content-Type: application/json

{
  "route": "sentiment-timeline-demo",
  "data": {
    "coin": "bitcoin",
    "days": 30,
    "include_volume": true
  },
  "format": "A4",
  "orientation": "landscape",
  "quality": "high"
}
```

#### 2. Generate PDF from Vue Component
```http
POST /enhanced-pdf/generate/component
Content-Type: application/json

{
  "component": "SentimentPriceChart",
  "props": {
    "coinSymbol": "BTC",
    "height": 400
  },
  "options": {
    "format": "A4",
    "orientation": "portrait"
  }
}
```

#### 3. Generate Sentiment Timeline PDF
```http
POST /enhanced-pdf/generate/sentiment-timeline
Content-Type: application/json

{
  "coin": "bitcoin",
  "days": 30,
  "include_volume": false,
  "orientation": "landscape",
  "filename": "sentiment-bitcoin-30d.pdf"
}
```

#### 4. Generate Dashboard PDF
```http
POST /enhanced-pdf/generate/dashboard
Content-Type: application/json

{
  "dashboard_data": {
    "title": "Analytics Dashboard",
    "include_charts": true,
    "metrics": {}
  },
  "format": "A4"
}
```

### Response Format

```json
{
  "success": true,
  "message": "PDF generated successfully",
  "data": {
    "success": true,
    "method": "browserless",
    "file_path": "pdfs/browserless/report-2025-01-08.pdf",
    "filename": "report-2025-01-08.pdf",
    "url": "/storage/pdfs/browserless/report-2025-01-08.pdf",
    "download_url": "/enhanced-pdf/download/report-2025-01-08.pdf",
    "size": 245678,
    "size_formatted": "240 KB",
    "processing_time": 3.45,
    "quality": "high",
    "generated_at": "2025-01-08T10:30:00Z"
  }
}
```

## 🎨 Vue Components

### 1. PdfTemplate Component

A comprehensive template component for PDF layouts:

```vue
<template>
  <PdfTemplate
    template-type="dashboard"
    :pdf-mode="true"
    title="Blockchain Analytics Report"
    subtitle="AI-Powered Insights"
    :show-header="true"
    :show-footer="true"
    confidentiality-level="Internal"
    author="AI Blockchain Analytics"
    subject="Security Analysis Report"
  >
    <template #content>
      <!-- Your PDF content here -->
      <div class="space-y-6">
        <h2>Executive Summary</h2>
        <p>Report content...</p>
      </div>
    </template>
  </PdfTemplate>
</template>

<script setup>
import PdfTemplate from '@/Components/PdfTemplate.vue'
</script>
```

### 2. EnhancedPdfExportButton Component

Feature-rich export button with advanced options:

```vue
<template>
  <EnhancedPdfExportButton
    export-type="component"
    export-target="MyDashboardComponent"
    :export-data="dashboardData"
    variant="primary"
    size="medium"
    button-text="Export Dashboard"
    default-engine="auto"
    default-format="A4"
    default-orientation="portrait"
    @export-started="onExportStarted"
    @export-completed="onExportCompleted"
    @export-failed="onExportFailed"
  />
</template>
```

### 3. Basic PDF Export Integration

For simple use cases:

```vue
<template>
  <div>
    <button @click="exportToPdf" :disabled="generating">
      {{ generating ? 'Generating...' : 'Export PDF' }}
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const generating = ref(false)

const exportToPdf = async () => {
  generating.value = true
  
  try {
    const response = await axios.post('/enhanced-pdf/generate/route', {
      route: 'my-vue-route',
      data: { /* your data */ },
      format: 'A4',
      orientation: 'portrait'
    })
    
    if (response.data.success) {
      // Download the PDF
      window.open(response.data.data.download_url, '_blank')
    }
  } catch (error) {
    console.error('PDF generation failed:', error)
  } finally {
    generating.value = false
  }
}
</script>
```

## ⚙️ Configuration Options

### PDF Generation Options

```javascript
const pdfOptions = {
  // Basic Options
  format: 'A4' | 'A3' | 'Letter' | 'Legal',
  orientation: 'portrait' | 'landscape',
  quality: 'high' | 'medium' | 'low',
  
  // Engine Selection
  engine: 'auto' | 'browserless' | 'dompdf',
  
  // Browserless Options
  margin: {
    top: '1cm',
    right: '1cm',
    bottom: '1cm',
    left: '1cm'
  },
  scale: 1.0,
  wait_for_selector: '.chart-loaded',
  wait_time: 2000, // milliseconds
  print_background: true,
  
  // DomPDF Options
  dpi: 96,
  enable_remote: false,
  enable_php: false,
  
  // File Options
  filename: 'custom-name.pdf',
  
  // Security & Metadata
  title: 'Document Title',
  author: 'Your Company',
  subject: 'Report Subject',
  keywords: 'blockchain,analytics,ai'
}
```

### Route to Template Mapping

Configure how Vue routes map to Blade templates for DomPDF fallback:

```php
// config/enhanced_pdf.php
'route_template_mapping' => [
    'sentiment-timeline-demo' => 'pdf.sentiment-price-timeline',
    'dashboard' => 'pdf.dashboard-report',
    'north-star-demo' => 'pdf.north-star-dashboard',
    'custom-route' => 'pdf.custom-template'
]
```

## 🔍 Testing and Debugging

### Test PDF Generation

```bash
# Test engine availability
curl http://localhost:8003/pdf/test-engines

# Generate test PDF
curl http://localhost:8003/pdf/test-dashboard

# Check service status
curl http://localhost:8003/enhanced-pdf/status
```

### Debug Common Issues

1. **Browserless Connection Failed**
   ```bash
   # Check if Browserless is running
   curl http://localhost:3000/health
   
   # Verify configuration
   php artisan config:show enhanced_pdf.browserless
   ```

2. **DomPDF Rendering Issues**
   ```bash
   # Test DomPDF availability
   php artisan tinker
   >>> class_exists('\Barryvdh\DomPDF\Facade\Pdf')
   ```

3. **Storage Permission Issues**
   ```bash
   # Fix storage permissions
   docker compose exec app chmod -R 775 storage/app/public
   docker compose exec app chown -R www-data:www-data storage/app/public
   ```

### Enable Debug Mode

```env
PDF_LOGGING_ENABLED=true
PDF_LOG_LEVEL=debug
PDF_LOG_PERFORMANCE=true
```

## 📊 Monitoring and Performance

### Service Health Check

```javascript
// Check service status
const response = await axios.get('/enhanced-pdf/status')
console.log(response.data)

// Output:
{
  "browserless": {
    "enabled": true,
    "url": "http://localhost:3000",
    "healthy": true,
    "timeout": 30
  },
  "dompdf": {
    "available": true,
    "laravel_wrapper": true
  },
  "storage": {
    "disk": "public",
    "base_path": "pdfs/",
    "writable": true
  }
}
```

### File Management

```javascript
// List generated PDFs
const files = await axios.get('/enhanced-pdf/files')

// Cleanup old files
const cleanup = await axios.delete('/enhanced-pdf/cleanup', {
  data: { days_old: 7 }
})
```

### Performance Optimization

1. **Enable Caching**
   ```env
   PDF_CACHE_ENABLED=true
   PDF_CACHE_TTL=3600
   ```

2. **Queue Processing**
   ```env
   PDF_QUEUE_CONNECTION=redis
   PDF_CONCURRENT_JOBS=3
   ```

3. **Memory Management**
   ```env
   PDF_MEMORY_LIMIT=512M
   PDF_EXECUTION_TIMEOUT=120
   ```

## 🔧 Advanced Usage

### Custom PDF Templates

Create Blade templates for DomPDF fallback:

```blade
{{-- resources/views/pdf/custom-report.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Custom Report' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .chart-placeholder {
            width: 100%;
            height: 300px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title ?? 'Custom Report' }}</h1>
        <p>Generated: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
    
    <div class="content">
        @if(isset($data))
            <pre>{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
        @else
            <div class="chart-placeholder">
                Chart data would appear here
            </div>
        @endif
    </div>
</body>
</html>
```

### Programmatic PDF Generation

```php
// app/Services/CustomPdfService.php
use App\Services\EnhancedVuePdfService;

class CustomPdfService
{
    public function __construct(
        private EnhancedVuePdfService $pdfService
    ) {}
    
    public function generateUserReport(User $user): array
    {
        $data = [
            'user' => $user,
            'analytics' => $this->getUserAnalytics($user),
            'charts' => $this->getChartData($user)
        ];
        
        return $this->pdfService->generateFromVueRoute(
            'user-report',
            $data,
            [
                'format' => 'A4',
                'orientation' => 'portrait',
                'filename' => "user-report-{$user->id}-" . now()->format('Y-m-d') . '.pdf'
            ],
            $user->id
        );
    }
}
```

### Batch PDF Generation

```php
// Generate multiple PDFs
foreach ($reports as $report) {
    dispatch(new GeneratePdfJob($report->id, $report->data));
}
```

## 🛡️ Security Considerations

### Access Control

```php
// Middleware for PDF generation endpoints
Route::middleware(['auth', 'throttle:pdf'])->group(function () {
    Route::post('/enhanced-pdf/generate/route', [EnhancedPdfController::class, 'generateFromRoute']);
});
```

### Rate Limiting

```php
// config/enhanced_pdf.php
'security' => [
    'rate_limit' => [
        'max_attempts' => 10,
        'decay_minutes' => 60
    ]
]
```

### File Security

- PDF files are stored in `storage/app/public/pdfs/`
- Access controlled through Laravel's authentication
- Automatic cleanup of old files
- File size limits enforced

## 🚨 Troubleshooting

### Common Issues and Solutions

1. **"PDF generation failed" Error**
   - Check if DomPDF is installed: `composer show barryvdh/laravel-dompdf`
   - Verify storage permissions
   - Check logs: `tail -f storage/logs/laravel.log`

2. **Browserless Connection Timeout**
   - Increase timeout: `BROWSERLESS_TIMEOUT=60`
   - Check Browserless health: `curl http://localhost:3000/health`
   - Verify Docker network connectivity

3. **Memory Limit Exceeded**
   - Increase PHP memory limit: `PDF_MEMORY_LIMIT=1G`
   - Optimize data payload size
   - Use pagination for large datasets

4. **Charts Not Rendering**
   - Enable wait for charts: `wait_for_charts: true`
   - Increase wait time: `wait_time: 5000`
   - Use specific selector: `wait_for_selector: '.chart-loaded'`

### Performance Issues

1. **Slow PDF Generation**
   - Use DomPDF for simple layouts
   - Enable caching
   - Optimize chart rendering
   - Reduce image sizes

2. **High Memory Usage**
   - Limit concurrent jobs
   - Use queue processing
   - Clear temporary files regularly

## 📋 Best Practices

### 1. Component Design for PDFs

```vue
<template>
  <div :class="{ 'pdf-optimized': pdfMode }">
    <!-- Use CSS that works well in both browsers and PDFs -->
    <div class="chart-container">
      <canvas 
        ref="chartCanvas"
        :class="{ 'pdf-chart': pdfMode }"
        @chart-loaded="onChartLoaded"
      />
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  pdfMode: { type: Boolean, default: false }
})

const onChartLoaded = () => {
  // Signal that chart is ready for PDF capture
  if (props.pdfMode) {
    document.body.classList.add('chart-loaded')
  }
}
</script>

<style scoped>
.pdf-optimized {
  /* PDF-specific styles */
  font-size: 12pt;
  line-height: 1.4;
}

.pdf-chart {
  /* Optimize chart for PDF rendering */
  image-rendering: -webkit-optimize-contrast;
}
</style>
```

### 2. Error Handling

```javascript
const exportPdf = async () => {
  try {
    const result = await pdfService.generate(options)
    
    // Success handling
    if (result.success) {
      showNotification('PDF generated successfully!')
      downloadFile(result.download_url)
    }
  } catch (error) {
    // Graceful error handling
    if (error.response?.status === 429) {
      showNotification('Too many requests. Please try again later.', 'warning')
    } else if (error.response?.status === 500) {
      showNotification('PDF generation failed. Please try again.', 'error')
    } else {
      showNotification('An unexpected error occurred.', 'error')
    }
    
    // Log for debugging
    console.error('PDF generation error:', error)
  }
}
```

### 3. Progressive Enhancement

```vue
<template>
  <div>
    <!-- Fallback for when JavaScript is disabled -->
    <noscript>
      <a href="/reports/download/{{ reportId }}" class="btn">
        Download PDF Report
      </a>
    </noscript>
    
    <!-- Enhanced PDF export with JavaScript -->
    <EnhancedPdfExportButton
      v-if="canGeneratePdf"
      :export-data="reportData"
      @export-completed="handleSuccess"
    />
  </div>
</template>
```

## 🔮 Future Enhancements

- **PDF Templating Engine**: Visual template designer
- **Real-time Collaboration**: Multi-user PDF generation
- **Advanced Analytics**: Usage tracking and optimization
- **Cloud Storage Integration**: Direct upload to S3/GCS
- **PDF Editing**: In-browser PDF annotation
- **Batch Processing**: Queue-based bulk generation
- **API Rate Limiting**: Per-user quotas and throttling

## 📞 Support

For issues and questions:

1. Check the logs: `storage/logs/laravel.log`
2. Test the API endpoints directly
3. Verify environment configuration
4. Check Docker container status
5. Review the comprehensive error messages

---

**🎉 Congratulations!** You now have a production-ready PDF generation system that can handle everything from simple reports to complex interactive dashboards. The system automatically falls back to DomPDF when Browserless is unavailable, ensuring reliable PDF generation in all scenarios.
