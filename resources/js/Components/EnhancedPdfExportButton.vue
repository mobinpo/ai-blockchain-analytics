<template>
    <div class="enhanced-pdf-export">
        <!-- Main Export Button -->
        <div class="relative">
            <button
                @click="showOptions ? hideOptions() : showExportOptions()"
                :disabled="isExporting"
                :class="buttonClasses"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2"
            >
                <svg v-if="isExporting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                
                <span>{{ isExporting ? exportingText : buttonText }}</span>
                
                <svg v-if="!isExporting && !hideDropdownIcon" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': showOptions }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <!-- Export Options Dropdown -->
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="transform opacity-0 scale-95"
                enter-to-class="transform opacity-100 scale-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform opacity-0 scale-95"
            >
                <div
                    v-if="showOptions"
                    :class="dropdownClasses"
                    class="absolute z-50 mt-2 bg-white dark:bg-panel rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden"
                >
                    <!-- Quick Export Options -->
                    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Quick Export</h4>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                @click="quickExport('A4', 'portrait')"
                                :disabled="isExporting"
                                class="px-3 py-2 text-xs bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors disabled:opacity-50"
                            >
                                📄 A4 Portrait
                            </button>
                            <button
                                @click="quickExport('A4', 'landscape')"
                                :disabled="isExporting"
                                class="px-3 py-2 text-xs bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 rounded hover:bg-green-100 dark:hover:bg-green-900/40 transition-colors disabled:opacity-50"
                            >
                                📄 A4 Landscape
                            </button>
                        </div>
                    </div>

                    <!-- Advanced Options -->
                    <div class="p-3">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Advanced Options</h4>
                        
                        <!-- Engine Selection -->
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Engine
                            </label>
                            <select
                                v-model="selectedEngine"
                                class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            >
                                <option value="auto">Auto (Browserless → DomPDF)</option>
                                <option value="browserless">Browserless (High Quality)</option>
                                <option value="dompdf">DomPDF (Fast)</option>
                            </select>
                        </div>

                        <!-- Format and Orientation -->
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Format
                                </label>
                                <select
                                    v-model="selectedFormat"
                                    class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                >
                                    <option value="A4">A4</option>
                                    <option value="A3">A3</option>
                                    <option value="Letter">Letter</option>
                                    <option value="Legal">Legal</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Orientation
                                </label>
                                <select
                                    v-model="selectedOrientation"
                                    class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                >
                                    <option value="portrait">Portrait</option>
                                    <option value="landscape">Landscape</option>
                                </select>
                            </div>
                        </div>

                        <!-- Quality and Options -->
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Quality
                            </label>
                            <select
                                v-model="selectedQuality"
                                class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            >
                                <option value="high">High (Slower)</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low (Faster)</option>
                            </select>
                        </div>

                        <!-- Additional Options -->
                        <div class="space-y-2 mb-3">
                            <label class="flex items-center text-xs">
                                <input
                                    type="checkbox"
                                    v-model="includeBackground"
                                    class="w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                                >
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Include Background</span>
                            </label>
                            <label class="flex items-center text-xs">
                                <input
                                    type="checkbox"
                                    v-model="waitForCharts"
                                    class="w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                                >
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Wait for Charts</span>
                            </label>
                            <label class="flex items-center text-xs">
                                <input
                                    type="checkbox"
                                    v-model="includeMetadata"
                                    class="w-3 h-3 text-blue-600 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                                >
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Include Metadata</span>
                            </label>
                        </div>

                        <!-- Custom Filename -->
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Filename (optional)
                            </label>
                            <input
                                type="text"
                                v-model="customFilename"
                                placeholder="auto-generated"
                                class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500"
                            >
                        </div>

                        <!-- Export Actions -->
                        <div class="flex gap-2">
                            <button
                                @click="exportWithOptions"
                                :disabled="isExporting"
                                class="flex-1 px-3 py-2 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 transition-colors"
                            >
                                {{ isExporting ? 'Generating...' : 'Generate PDF' }}
                            </button>
                            <button
                                @click="previewBeforeExport"
                                :disabled="isExporting"
                                class="px-3 py-2 text-xs bg-gray-600 text-white rounded hover:bg-gray-700 disabled:opacity-50 transition-colors"
                            >
                                👁️
                            </button>
                        </div>
                    </div>

                    <!-- Recent Exports -->
                    <div v-if="recentExports.length > 0" class="border-t border-gray-200 dark:border-gray-700 p-3">
                        <h4 class="text-xs font-medium text-gray-900 dark:text-white mb-2">Recent Exports</h4>
                        <div class="space-y-1 max-h-24 overflow-y-auto">
                            <div
                                v-for="export_ in recentExports.slice(0, 3)"
                                :key="export_.id"
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="text-gray-600 dark:text-gray-400 truncate">
                                    {{ export_.filename }}
                                </span>
                                <div class="flex gap-1 ml-2">
                                    <a
                                        :href="export_.url"
                                        target="_blank"
                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200"
                                        title="View"
                                    >
                                        👁️
                                    </a>
                                    <a
                                        :href="export_.download_url"
                                        class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200"
                                        title="Download"
                                    >
                                        📥
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <!-- Export Progress Modal -->
        <teleport to="body">
            <transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showProgressModal"
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[100] p-4"
                    @click="hideProgressModal"
                >
                    <div
                        @click.stop
                        class="bg-white dark:bg-panel rounded-lg p-6 max-w-md w-full shadow-xl"
                    >
                        <div class="text-center">
                            <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-blue-600 dark:text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                Generating PDF
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                {{ progressMessage }}
                            </p>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div
                                    class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                    :style="{ width: progressPercentage + '%' }"
                                ></div>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                {{ progressPercentage }}% complete
                            </p>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

// Props
const props = defineProps({
    // Export Configuration
    exportType: {
        type: String,
        default: 'component',
        validator: value => ['component', 'route', 'dashboard', 'sentiment-timeline'].includes(value)
    },
    exportTarget: {
        type: String,
        required: true
    },
    exportData: {
        type: Object,
        default: () => ({})
    },
    
    // Button Styling
    variant: {
        type: String,
        default: 'primary',
        validator: value => ['primary', 'secondary', 'outline', 'ghost'].includes(value)
    },
    size: {
        type: String,
        default: 'medium',
        validator: value => ['small', 'medium', 'large'].includes(value)
    },
    buttonText: {
        type: String,
        default: 'Export PDF'
    },
    exportingText: {
        type: String,
        default: 'Generating PDF...'
    },
    hideDropdownIcon: {
        type: Boolean,
        default: false
    },
    
    // Dropdown Configuration
    dropdownPosition: {
        type: String,
        default: 'right',
        validator: value => ['left', 'right', 'center'].includes(value)
    },
    
    // Default Options
    defaultEngine: {
        type: String,
        default: 'auto'
    },
    defaultFormat: {
        type: String,
        default: 'A4'
    },
    defaultOrientation: {
        type: String,
        default: 'portrait'
    },
    defaultQuality: {
        type: String,
        default: 'high'
    }
})

// Emits
const emit = defineEmits([
    'export-started',
    'export-completed',
    'export-failed',
    'export-progress',
    'options-opened',
    'options-closed'
])

// Reactive state
const isExporting = ref(false)
const showOptions = ref(false)
const showProgressModal = ref(false)
const progressPercentage = ref(0)
const progressMessage = ref('Initializing...')

// Export options
const selectedEngine = ref(props.defaultEngine)
const selectedFormat = ref(props.defaultFormat)
const selectedOrientation = ref(props.defaultOrientation)
const selectedQuality = ref(props.defaultQuality)
const includeBackground = ref(true)
const waitForCharts = ref(true)
const includeMetadata = ref(true)
const customFilename = ref('')

// Recent exports
const recentExports = ref([])

// Computed classes
const buttonClasses = computed(() => {
    const baseClasses = 'transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2'
    
    const variantClasses = {
        primary: 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
        secondary: 'bg-gray-600 hover:bg-gray-700 text-white focus:ring-gray-500',
        outline: 'border-2 border-blue-600 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 focus:ring-blue-500',
        ghost: 'text-gray-600 hover:bg-ink dark:text-gray-400 dark:hover:bg-panel focus:ring-gray-500'
    }
    
    const sizeClasses = {
        small: 'px-3 py-1.5 text-sm',
        medium: 'px-4 py-2 text-base',
        large: 'px-6 py-3 text-lg'
    }
    
    const disabledClasses = isExporting.value ? 'opacity-50 cursor-not-allowed' : ''
    
    return [
        baseClasses,
        variantClasses[props.variant],
        sizeClasses[props.size],
        disabledClasses
    ].join(' ')
})

const dropdownClasses = computed(() => {
    const positionClasses = {
        left: 'left-0',
        right: 'right-0',
        center: 'left-1/2 transform -translate-x-1/2'
    }
    
    return [
        'w-80',
        positionClasses[props.dropdownPosition]
    ].join(' ')
})

// Methods
const showExportOptions = () => {
    showOptions.value = true
    emit('options-opened')
}

const hideOptions = () => {
    showOptions.value = false
    emit('options-closed')
}

const quickExport = async (format, orientation) => {
    selectedFormat.value = format
    selectedOrientation.value = orientation
    await exportWithOptions()
}

const exportWithOptions = async () => {
    if (isExporting.value) return
    
    hideOptions()
    isExporting.value = true
    showProgressModal.value = true
    progressPercentage.value = 0
    progressMessage.value = 'Preparing export...'
    
    emit('export-started', {
        engine: selectedEngine.value,
        format: selectedFormat.value,
        orientation: selectedOrientation.value,
        quality: selectedQuality.value
    })
    
    try {
        // Simulate progress updates
        const progressInterval = setInterval(() => {
            if (progressPercentage.value < 90) {
                progressPercentage.value += Math.random() * 20
                
                if (progressPercentage.value > 30) {
                    progressMessage.value = 'Rendering content...'
                }
                if (progressPercentage.value > 60) {
                    progressMessage.value = 'Generating PDF...'
                }
            }
        }, 500)
        
        const options = {
            engine: selectedEngine.value,
            format: selectedFormat.value,
            orientation: selectedOrientation.value,
            quality: selectedQuality.value,
            include_background: includeBackground.value,
            wait_for_charts: waitForCharts.value,
            include_metadata: includeMetadata.value,
            filename: customFilename.value || undefined
        }
        
        let endpoint = '/enhanced-pdf/generate/'
        let payload = {
            ...options,
            data: props.exportData
        }
        
        // Determine endpoint based on export type
        switch (props.exportType) {
            case 'route':
                endpoint += 'route'
                payload.route = props.exportTarget
                break
            case 'component':
                endpoint += 'component'
                payload.component = props.exportTarget
                payload.props = props.exportData
                break
            case 'sentiment-timeline':
                endpoint += 'sentiment-timeline'
                payload = { ...payload, ...props.exportData }
                break
            case 'dashboard':
                endpoint += 'dashboard'
                payload.dashboard_data = props.exportData
                break
            default:
                endpoint += 'component'
                payload.component = props.exportTarget
        }
        
        const response = await axios.post(endpoint, payload)
        
        clearInterval(progressInterval)
        progressPercentage.value = 100
        progressMessage.value = 'PDF generated successfully!'
        
        if (response.data.success) {
            // Add to recent exports
            const exportResult = response.data.data
            recentExports.value.unshift({
                id: Date.now(),
                filename: exportResult.filename,
                url: exportResult.url,
                download_url: exportResult.download_url,
                created_at: new Date().toISOString(),
                ...exportResult
            })
            
            // Keep only the last 10 exports
            if (recentExports.value.length > 10) {
                recentExports.value = recentExports.value.slice(0, 10)
            }
            
            // Save to localStorage
            localStorage.setItem('pdf_exports', JSON.stringify(recentExports.value))
            
            emit('export-completed', exportResult)
            
            // Hide modal after delay
            setTimeout(() => {
                hideProgressModal()
            }, 1500)
        } else {
            throw new Error(response.data.error || 'Export failed')
        }
        
    } catch (error) {
        progressMessage.value = 'Export failed!'
        emit('export-failed', error.response?.data?.error || error.message)
        
        setTimeout(() => {
            hideProgressModal()
        }, 2000)
    } finally {
        isExporting.value = false
    }
}

const previewBeforeExport = async () => {
    try {
        // Generate preview token first
        const response = await fetch('/enhanced-pdf/preview/token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                route: props.exportTarget,
                data: {} // Add any required data here
            })
        })
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }
        
        const result = await response.json()
        
        if (result.success && result.data.preview_url) {
            // Open preview in new tab using the generated URL
            window.open(result.data.preview_url, '_blank')
        } else {
            throw new Error(result.message || 'Failed to generate preview token')
        }
    } catch (error) {
        console.error('Preview generation failed:', error)
        alert('Failed to generate preview. Please try again.')
    }
}

const hideProgressModal = () => {
    showProgressModal.value = false
    progressPercentage.value = 0
    progressMessage.value = 'Initializing...'
}

// Click outside to close dropdown
const handleClickOutside = (event) => {
    if (showOptions.value && !event.target.closest('.enhanced-pdf-export')) {
        hideOptions()
    }
}

// Lifecycle
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    
    // Load recent exports from localStorage
    const savedExports = localStorage.getItem('pdf_exports')
    if (savedExports) {
        try {
            recentExports.value = JSON.parse(savedExports)
        } catch (e) {
            console.warn('Failed to parse saved PDF exports')
        }
    }
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
/* Custom scrollbar for recent exports */
.enhanced-pdf-export ::-webkit-scrollbar {
    width: 4px;
}

.enhanced-pdf-export ::-webkit-scrollbar-track {
    @apply bg-ink dark:bg-gray-700 rounded;
}

.enhanced-pdf-export ::-webkit-scrollbar-thumb {
    @apply bg-gray-300 dark:bg-gray-600 rounded;
}

.enhanced-pdf-export ::-webkit-scrollbar-thumb:hover {
    @apply bg-gray-400 dark:bg-panel;
}

/* Focus styles for better accessibility */
.enhanced-pdf-export button:focus-visible {
    @apply ring-2 ring-offset-2 ring-blue-500;
}

.enhanced-pdf-export select:focus,
.enhanced-pdf-export input:focus {
    @apply ring-2 ring-blue-500 border-blue-500;
}

/* Animation for progress bar */
.enhanced-pdf-export .bg-blue-600 {
    transition: width 0.3s ease-in-out;
}

/* Hover effects */
.enhanced-pdf-export .hover\\:bg-blue-100:hover {
    background-color: rgb(219 234 254);
}

.dark .enhanced-pdf-export .dark\\:hover\\:bg-blue-900\\/40:hover {
    background-color: rgb(30 58 138 / 0.4);
}
</style>
