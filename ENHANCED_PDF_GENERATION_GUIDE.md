# 📄 Enhanced PDF Generation System

**Convert Vue components and views to high-quality PDFs using Browserless (headless Chrome) or DomPDF fallback**

## 🎯 Overview

This enhanced PDF generation system provides a robust solution for converting Vue.js components and views into professional PDF documents. It features intelligent fallback between high-quality Browserless rendering and reliable DomPDF generation.

## 🏗️ Architecture

```
Vue Component → Secure Preview URL → Browserless/DomPDF → PDF File → Storage
```

### Core Components

1. **📊 EnhancedVuePdfService** - Main PDF generation engine
2. **🎛️ EnhancedPdfController** - HTTP API interface
3. **🎨 PDF Templates** - Blade templates for DomPDF fallback
4. **🔧 Configuration** - Flexible configuration system
5. **🖥️ Demo Interface** - Interactive testing and management UI

## 🚀 Quick Start

### 1. **Generate PDF from Vue Route**

```bash
curl -X POST "http://localhost:8003/enhanced-pdf/generate/route" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "route": "sentiment-timeline-demo",
    "data": {
      "coin": "bitcoin",
      "days": 30,
      "include_volume": true
    },
    "format": "A4",
    "orientation": "landscape"
  }'
```

### 2. **Generate PDF from Vue Component**

```bash
curl -X POST "http://localhost:8003/enhanced-pdf/generate/component" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "component": "EnhancedSentimentPriceTimeline",
    "props": {
      "initialCoin": "ethereum",
      "initialTimeframe": 90,
      "showVolume": true
    },
    "options": {
      "filename": "ethereum-sentiment-analysis.pdf"
    }
  }'
```

### 3. **Access Demo Interface**

Visit `/enhanced-pdf/demo` for an interactive interface to:
- Generate PDFs with various options
- View generated files
- Test different rendering methods
- Monitor service status

## 📊 Rendering Methods

### 🌟 **Browserless (High-Quality)**

**Best for**: Interactive charts, complex layouts, JavaScript-heavy components

**Features**:
- ✅ Full JavaScript execution
- ✅ CSS animations and transitions  
- ✅ Perfect chart rendering (Chart.js, D3.js)
- ✅ High-resolution output
- ✅ Custom fonts and styling
- ✅ Print media queries support

**Requirements**:
- Browserless service running
- Network connectivity
- Higher resource usage

### 📄 **DomPDF (Fallback)**

**Best for**: Text-heavy reports, simple layouts, guaranteed availability

**Features**:
- ✅ Always available (no external dependencies)
- ✅ Fast generation
- ✅ Low resource usage
- ✅ Good text rendering
- ✅ Basic CSS support
- ⚠️ Limited JavaScript support
- ⚠️ Simple chart placeholders

## 🎛️ API Endpoints

### **Generate from Route**
```http
POST /enhanced-pdf/generate/route
```

**Request Body**:
```json
{
  "route": "sentiment-timeline-demo",
  "data": {
    "coin": "bitcoin",
    "days": 30
  },
  "options": {
    "format": "A4",
    "orientation": "portrait",
    "filename": "custom-name.pdf"
  }
}
```

### **Generate from Component**
```http
POST /enhanced-pdf/generate/component
```

**Request Body**:
```json
{
  "component": "SentimentPriceChart",
  "props": {
    "coinSymbol": "BTC",
    "height": 400
  },
  "options": {
    "format": "A3",
    "orientation": "landscape"
  }
}
```

### **Specialized Endpoints**

| Endpoint | Description | Purpose |
|----------|-------------|---------|
| `POST /enhanced-pdf/generate/sentiment-timeline` | Sentiment timeline PDF | Crypto analysis reports |
| `POST /enhanced-pdf/generate/dashboard` | Dashboard report PDF | Analytics summaries |
| `GET /enhanced-pdf/status` | Service status | Health monitoring |
| `GET /enhanced-pdf/files` | List generated files | File management |
| `DELETE /enhanced-pdf/cleanup` | Clean old files | Storage maintenance |

## 🔧 Configuration

### Environment Variables

```env
# Browserless Configuration
BROWSERLESS_ENABLED=true
BROWSERLESS_URL=http://localhost:3000
BROWSERLESS_TIMEOUT=30

# DomPDF Configuration  
DOMPDF_ENABLED=true

# Storage Configuration
PDF_STORAGE_DISK=public
PDF_STORAGE_PATH=pdfs

# Performance Settings
PDF_CONCURRENT_JOBS=3
PDF_MEMORY_LIMIT=512M
PDF_EXECUTION_TIMEOUT=120

# Security Settings
PDF_PREVIEW_TOKEN_LIFETIME=10
PDF_MAX_FILE_SIZE=52428800

# Monitoring
PDF_MONITORING_ENABLED=true
PDF_LOGGING_ENABLED=true
```

### Configuration File

Edit `config/enhanced_pdf.php` for detailed settings:

```php
<?php

return [
    'default_method' => 'browserless',
    
    'browserless' => [
        'enabled' => env('BROWSERLESS_ENABLED', false),
        'url' => env('BROWSERLESS_URL', 'http://localhost:3000'),
        'timeout' => 30,
        'default_options' => [
            'format' => 'A4',
            'orientation' => 'portrait',
            'wait_time' => 2000
        ]
    ],
    
    'security' => [
        'allowed_routes' => [
            'sentiment-timeline-demo',
            'dashboard',
            'north-star-demo'
        ]
    ]
];
```

## 🎨 Template System

### Blade Templates for DomPDF

The system includes optimized Blade templates for fallback rendering:

```php
// resources/views/pdf/sentiment-price-timeline.blade.php
<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Sentiment Analysis' }}</title>
    <style>
        /* PDF-optimized styles */
        .chart-placeholder {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            border: 2px dashed #3b82f6;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="pdf-container">
        <h1>{{ $title }}</h1>
        <!-- Chart placeholder with data table -->
        <div class="chart-placeholder">
            📈 Interactive Chart (Best viewed with Browserless)
        </div>
        <!-- Statistics and data tables -->
    </div>
</body>
</html>
```

### Template Mapping

Vue routes are automatically mapped to Blade templates:

```php
'route_template_mapping' => [
    'sentiment-timeline-demo' => 'pdf.sentiment-price-timeline',
    'dashboard' => 'pdf.dashboard-report',
    'north-star-demo' => 'pdf.north-star-dashboard'
]
```

## 📈 Usage Examples

### 1. **Cryptocurrency Analysis Report**

```php
use App\Services\EnhancedVuePdfService;

$pdfService = app(EnhancedVuePdfService::class);

$result = $pdfService->generateFromVueRoute(
    'sentiment-timeline-demo',
    [
        'coin' => 'bitcoin',
        'days' => 90,
        'include_volume' => true,
        'pdf_mode' => true
    ],
    [
        'format' => 'A4',
        'orientation' => 'landscape',
        'filename' => 'bitcoin-90day-analysis.pdf',
        'wait_for_selector' => '.sentiment-price-timeline canvas',
        'wait_time' => 3000
    ]
);

if ($result['success']) {
    $downloadUrl = $result['download_url'];
    $fileSize = $result['size_formatted'];
    $method = $result['method']; // 'browserless' or 'dompdf'
}
```

### 2. **Dashboard Report Generation**

```php
$dashboardData = [
    'title' => 'Q4 2024 Analytics Report',
    'include_charts' => true,
    'metrics' => [
        'total_analyses' => 1250,
        'success_rate' => 98.5,
        'avg_processing_time' => '2.3s'
    ]
];

$result = $pdfService->generateFromVueRoute(
    'dashboard',
    $dashboardData,
    [
        'format' => 'A4',
        'orientation' => 'portrait',
        'title' => 'Q4 Analytics Report'
    ]
);
```

### 3. **Component-Based Generation**

```javascript
// Frontend JavaScript
const generatePdf = async () => {
    const response = await fetch('/enhanced-pdf/generate/component', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            component: 'EnhancedSentimentPriceTimeline',
            props: {
                initialCoin: 'ethereum',
                initialTimeframe: 30,
                showVolume: true,
                height: 600
            },
            options: {
                format: 'A3',
                orientation: 'landscape',
                quality: 'high'
            }
        })
    });
    
    const result = await response.json();
    
    if (result.success) {
        // Download or display the PDF
        window.open(result.data.url, '_blank');
    }
};
```

## 🔍 Monitoring & Management

### Service Status

```bash
curl "http://localhost:8003/enhanced-pdf/status" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response**:
```json
{
  "success": true,
  "data": {
    "browserless": {
      "enabled": true,
      "healthy": true,
      "url": "http://localhost:3000"
    },
    "dompdf": {
      "available": true,
      "laravel_wrapper": true
    },
    "storage": {
      "disk": "public",
      "writable": true
    }
  }
}
```

### File Management

```bash
# List generated PDFs
curl "http://localhost:8003/enhanced-pdf/files" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Clean up old files
curl -X DELETE "http://localhost:8003/enhanced-pdf/cleanup" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"days_old": 7}'
```

### Performance Monitoring

The system tracks:
- **Generation times** per method
- **Success/failure rates**
- **File sizes** and storage usage
- **Method preference** (Browserless vs DomPDF)
- **Queue processing** times

## 🛡️ Security Features

### Secure Preview URLs

- **Temporary tokens** with configurable expiration
- **Signed URLs** to prevent unauthorized access
- **Route validation** against allowed routes list
- **Component validation** against allowed components

### Access Control

```php
'security' => [
    'preview_token_lifetime' => 10, // minutes
    'max_file_size' => 50 * 1024 * 1024, // 50MB
    'allowed_routes' => [
        'sentiment-timeline-demo',
        'dashboard'
    ],
    'allowed_components' => [
        'EnhancedSentimentPriceTimeline',
        'SentimentPriceChart'
    ]
]
```

## ⚡ Performance Optimization

### Intelligent Method Selection

The system automatically chooses the best rendering method:

1. **Check Browserless availability** - Health check with caching
2. **Evaluate complexity** - Charts/JavaScript favor Browserless
3. **Consider fallback** - Always ensure PDF generation succeeds
4. **Monitor performance** - Track and optimize based on metrics

### Caching Strategy

- **Service health checks** cached for 5 minutes
- **Template compilation** cached by Laravel
- **Configuration** cached for performance
- **Generated PDFs** stored with metadata

### Queue Integration

```php
// Dispatch PDF generation to queue for large jobs
dispatch(new GeneratePdfJob($route, $data, $options));
```

## 🎛️ Demo Interface Features

Visit `/enhanced-pdf/demo` for:

### **Interactive Generation**
- Quick PDF generation forms
- Real-time parameter adjustment
- Multiple format/orientation options
- Component and route selection

### **File Management**
- View generated PDFs
- Download links
- File size and method information
- Bulk cleanup operations

### **Service Monitoring**
- Real-time service status
- Health check indicators
- Performance metrics
- Configuration display

### **API Documentation**
- Interactive API examples
- Copy-paste curl commands
- Request/response samples
- Parameter documentation

## 🔧 Troubleshooting

### Common Issues

#### **Browserless Unavailable**
```
Error: Browserless API error: Connection refused
```
**Solution**: 
1. Check Browserless service is running
2. Verify `BROWSERLESS_URL` configuration
3. System will automatically fallback to DomPDF

#### **Charts Not Rendering**
```
Warning: Generated using DomPDF - charts may not render correctly
```
**Solution**: 
1. Enable Browserless for chart-heavy PDFs
2. Use `wait_for_selector` for dynamic content
3. Increase `wait_time` for complex charts

#### **Large File Sizes**
```
Error: PDF file exceeds maximum size limit
```
**Solution**:
1. Increase `PDF_MAX_FILE_SIZE` in environment
2. Optimize images and reduce content
3. Use compression options

### Performance Tuning

```env
# Increase memory for large PDFs
PDF_MEMORY_LIMIT=1024M

# Adjust timeout for complex pages
PDF_EXECUTION_TIMEOUT=180

# Enable concurrent processing
PDF_CONCURRENT_JOBS=5

# Optimize wait times
BROWSERLESS_WAIT_TIME=3000
```

## 📊 Integration Examples

### Laravel Controller

```php
class ReportController extends Controller
{
    public function generateAnalysisReport(Request $request)
    {
        $pdfService = app(EnhancedVuePdfService::class);
        
        $data = [
            'coin' => $request->coin,
            'period' => $request->period,
            'analysis_data' => $this->getAnalysisData($request->coin)
        ];
        
        $result = $pdfService->generateFromVueRoute(
            'sentiment-timeline-demo',
            $data,
            [
                'filename' => "analysis-{$request->coin}-" . now()->format('Y-m-d') . '.pdf',
                'format' => 'A4',
                'orientation' => 'landscape'
            ]
        );
        
        if ($result['success']) {
            return response()->json([
                'download_url' => $result['download_url'],
                'file_size' => $result['size_formatted'],
                'generation_method' => $result['method']
            ]);
        }
        
        return response()->json(['error' => 'PDF generation failed'], 500);
    }
}
```

### Vue Component Integration

```vue
<template>
    <div class="pdf-generator">
        <button 
            @click="generatePdf" 
            :disabled="generating"
            class="btn btn-primary"
        >
            {{ generating ? 'Generating...' : 'Generate PDF' }}
        </button>
        
        <div v-if="pdfUrl" class="mt-4">
            <a :href="pdfUrl" target="_blank" class="btn btn-success">
                📄 View PDF
            </a>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const generating = ref(false)
const pdfUrl = ref('')

const generatePdf = async () => {
    generating.value = true
    
    try {
        const response = await axios.post('/enhanced-pdf/generate/route', {
            route: 'sentiment-timeline-demo',
            data: {
                coin: 'bitcoin',
                days: 30
            },
            options: {
                format: 'A4',
                orientation: 'landscape'
            }
        })
        
        if (response.data.success) {
            pdfUrl.value = response.data.data.url
        }
    } catch (error) {
        console.error('PDF generation failed:', error)
    } finally {
        generating.value = false
    }
}
</script>
```

## 🎉 Summary

The Enhanced PDF Generation System provides:

✅ **Dual Rendering Methods** - Browserless (high-quality) + DomPDF (reliable fallback)  
✅ **Vue Component Support** - Direct component-to-PDF conversion  
✅ **Intelligent Fallback** - Automatic method selection based on availability  
✅ **Secure Preview System** - Temporary signed URLs for Browserless access  
✅ **Professional Templates** - Optimized Blade templates for DomPDF  
✅ **Interactive Demo** - Full-featured testing and management interface  
✅ **Comprehensive API** - RESTful endpoints for all operations  
✅ **Performance Monitoring** - Health checks and metrics tracking  
✅ **File Management** - Automated cleanup and storage optimization  
✅ **Security Features** - Access control and validation  

**Perfect for generating professional reports, charts, dashboards, and documentation from Vue.js applications!** 🚀📄

---

## 🔗 Quick Links

- **Demo Interface**: `/enhanced-pdf/demo`
- **API Status**: `/enhanced-pdf/status`
- **Generated Files**: `/enhanced-pdf/files`
- **Configuration**: `config/enhanced_pdf.php`
- **Templates**: `resources/views/pdf/`