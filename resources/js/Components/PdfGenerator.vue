<template>
    <div class="bg-white dark:bg-panel rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">📄 PDF Generation</h2>
            <div class="flex items-center space-x-2">
                <span 
                    :class="[
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                        serviceStatus.browserless_available 
                            ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400'
                    ]"
                >
                    {{ serviceStatus.browserless_available ? 'Browserless Available' : 'DomPDF Only' }}
                </span>
                <button
                    @click="refreshStatus"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    title="Refresh Status"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- PDF Generation Options -->
        <div class="space-y-6">
            <!-- Vue View PDF Generation -->
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">🖼️ Vue View PDF (Browserless)</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <input 
                        v-model="vueRoute" 
                        type="text" 
                        placeholder="Vue Route (e.g., pdf/sentiment-report)"
                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm"
                    >
                    <select v-model="vueOptions.format" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                        <option value="A4">A4</option>
                        <option value="Letter">Letter</option>
                        <option value="Legal">Legal</option>
                    </select>
                    <select v-model="vueOptions.orientation" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                        <option value="portrait">Portrait</option>
                        <option value="landscape">Landscape</option>
                    </select>
                    <button 
                        @click="generateVuePdf"
                        :disabled="isGenerating || !serviceStatus.browserless_available"
                        class="w-full bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-md transition-colors"
                    >
                        {{ isGenerating ? 'Generating...' : 'Generate Vue PDF' }}
                    </button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Requires Browserless service. Renders Vue components with full JavaScript support.
                </p>
            </div>

            <!-- Analytics Report -->
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">📊 Analytics Report PDF</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <input 
                        v-model="contractId" 
                        type="text" 
                        placeholder="Contract ID"
                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm"
                    >
                    <select v-model="format" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                        <option value="detailed">Detailed Analysis</option>
                        <option value="summary">Executive Summary</option>
                    </select>
                    <button 
                        @click="generateAnalyticsPdf"
                        :disabled="isGenerating"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-md transition-colors"
                    >
                        {{ isGenerating ? 'Generating...' : 'Generate PDF' }}
                    </button>
                </div>
            </div>

            <!-- Sentiment Dashboard -->
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">📈 Sentiment Dashboard PDF</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <select v-model="timeframe" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                        <option value="7d">Last Week</option>
                        <option value="30d">Last Month</option>
                        <option value="90d">Last Quarter</option>
                    </select>
                    <select v-model="sentimentPlatform" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                        <option value="all">All Platforms</option>
                        <option value="twitter">Twitter</option>
                        <option value="reddit">Reddit</option>
                        <option value="telegram">Telegram</option>
                    </select>
                    <button 
                        @click="generateSentimentPdf"
                        :disabled="isGenerating"
                        class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-md transition-colors"
                    >
                        {{ isGenerating ? 'Generating...' : 'Generate PDF' }}
                    </button>
                </div>
            </div>

            <!-- Social Media Report -->
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">📱 Social Media Report PDF</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <select v-model="socialPlatform" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                        <option value="">All Platforms</option>
                        <option value="twitter">Twitter</option>
                        <option value="reddit">Reddit</option>
                        <option value="telegram">Telegram</option>
                    </select>
                    <input 
                        v-model="keyword" 
                        type="text" 
                        placeholder="Keyword"
                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm"
                    >
                    <input 
                        v-model="days" 
                        type="number" 
                        placeholder="Days (1-365)"
                        min="1" 
                        max="365"
                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm"
                    >
                    <button 
                        @click="generateSocialPdf"
                        :disabled="isGenerating"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-md transition-colors"
                    >
                        {{ isGenerating ? 'Generating...' : 'Generate PDF' }}
                    </button>
                </div>
            </div>

            <!-- Custom HTML PDF -->
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">🔧 Custom HTML PDF</h3>
                <div class="space-y-4">
                    <textarea 
                        v-model="customHtml" 
                        placeholder="Enter HTML content..."
                        rows="4"
                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm"
                    ></textarea>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <select v-model="customOptions.format" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                            <option value="A4">A4</option>
                            <option value="Letter">Letter</option>
                            <option value="Legal">Legal</option>
                        </select>
                        <select v-model="customOptions.orientation" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                            <option value="portrait">Portrait</option>
                            <option value="landscape">Landscape</option>
                        </select>
                        <button 
                            @click="generateCustomPdf"
                            :disabled="isGenerating || !customHtml.trim()"
                            class="bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-md transition-colors"
                        >
                            {{ isGenerating ? 'Generating...' : 'Generate Custom PDF' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Test Generation -->
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">🧪 Test PDF Generation</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <select v-model="testType" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                        <option value="dashboard">Dashboard</option>
                        <option value="sentiment">Sentiment</option>
                        <option value="crawler">Crawler</option>
                    </select>
                    <select v-model="testMethod" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                        <option value="auto">Auto (Browserless → DomPDF)</option>
                        <option value="browserless" :disabled="!serviceStatus.browserless_available">Browserless Only</option>
                        <option value="dompdf">DomPDF Only</option>
                    </select>
                    <button 
                        @click="testGeneration"
                        :disabled="isGenerating"
                        class="w-full bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-md transition-colors"
                    >
                        {{ isGenerating ? 'Testing...' : 'Test Generation' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent PDFs -->
        <div v-if="recentPdfs.length > 0" class="mt-8">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">📋 Recent PDFs</h3>
            <div class="space-y-2">
                <div 
                    v-for="pdf in recentPdfs" 
                    :key="pdf.filename"
                    class="flex items-center justify-between p-3 bg-panel dark:bg-gray-700 rounded-lg"
                >
                    <div class="flex items-center space-x-3">
                        <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ pdf.filename }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(pdf.created_at) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button
                            @click="viewPdf(pdf.view_url)"
                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm"
                        >
                            View
                        </button>
                        <button
                            @click="downloadPdf(pdf.download_url, pdf.filename)"
                            class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-sm"
                        >
                            Download
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Status -->
        <div class="mt-8 p-4 bg-panel dark:bg-gray-700 rounded-lg">
            <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-2">Service Status</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Browserless:</span>
                    <span :class="serviceStatus.browserless_available ? 'text-green-600' : 'text-red-600'">
                        {{ serviceStatus.browserless_available ? 'Available' : 'Unavailable' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">DomPDF:</span>
                    <span :class="serviceStatus.dompdf_available ? 'text-green-600' : 'text-red-600'">
                        {{ serviceStatus.dompdf_available ? 'Available' : 'Unavailable' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Storage:</span>
                    <span :class="serviceStatus.storage_writable ? 'text-green-600' : 'text-red-600'">
                        {{ serviceStatus.storage_writable ? 'Writable' : 'Not Writable' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'

export default {
    name: 'PdfGenerator',
    setup() {
        // Existing state
        const contractId = ref('0x1234567890abcdef')
        const format = ref('detailed')
        const timeframe = ref('7d')
        
        // New state
        const isGenerating = ref(false)
        const vueRoute = ref('pdf/sentiment-report')
        const vueOptions = ref({
            format: 'A4',
            orientation: 'portrait'
        })
        const sentimentPlatform = ref('all')
        const socialPlatform = ref('')
        const keyword = ref('bitcoin')
        const days = ref(30)
        const customHtml = ref('<h1>Sample PDF Content</h1><p>This is a test PDF generated from custom HTML.</p>')
        const customOptions = ref({
            format: 'A4',
            orientation: 'portrait'
        })
        const testType = ref('dashboard')
        const testMethod = ref('auto')
        const recentPdfs = ref([])
        const serviceStatus = ref({
            browserless_available: false,
            dompdf_available: true,
            storage_writable: true,
            browserless_url: '',
            has_token: false
        })

        // Methods
        const refreshStatus = async () => {
            try {
                const response = await axios.get('/api/pdf/status')
                serviceStatus.value = response.data
            } catch (error) {
                console.error('Failed to refresh status:', error)
            }
        }

        const generateVuePdf = async () => {
            if (isGenerating.value) return
            isGenerating.value = true
            
            try {
                const response = await axios.post('/api/pdf/vue-view', {
                    route: vueRoute.value,
                    data: generateSampleData(),
                    options: vueOptions.value
                })
                
                if (response.data.success) {
                    addToRecentPdfs(response.data)
                    downloadPdf(response.data.download_url, response.data.filename)
                    alert('Vue PDF generated successfully!')
                } else {
                    alert('PDF generation failed: ' + response.data.error)
                }
            } catch (error) {
                console.error('Vue PDF generation failed:', error)
                alert('PDF generation failed: ' + error.message)
            } finally {
                isGenerating.value = false
            }
        }

        const generateAnalyticsPdf = async () => {
            if (isGenerating.value) return
            isGenerating.value = true
            
            try {
                const response = await axios.get(`/pdf/analytics/${contractId.value}`, {
                    params: { format: format.value }
                })
                
                if (response.data.success) {
                    addToRecentPdfs(response.data)
                    downloadPdf(response.data.file_url, response.data.filename)
                } else {
                    alert('Analytics PDF generation failed')
                }
            } catch (error) {
                console.error('Analytics PDF generation failed:', error)
                alert('PDF generation failed: ' + error.message)
            } finally {
                isGenerating.value = false
            }
        }

        const generateSentimentPdf = async () => {
            if (isGenerating.value) return
            isGenerating.value = true
            
            try {
                const response = await axios.post('/api/pdf/sentiment-report', {
                    symbol: 'BTC',
                    days: parseInt(timeframe.value.replace('d', '')),
                    platform: sentimentPlatform.value
                })
                
                if (response.data.success) {
                    addToRecentPdfs(response.data)
                    downloadPdf(response.data.download_url, response.data.filename)
                } else {
                    alert('Sentiment PDF generation failed: ' + response.data.error)
                }
            } catch (error) {
                console.error('Sentiment PDF generation failed:', error)
                alert('PDF generation failed: ' + error.message)
            } finally {
                isGenerating.value = false
            }
        }

        const generateSocialPdf = async () => {
            if (isGenerating.value) return
            isGenerating.value = true
            
            try {
                const response = await axios.post('/api/pdf/social-report', {
                    platform: socialPlatform.value,
                    keyword: keyword.value,
                    days: days.value
                })
                
                if (response.data.success) {
                    addToRecentPdfs(response.data)
                    downloadPdf(response.data.download_url, response.data.filename)
                } else {
                    alert('Social PDF generation failed: ' + response.data.error)
                }
            } catch (error) {
                console.error('Social PDF generation failed:', error)
                alert('PDF generation failed: ' + error.message)
            } finally {
                isGenerating.value = false
            }
        }

        const generateCustomPdf = async () => {
            if (isGenerating.value || !customHtml.value.trim()) return
            isGenerating.value = true
            
            try {
                const response = await axios.post('/api/pdf/html', {
                    html: customHtml.value,
                    format: customOptions.value.format,
                    orientation: customOptions.value.orientation
                })
                
                if (response.data.success) {
                    addToRecentPdfs(response.data)
                    downloadPdf(response.data.download_url, response.data.filename)
                } else {
                    alert('Custom PDF generation failed: ' + response.data.error)
                }
            } catch (error) {
                console.error('Custom PDF generation failed:', error)
                alert('PDF generation failed: ' + error.message)
            } finally {
                isGenerating.value = false
            }
        }

        const testGeneration = async () => {
            if (isGenerating.value) return
            isGenerating.value = true
            
            try {
                const response = await axios.post('/api/pdf/test', {
                    type: testType.value,
                    method: testMethod.value
                })
                
                if (response.data.success) {
                    const message = `Test successful!\nType: ${response.data.type}\nMethod: ${response.data.method}\nProcessing time: ${response.data.processing_time}ms`
                    alert(message)
                    
                    if (response.data.file_url) {
                        viewPdf(response.data.file_url)
                    }
                } else {
                    alert('Test failed: ' + response.data.error)
                }
            } catch (error) {
                console.error('Test failed:', error)
                alert('Test failed: ' + error.message)
            } finally {
                isGenerating.value = false
            }
        }

        const generateSampleData = () => {
            return {
                symbol: 'BTC',
                sentiment_score: 0.65,
                total_posts: 15420,
                platforms: ['twitter', 'reddit', 'telegram'],
                date_range: {
                    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                    end: new Date().toISOString().split('T')[0]
                }
            }
        }

        const addToRecentPdfs = (pdfData) => {
            recentPdfs.value.unshift({
                filename: pdfData.filename,
                download_url: pdfData.download_url,
                view_url: pdfData.view_url || pdfData.file_url,
                created_at: new Date().toISOString()
            })
            
            // Keep only last 10 PDFs
            if (recentPdfs.value.length > 10) {
                recentPdfs.value = recentPdfs.value.slice(0, 10)
            }
        }

        const viewPdf = (url) => {
            window.open(url, '_blank')
        }

        const downloadPdf = (url, filename) => {
            const link = document.createElement('a')
            link.href = url
            link.download = filename || 'download.pdf'
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
        }

        const formatDate = (dateString) => {
            return new Date(dateString).toLocaleString()
        }

        // Lifecycle
        onMounted(() => {
            refreshStatus()
        })

        return {
            // Existing
            contractId,
            format,
            timeframe,
            generateAnalyticsPdf,
            generateSentimentPdf,
            
            // New
            isGenerating,
            vueRoute,
            vueOptions,
            sentimentPlatform,
            socialPlatform,
            keyword,
            days,
            customHtml,
            customOptions,
            testType,
            testMethod,
            recentPdfs,
            serviceStatus,
            refreshStatus,
            generateVuePdf,
            generateSocialPdf,
            generateCustomPdf,
            testGeneration,
            viewPdf,
            downloadPdf,
            formatDate
        }
    }
}
</script>