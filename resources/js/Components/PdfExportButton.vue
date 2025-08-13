<template>
    <div class="pdf-export-component">
        <!-- Export Button -->
        <button
            @click="exportToPdf"
            :disabled="isGenerating"
            :class="buttonClasses"
            class="pdf-export-btn"
        >
            <Icon 
                v-if="!isGenerating" 
                name="download" 
                class="w-4 h-4 mr-2" 
            />
            <Icon 
                v-else 
                name="spinner" 
                class="w-4 h-4 mr-2 animate-spin" 
            />
            {{ buttonText }}
        </button>

        <!-- Export Options Modal -->
        <div 
            v-if="showOptions" 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click="closeModal"
        >
            <div 
                class="bg-white rounded-lg p-6 w-full max-w-md mx-4"
                @click.stop
            >
                <h3 class="text-lg font-semibold mb-4">PDF Export Options</h3>
                
                <form @submit.prevent="handleExport">
                    <div class="space-y-4">
                        <!-- Format Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Page Format
                            </label>
                            <select 
                                v-model="exportOptions.format"
                                class="w-full border border-gray-300 rounded-md px-3 py-2"
                            >
                                <option value="A4">A4</option>
                                <option value="A3">A3</option>
                                <option value="Letter">Letter</option>
                                <option value="Legal">Legal</option>
                            </select>
                        </div>

                        <!-- Orientation -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Orientation
                            </label>
                            <div class="flex space-x-4">
                                <label class="flex items-center">
                                    <input 
                                        v-model="exportOptions.orientation" 
                                        type="radio" 
                                        value="portrait"
                                        class="mr-2"
                                    >
                                    Portrait
                                </label>
                                <label class="flex items-center">
                                    <input 
                                        v-model="exportOptions.orientation" 
                                        type="radio" 
                                        value="landscape"
                                        class="mr-2"
                                    >
                                    Landscape
                                </label>
                            </div>
                        </div>

                        <!-- Filename -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Filename
                            </label>
                            <input 
                                v-model="exportOptions.filename"
                                type="text"
                                class="w-full border border-gray-300 rounded-md px-3 py-2"
                                placeholder="Enter filename (without .pdf)"
                            >
                        </div>

                        <!-- Include Charts -->
                        <div v-if="hasCharts">
                            <label class="flex items-center">
                                <input 
                                    v-model="exportOptions.includeCharts" 
                                    type="checkbox"
                                    class="mr-2"
                                >
                                Include interactive charts (slower generation)
                            </label>
                        </div>

                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Document Title
                            </label>
                            <input 
                                v-model="exportOptions.title"
                                type="text"
                                class="w-full border border-gray-300 rounded-md px-3 py-2"
                                placeholder="Enter document title"
                            >
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 text-gray-600 hover:text-gray-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isGenerating"
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            Generate PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Progress Modal -->
        <div 
            v-if="isGenerating" 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4 text-center">
                <Icon name="spinner" class="w-8 h-8 animate-spin mx-auto mb-4 text-blue-600" />
                <h3 class="text-lg font-semibold mb-2">Generating PDF</h3>
                <p class="text-gray-600 mb-4">{{ progressMessage }}</p>
                
                <div v-if="estimatedTime > 0" class="text-sm text-gray-500">
                    Estimated time: {{ estimatedTime }}s
                </div>

                <div class="w-full bg-gray-200 rounded-full h-2 mt-4">
                    <div 
                        class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                        :style="{ width: progress + '%' }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <div 
            v-if="message" 
            :class="messageClasses"
            class="fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 max-w-sm"
        >
            <div class="flex items-center">
                <Icon 
                    :name="messageType === 'success' ? 'check-circle' : 'x-circle'" 
                    class="w-5 h-5 mr-2" 
                />
                <span>{{ message }}</span>
            </div>
            
            <div v-if="downloadUrl && messageType === 'success'" class="mt-2">
                <a 
                    :href="downloadUrl" 
                    download
                    class="text-sm underline hover:no-underline"
                >
                    Download PDF
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Icon from './Icon.vue'

const props = defineProps({
    componentRoute: {
        type: String,
        required: true
    },
    data: {
        type: Object,
        default: () => ({})
    },
    showOptionsDialog: {
        type: Boolean,
        default: true
    },
    defaultOptions: {
        type: Object,
        default: () => ({})
    },
    hasCharts: {
        type: Boolean,
        default: false
    },
    variant: {
        type: String,
        default: 'primary', // primary, secondary, outline
        validator: (value) => ['primary', 'secondary', 'outline'].includes(value)
    },
    size: {
        type: String,
        default: 'md', // sm, md, lg
        validator: (value) => ['sm', 'md', 'lg'].includes(value)
    }
})

const emit = defineEmits(['export-started', 'export-completed', 'export-failed'])

// Reactive state
const isGenerating = ref(false)
const showOptions = ref(false)
const message = ref('')
const messageType = ref('success')
const downloadUrl = ref('')
const progress = ref(0)
const progressMessage = ref('')
const estimatedTime = ref(0)

// Export options
const exportOptions = ref({
    format: 'A4',
    orientation: 'portrait',
    filename: '',
    title: '',
    includeCharts: true,
    ...props.defaultOptions
})

// Computed properties
const buttonText = computed(() => {
    if (isGenerating.value) {
        return 'Generating...'
    }
    return 'Export PDF'
})

const buttonClasses = computed(() => {
    const baseClasses = 'inline-flex items-center justify-center font-medium rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed'
    
    const sizeClasses = {
        sm: 'px-3 py-1.5 text-sm',
        md: 'px-4 py-2 text-sm',
        lg: 'px-6 py-3 text-base'
    }
    
    const variantClasses = {
        primary: 'bg-blue-600 text-white hover:bg-blue-700',
        secondary: 'bg-gray-600 text-white hover:bg-gray-700',
        outline: 'border border-gray-300 text-gray-700 hover:bg-panel'
    }
    
    return `${baseClasses} ${sizeClasses[props.size]} ${variantClasses[props.variant]}`
})

const messageClasses = computed(() => {
    const baseClasses = 'text-white'
    return messageType.value === 'success' 
        ? `${baseClasses} bg-green-600`
        : `${baseClasses} bg-red-600`
})

// Methods
const exportToPdf = () => {
    if (props.showOptionsDialog) {
        showOptions.value = true
    } else {
        handleExport()
    }
}

const closeModal = () => {
    showOptions.value = false
}

const handleExport = async () => {
    showOptions.value = false
    isGenerating.value = true
    progress.value = 0
    progressMessage.value = 'Preparing data...'
    estimatedTime.value = props.hasCharts ? 45 : 15

    emit('export-started')

    try {
        // Simulate progress updates
        const progressInterval = setInterval(() => {
            if (progress.value < 90) {
                progress.value += Math.random() * 10
                updateProgressMessage()
            }
        }, 1000)

        const response = await fetch('/api/vue-pdf/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                component_route: props.componentRoute,
                data: props.data,
                options: {
                    ...exportOptions.value,
                    chart_rendering: props.hasCharts && exportOptions.value.includeCharts
                }
            })
        })

        clearInterval(progressInterval)
        progress.value = 100
        progressMessage.value = 'Finalizing PDF...'

        const result = await response.json()

        if (result.success) {
            downloadUrl.value = result.result.download_url
            showMessage('PDF generated successfully!', 'success')
            emit('export-completed', result.result)

            // Auto-download
            if (downloadUrl.value) {
                window.open(downloadUrl.value, '_blank')
            }
        } else {
            throw new Error(result.message || 'PDF generation failed')
        }

    } catch (error) {
        console.error('PDF generation error:', error)
        showMessage(error.message || 'Failed to generate PDF', 'error')
        emit('export-failed', error)
    } finally {
        isGenerating.value = false
        progress.value = 0
    }
}

const updateProgressMessage = () => {
    const messages = [
        'Preparing data...',
        'Rendering component...',
        'Processing charts...',
        'Generating PDF...',
        'Optimizing layout...'
    ]
    
    const messageIndex = Math.floor(progress.value / 20)
    progressMessage.value = messages[Math.min(messageIndex, messages.length - 1)]
}

const showMessage = (text, type = 'success') => {
    message.value = text
    messageType.value = type
    
    setTimeout(() => {
        message.value = ''
        downloadUrl.value = ''
    }, 5000)
}

// Initialize default filename
onMounted(() => {
    if (!exportOptions.value.filename) {
        const route = props.componentRoute.replace(/\./g, '-')
        exportOptions.value.filename = `${route}-${new Date().toISOString().split('T')[0]}`
    }
    
    if (!exportOptions.value.title) {
        exportOptions.value.title = props.componentRoute
            .split('.')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ')
    }
})
</script>

<style scoped>
.pdf-export-btn {
    transition: all 0.2s ease-in-out;
}

.pdf-export-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.pdf-export-btn:active:not(:disabled) {
    transform: translateY(0);
}

/* Fade animations */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>