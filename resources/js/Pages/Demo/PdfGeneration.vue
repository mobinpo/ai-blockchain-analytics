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
                                    📄 PDF Generation System
                                </h1>
                                <p class="mt-2 text-lg text-gray-600 dark:text-gray-400">
                                    Convert Vue components to high-quality PDFs using Browserless or DomPDF
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Service Status</div>
                                <div class="text-lg font-semibold" :class="serviceHealthy ? 'text-green-600' : 'text-yellow-600'">
                                    {{ serviceHealthy ? '🟢 Operational' : '🟡 Limited' }}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Service Status -->
                        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-blue-900 dark:text-blue-100">Browserless</div>
                                        <div class="text-xs" :class="serviceStatus?.browserless?.healthy ? 'text-green-600' : 'text-red-600'">
                                            {{ serviceStatus?.browserless?.healthy ? 'Available' : 'Unavailable' }}
                                        </div>
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
                                        <div class="text-sm font-medium text-green-900 dark:text-green-100">DomPDF</div>
                                        <div class="text-xs" :class="serviceStatus?.dompdf?.available ? 'text-green-600' : 'text-red-600'">
                                            {{ serviceStatus?.dompdf?.available ? 'Available' : 'Unavailable' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900/40 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-purple-900 dark:text-purple-100">Storage</div>
                                        <div class="text-xs" :class="serviceStatus?.storage?.writable ? 'text-green-600' : 'text-red-600'">
                                            {{ serviceStatus?.storage?.writable ? 'Writable' : 'Read-only' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Generate PDF Forms -->
                    <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            🚀 Quick PDF Generation
                        </h3>
                        
                        <!-- Sentiment Timeline PDF -->
                        <div class="space-y-4">
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Sentiment Price Timeline</h4>
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Cryptocurrency
                                        </label>
                                        <select v-model="sentimentForm.coin" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                            <option value="bitcoin">Bitcoin (BTC)</option>
                                            <option value="ethereum">Ethereum (ETH)</option>
                                            <option value="cardano">Cardano (ADA)</option>
                                            <option value="solana">Solana (SOL)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Days
                                        </label>
                                        <select v-model="sentimentForm.days" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                            <option value="7">7 Days</option>
                                            <option value="30">30 Days</option>
                                            <option value="90">90 Days</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 mb-4">
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="sentimentForm.include_volume"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                        >
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Include Volume</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="sentimentForm.landscape"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                        >
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Landscape</span>
                                    </label>
                                </div>
                                <button
                                    @click="generateSentimentTimelinePdf"
                                    :disabled="generatingPdf"
                                    class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 transition-colors"
                                >
                                    {{ generatingPdf ? 'Generating...' : '📊 Generate Timeline PDF' }}
                                </button>
                            </div>

                            <!-- Dashboard PDF -->
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Dashboard Report</h4>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Report Title
                                    </label>
                                    <input
                                        v-model="dashboardForm.title"
                                        type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                        placeholder="Analytics Dashboard Report"
                                    >
                                </div>
                                <div class="flex items-center mb-4">
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="dashboardForm.include_charts"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                        >
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Include Charts</span>
                                    </label>
                                </div>
                                <button
                                    @click="generateDashboardPdf"
                                    :disabled="generatingPdf"
                                    class="w-full px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 disabled:opacity-50 transition-colors"
                                >
                                    {{ generatingPdf ? 'Generating...' : '📈 Generate Dashboard PDF' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Options -->
                    <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            ⚙️ Advanced Generation
                        </h3>
                        
                        <!-- Custom Route PDF -->
                        <div class="space-y-4">
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Custom Route</h4>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Vue Route
                                    </label>
                                    <select v-model="customForm.route" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                        <option value="">Select a route...</option>
                                        <option v-for="route in availableRoutes" :key="route.route" :value="route.route">
                                            {{ route.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Format
                                        </label>
                                        <select v-model="customForm.format" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                            <option value="A4">A4</option>
                                            <option value="A3">A3</option>
                                            <option value="Letter">Letter</option>
                                            <option value="Legal">Legal</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Orientation
                                        </label>
                                        <select v-model="customForm.orientation" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                            <option value="portrait">Portrait</option>
                                            <option value="landscape">Landscape</option>
                                        </select>
                                    </div>
                                </div>
                                <button
                                    @click="generateCustomPdf"
                                    :disabled="generatingPdf || !customForm.route"
                                    class="w-full px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 disabled:opacity-50 transition-colors"
                                >
                                    {{ generatingPdf ? 'Generating...' : '🎨 Generate Custom PDF' }}
                                </button>
                            </div>

                            <!-- Component PDF -->
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Vue Component</h4>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Component Name
                                    </label>
                                    <select v-model="componentForm.component" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                        <option value="">Select a component...</option>
                                        <option v-for="component in availableComponents" :key="component.component" :value="component.component">
                                            {{ component.name }}
                                        </option>
                                    </select>
                                </div>
                                <button
                                    @click="generateComponentPdf"
                                    :disabled="generatingPdf || !componentForm.component"
                                    class="w-full px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:opacity-50 transition-colors"
                                >
                                    {{ generatingPdf ? 'Generating...' : '🧩 Generate Component PDF' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Generated PDFs -->
                <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            📁 Generated PDFs
                        </h3>
                        <div class="flex gap-2">
                            <button
                                @click="refreshFileList"
                                class="px-3 py-1 text-sm bg-ink text-gray-700 dark:bg-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            >
                                🔄 Refresh
                            </button>
                            <button
                                @click="cleanupOldFiles"
                                class="px-3 py-1 text-sm bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 rounded hover:bg-red-200 dark:hover:bg-red-800 transition-colors"
                            >
                                🗑️ Cleanup
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="loadingFiles" class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-4"></div>
                        <p class="text-gray-600 dark:text-gray-400">Loading files...</p>
                    </div>
                    
                    <div v-else-if="pdfFiles.length === 0" class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-600 dark:text-gray-400">No PDF files generated yet</p>
                    </div>
                    
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="file in pdfFiles"
                            :key="file.filename"
                            class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 hover:bg-panel dark:hover:bg-gray-700 transition-colors"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-gray-900 dark:text-white truncate">
                                    {{ file.filename }}
                                </h4>
                                <span class="text-xs px-2 py-1 rounded" :class="getMethodBadgeClass(file.method)">
                                    {{ file.method }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                <div>Size: {{ file.size_formatted }}</div>
                                <div>Modified: {{ formatDate(file.last_modified_formatted) }}</div>
                            </div>
                            <div class="flex gap-2">
                                <a
                                    :href="file.url"
                                    target="_blank"
                                    class="flex-1 px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-center transition-colors"
                                >
                                    👁️ View
                                </a>
                                <a
                                    :href="file.download_url"
                                    class="flex-1 px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200 text-center transition-colors"
                                >
                                    📥 Download
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="pdfFiles.length > 0" class="mt-4 text-sm text-gray-600 dark:text-gray-400 text-center">
                        {{ pdfFiles.length }} files • {{ fileStats?.total_size_formatted || '0 B' }} total
                    </div>
                </div>

                <!-- API Documentation -->
                <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        🔌 API Documentation
                    </h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-2">Generate from Route</h4>
                            <div class="bg-panel dark:bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <code class="text-sm text-gray-800 dark:text-gray-200">
                                    POST /enhanced-pdf/generate/route<br>
                                    {<br>
                                    &nbsp;&nbsp;"route": "sentiment-timeline-demo",<br>
                                    &nbsp;&nbsp;"data": { "coin": "bitcoin", "days": 30 },<br>
                                    &nbsp;&nbsp;"options": { "format": "A4" }<br>
                                    }
                                </code>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-2">Generate from Component</h4>
                            <div class="bg-panel dark:bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <code class="text-sm text-gray-800 dark:text-gray-200">
                                    POST /enhanced-pdf/generate/component<br>
                                    {<br>
                                    &nbsp;&nbsp;"component": "SentimentPriceChart",<br>
                                    &nbsp;&nbsp;"props": { "coinSymbol": "BTC" },<br>
                                    &nbsp;&nbsp;"options": { "orientation": "landscape" }<br>
                                    }
                                </code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

// Props from server
const props = defineProps({
    service_status: Object,
    available_routes: Array,
    available_components: Array
})

// Reactive state
const serviceStatus = ref(props.service_status || {})
const availableRoutes = ref(props.available_routes || [])
const availableComponents = ref(props.available_components || [])
const generatingPdf = ref(false)
const pdfFiles = ref([])
const loadingFiles = ref(false)
const fileStats = ref(null)

// Form data
const sentimentForm = ref({
    coin: 'bitcoin',
    days: 30,
    include_volume: false,
    landscape: false
})

const dashboardForm = ref({
    title: 'Analytics Dashboard Report',
    include_charts: true
})

const customForm = ref({
    route: '',
    format: 'A4',
    orientation: 'portrait'
})

const componentForm = ref({
    component: ''
})

// Computed
const serviceHealthy = computed(() => {
    return serviceStatus.value?.browserless?.healthy || serviceStatus.value?.dompdf?.available
})

// Methods
const generateSentimentTimelinePdf = async () => {
    generatingPdf.value = true
    
    try {
        const response = await axios.post('/enhanced-pdf/generate/sentiment-timeline', {
            coin: sentimentForm.value.coin,
            days: sentimentForm.value.days,
            include_volume: sentimentForm.value.include_volume,
            orientation: sentimentForm.value.landscape ? 'landscape' : 'portrait',
            filename: `sentiment-${sentimentForm.value.coin}-${sentimentForm.value.days}d-${new Date().toISOString().split('T')[0]}.pdf`
        })
        
        if (response.data.success) {
            showSuccess('Sentiment timeline PDF generated successfully!')
            refreshFileList()
        } else {
            showError('Failed to generate PDF: ' + response.data.error)
        }
    } catch (error) {
        showError('Error generating PDF: ' + (error.response?.data?.error || error.message))
    } finally {
        generatingPdf.value = false
    }
}

const generateDashboardPdf = async () => {
    generatingPdf.value = true
    
    try {
        const response = await axios.post('/enhanced-pdf/generate/dashboard', {
            dashboard_data: {
                title: dashboardForm.value.title,
                include_charts: dashboardForm.value.include_charts,
                generated_at: new Date().toISOString()
            },
            title: dashboardForm.value.title,
            include_charts: dashboardForm.value.include_charts,
            filename: `dashboard-${new Date().toISOString().split('T')[0]}.pdf`
        })
        
        if (response.data.success) {
            showSuccess('Dashboard PDF generated successfully!')
            refreshFileList()
        } else {
            showError('Failed to generate PDF: ' + response.data.error)
        }
    } catch (error) {
        showError('Error generating PDF: ' + (error.response?.data?.error || error.message))
    } finally {
        generatingPdf.value = false
    }
}

const generateCustomPdf = async () => {
    generatingPdf.value = true
    
    try {
        const selectedRoute = availableRoutes.value.find(r => r.route === customForm.value.route)
        const response = await axios.post('/enhanced-pdf/generate/route', {
            route: customForm.value.route,
            data: selectedRoute?.sample_data || {},
            format: customForm.value.format,
            orientation: customForm.value.orientation,
            filename: `${customForm.value.route}-${new Date().toISOString().split('T')[0]}.pdf`
        })
        
        if (response.data.success) {
            showSuccess('Custom PDF generated successfully!')
            refreshFileList()
        } else {
            showError('Failed to generate PDF: ' + response.data.error)
        }
    } catch (error) {
        showError('Error generating PDF: ' + (error.response?.data?.error || error.message))
    } finally {
        generatingPdf.value = false
    }
}

const generateComponentPdf = async () => {
    generatingPdf.value = true
    
    try {
        const selectedComponent = availableComponents.value.find(c => c.component === componentForm.value.component)
        const response = await axios.post('/enhanced-pdf/generate/component', {
            component: componentForm.value.component,
            props: selectedComponent?.sample_props || {},
            filename: `${componentForm.value.component}-${new Date().toISOString().split('T')[0]}.pdf`
        })
        
        if (response.data.success) {
            showSuccess('Component PDF generated successfully!')
            refreshFileList()
        } else {
            showError('Failed to generate PDF: ' + response.data.error)
        }
    } catch (error) {
        showError('Error generating PDF: ' + (error.response?.data?.error || error.message))
    } finally {
        generatingPdf.value = false
    }
}

const refreshFileList = async () => {
    loadingFiles.value = true
    
    try {
        const response = await axios.get('/enhanced-pdf/files')
        if (response.data.success) {
            pdfFiles.value = response.data.data.files
            fileStats.value = response.data.data
        }
    } catch (error) {
        showError('Failed to load file list: ' + error.message)
    } finally {
        loadingFiles.value = false
    }
}

const cleanupOldFiles = async () => {
    if (!confirm('This will delete PDF files older than 7 days. Continue?')) {
        return
    }
    
    try {
        const response = await axios.delete('/enhanced-pdf/cleanup', {
            data: { days_old: 7 }
        })
        
        if (response.data.success) {
            showSuccess(`Cleaned up ${response.data.data.files_deleted} files (${response.data.data.size_freed_formatted} freed)`)
            refreshFileList()
        }
    } catch (error) {
        showError('Failed to cleanup files: ' + error.message)
    }
}

const getMethodBadgeClass = (method) => {
    const classes = {
        'browserless': 'bg-blue-100 text-blue-800',
        'dompdf': 'bg-green-100 text-green-800',
        'basic_dompdf': 'bg-yellow-100 text-yellow-800'
    }
    return classes[method] || 'bg-ink text-gray-800'
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString()
}

const showSuccess = (message) => {
    // Simple success notification - could be replaced with a toast library
    alert('✅ ' + message)
}

const showError = (message) => {
    // Simple error notification - could be replaced with a toast library
    alert('❌ ' + message)
}

// Lifecycle
onMounted(() => {
    refreshFileList()
})
</script>

<style scoped>
/* Custom scrollbar for code blocks */
code {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

/* Smooth transitions */
.transition-colors {
    transition-property: color, background-color, border-color;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>