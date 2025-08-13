<template>
    <AppLayout title="PDF Generation Demo">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg mb-8">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                    📄 Vue to PDF Generation Demo
                                </h1>
                                <p class="mt-2 text-lg text-gray-600 dark:text-gray-400">
                                    Generate high-quality PDFs from Vue components using Browserless or DomPDF
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">System Status</div>
                                <div class="text-lg font-semibold text-green-600">
                                    🟢 Ready
                                </div>
                            </div>
                        </div>
                        
                        <!-- Feature Highlights -->
                        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-blue-900 dark:text-blue-100">Browserless Engine</div>
                                        <div class="text-xs text-blue-600 dark:text-blue-400">High-quality rendering</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-green-900 dark:text-green-100">DomPDF Fallback</div>
                                        <div class="text-xs text-green-600 dark:text-green-400">Reliable server-side</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-purple-900 dark:text-purple-100">Chart Support</div>
                                        <div class="text-xs text-purple-600 dark:text-purple-400">Chart.js rendering</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-orange-900 dark:text-orange-100">Auto Fallback</div>
                                        <div class="text-xs text-orange-600 dark:text-orange-400">Intelligent switching</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main PDF Generator Component -->
                <PdfGenerator
                    default-type="sentiment-chart"
                    default-engine="auto"
                    @pdf-generated="onPdfGenerated"
                    @generation-started="onGenerationStarted"
                    @generation-error="onGenerationError"
                />

                <!-- Usage Examples -->
                <div class="mt-8 bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        🔌 API Usage Examples
                    </h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- JavaScript Example -->
                        <div>
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-2">JavaScript API Call</h4>
                            <div class="bg-panel dark:bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <pre class="text-sm text-gray-800 dark:text-gray-200"><code>// Generate PDF from Vue component
const response = await axios.post('/api/vue-pdf/generate', {
  component_route: 'charts.sentiment-price',
  data: {
    chart_data: {
      sentiment_timeline: [...],
      price_timeline: [...]
    },
    coin_symbol: 'BTC'
  },
  options: {
    format: 'A4',
    orientation: 'landscape',
    engine: 'auto'
  }
})

// Download the generated PDF
window.open(response.data.result.download_url)</code></pre>
                            </div>
                        </div>
                        
                        <!-- Vue Component Example -->
                        <div>
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-2">Vue Component Usage</h4>
                            <div class="bg-panel dark:bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <pre class="text-sm text-gray-800 dark:text-gray-200"><code>&lt;template&gt;
  &lt;PdfGenerator
    default-type="sentiment-chart"
    default-engine="browserless"
    @pdf-generated="handlePdfGenerated"
  /&gt;
&lt;/template&gt;

&lt;script setup&gt;
import PdfGenerator from '@/Components/PdfGenerator.vue'

const handlePdfGenerated = (result) => {
  console.log('PDF generated:', result)
  // Handle successful generation
}
&lt;/script&gt;</code></pre>
                            </div>
                        </div>
                    </div>
                    
                    <!-- cURL Example -->
                    <div class="mt-6">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white mb-2">cURL Command</h4>
                        <div class="bg-panel dark:bg-gray-900 rounded-lg p-4 overflow-x-auto">
                            <pre class="text-sm text-gray-800 dark:text-gray-200"><code>curl -X POST {{ appUrl }}/api/vue-pdf/generate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "component_route": "charts.sentiment-price",
    "data": {
      "chart_data": {
        "sentiment_timeline": [
          {"x": "2025-01-01", "y": 0.2},
          {"x": "2025-01-02", "y": 0.3}
        ],
        "price_timeline": [
          {"x": "2025-01-01", "y": 50000},
          {"x": "2025-01-02", "y": 51000}
        ]
      }
    },
    "options": {
      "format": "A4",
      "orientation": "landscape",
      "filename": "sentiment-chart.pdf"
    }
  }'</code></pre>
                        </div>
                    </div>
                    
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            @click="copyApiExample"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-sm"
                        >
                            📋 Copy API Example
                        </button>
                        <button
                            @click="copyCurlExample"
                            class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm"
                        >
                            📋 Copy cURL Example
                        </button>
                        <span v-if="copied" class="px-3 py-2 text-sm text-green-600 dark:text-green-400">Copied!</span>
                    </div>
                </div>

                <!-- System Information -->
                <div class="mt-8 bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        🔧 System Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Engine Status</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Browserless:</span>
                                    <span class="text-sm font-medium text-yellow-600">⚠️ Disabled</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">DomPDF:</span>
                                    <span class="text-sm font-medium text-green-600">✅ Available</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Auto Fallback:</span>
                                    <span class="text-sm font-medium text-blue-600">🔄 Active</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Supported Features</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Vue Components:</span>
                                    <span class="text-sm font-medium text-green-600">✅ Supported</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Chart.js:</span>
                                    <span class="text-sm font-medium text-green-600">✅ Supported</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Tailwind CSS:</span>
                                    <span class="text-sm font-medium text-green-600">✅ Supported</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PdfGenerator from '@/Components/PdfGenerator.vue'

// Reactive state
const copied = ref(false)
const notifications = ref([])

// Computed properties
const appUrl = computed(() => {
    return window.location.origin
})

// Methods
const onPdfGenerated = (result) => {
    console.log('PDF generated successfully:', result)
    
    // Show success notification
    addNotification({
        type: 'success',
        title: 'PDF Generated',
        message: `Successfully generated ${result.filename} using ${result.method} engine`,
        timeout: 5000
    })
}

const onGenerationStarted = () => {
    console.log('PDF generation started')
    
    addNotification({
        type: 'info',
        title: 'Generation Started',
        message: 'PDF generation is in progress...',
        timeout: 3000
    })
}

const onGenerationError = (error) => {
    console.error('PDF generation error:', error)
    
    addNotification({
        type: 'error',
        title: 'Generation Failed',
        message: error,
        timeout: 10000
    })
}

const copyApiExample = async () => {
    const example = `// Generate PDF from Vue component
const response = await axios.post('/api/vue-pdf/generate', {
  component_route: 'charts.sentiment-price',
  data: {
    chart_data: {
      sentiment_timeline: [
        {"x": "2025-01-01", "y": 0.2},
        {"x": "2025-01-02", "y": 0.3}
      ],
      price_timeline: [
        {"x": "2025-01-01", "y": 50000},
        {"x": "2025-01-02", "y": 51000}
      ]
    },
    coin_symbol: 'BTC'
  },
  options: {
    format: 'A4',
    orientation: 'landscape',
    engine: 'auto'
  }
})

// Download the generated PDF
window.open(response.data.result.download_url)`
    
    await navigator.clipboard.writeText(example)
    showCopiedState()
}

const copyCurlExample = async () => {
    const example = `curl -X POST ${appUrl.value}/api/vue-pdf/generate \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{
    "component_route": "charts.sentiment-price",
    "data": {
      "chart_data": {
        "sentiment_timeline": [
          {"x": "2025-01-01", "y": 0.2},
          {"x": "2025-01-02", "y": 0.3}
        ],
        "price_timeline": [
          {"x": "2025-01-01", "y": 50000},
          {"x": "2025-01-02", "y": 51000}
        ]
      }
    },
    "options": {
      "format": "A4",
      "orientation": "landscape",
      "filename": "sentiment-chart.pdf"
    }
  }'`
    
    await navigator.clipboard.writeText(example)
    showCopiedState()
}

const showCopiedState = () => {
    copied.value = true
    setTimeout(() => {
        copied.value = false
    }, 2000)
}

const addNotification = (notification) => {
    const id = Date.now()
    const newNotification = { ...notification, id }
    
    notifications.value.push(newNotification)
    
    if (notification.timeout) {
        setTimeout(() => {
            removeNotification(id)
        }, notification.timeout)
    }
}

const removeNotification = (id) => {
    const index = notifications.value.findIndex(n => n.id === id)
    if (index > -1) {
        notifications.value.splice(index, 1)
    }
}
</script>

<style scoped>
/* Custom scrollbar for code blocks */
pre {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 12px;
    line-height: 1.4;
}

/* Smooth transitions */
.transition-colors {
    transition-property: color, background-color, border-color;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

/* Code block styling */
code {
    white-space: pre-wrap;
    word-break: break-word;
}

/* Notification styling */
.notification {
    @apply fixed top-4 right-4 z-50 max-w-sm w-full;
}

.notification-enter-active,
.notification-leave-active {
    transition: all 0.3s ease;
}

.notification-enter-from,
.notification-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>
