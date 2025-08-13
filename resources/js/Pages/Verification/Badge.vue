<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 py-12">
        <Head :title="`Verification Badge - ${badge.entity_type} ${badge.entity_id}`" />
        
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="flex items-center justify-center mb-4">
                    <div class="h-12 w-12 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-2">
                    Verification Badge
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    This badge provides cryptographic proof of verification using SHA-256 + HMAC signatures.
                </p>
            </div>

            <!-- Main Badge Display -->
            <div class="mb-8">
                <VerificationBadge 
                    :token="token"
                    :badge-data="badge"
                    size="large"
                    :show-details="true"
                    :show-actions="true"
                    :auto-verify="false"
                    @verified="handleVerified"
                    @error="handleError"
                    @copied="handleCopied"
                />
            </div>

            <!-- Verification Information -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Technical Details -->
                <div class="bg-white dark:bg-panel rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <svg class="h-5 w-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Technical Details
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Signature Algorithm:</span>
                            <span class="font-mono text-gray-900 dark:text-white">SHA-256 + HMAC</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Entity Type:</span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ badge.entity_type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Badge Type:</span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ badge.badge_type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Issued At:</span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ formatDate(badge.issued_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Expires At:</span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ formatDate(badge.expires_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Token Preview:</span>
                            <span class="font-mono text-gray-900 dark:text-white text-xs">{{ tokenPreview }}</span>
                        </div>
                    </div>
                </div>

                <!-- Verification Status -->
                <div class="bg-white dark:bg-panel rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Verification Status
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="h-3 w-3 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-900 dark:text-white">Signature Valid</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="h-3 w-3 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-900 dark:text-white">Badge Active</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div :class="expiryStatusColor" class="h-3 w-3 rounded-full"></div>
                            <span class="text-sm text-gray-900 dark:text-white">{{ expiryStatus }}</span>
                        </div>
                        <div v-if="badge.metadata && badge.metadata.verification_level" class="flex items-center space-x-3">
                            <div :class="verificationLevelColor" class="h-3 w-3 rounded-full"></div>
                            <span class="text-sm text-gray-900 dark:text-white">
                                Verification Level: {{ badge.metadata.verification_level.toUpperCase() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Embed Code -->
            <div class="bg-white dark:bg-panel rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                    <svg class="h-5 w-5 text-purple-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                    </svg>
                    Embed This Badge
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Use these code snippets to embed this verification badge on your website:
                </p>
                
                <div class="space-y-4">
                    <!-- HTML Embed -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            HTML Embed (Badge Image)
                        </label>
                        <div class="relative">
                            <textarea 
                                v-model="htmlEmbedCode" 
                                readonly 
                                class="w-full p-3 bg-panel dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg font-mono text-sm"
                                rows="3"
                            ></textarea>
                            <button 
                                @click="copyToClipboard(htmlEmbedCode, 'HTML embed')"
                                class="absolute top-2 right-2 p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Markdown Embed -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Markdown (README files)
                        </label>
                        <div class="relative">
                            <textarea 
                                v-model="markdownEmbedCode" 
                                readonly 
                                class="w-full p-3 bg-panel dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg font-mono text-sm"
                                rows="2"
                            ></textarea>
                            <button 
                                @click="copyToClipboard(markdownEmbedCode, 'Markdown embed')"
                                class="absolute top-2 right-2 p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Direct URL -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Direct Badge URL
                        </label>
                        <div class="relative">
                            <input 
                                v-model="badgeUrl" 
                                readonly 
                                class="w-full p-3 bg-panel dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg font-mono text-sm"
                            />
                            <button 
                                @click="copyToClipboard(badgeUrl, 'Badge URL')"
                                class="absolute top-2 right-2 p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row sm:justify-center sm:space-x-4 space-y-4 sm:space-y-0">
                <Link 
                    :href="verificationUrl"
                    class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Verify This Badge
                </Link>
                
                <button 
                    @click="downloadBadge"
                    class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-panel hover:bg-panel dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download Badge
                </button>
            </div>

            <!-- Security Notice -->
            <div class="mt-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-2">
                            Security Information
                        </h4>
                        <div class="text-sm text-blue-700 dark:text-blue-300 space-y-2">
                            <p>
                                This verification badge uses cryptographic signatures (SHA-256 + HMAC) to prevent spoofing and tampering.
                            </p>
                            <p>
                                Each badge contains a unique token that can be independently verified using our API.
                                The signature ensures that the badge data has not been modified since issuance.
                            </p>
                            <p>
                                Always verify badges through the official verification URL or API endpoint to ensure authenticity.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Toast -->
        <div v-if="showToast" class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-bounce">
            ✅ {{ toastMessage }}
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import VerificationBadge from '@/Components/VerificationBadge.vue'

// Props
const props = defineProps({
    badge: {
        type: Object,
        required: true
    },
    token: {
        type: String,
        required: true
    },
    verification_url: {
        type: String,
        required: true
    }
})

// Reactive state
const showToast = ref(false)
const toastMessage = ref('')

// Computed properties
const tokenPreview = computed(() => {
    return props.token.substring(0, 32) + '...'
})

const expiryStatus = computed(() => {
    const expiresAt = new Date(props.badge.expires_at)
    const now = new Date()
    const hoursUntilExpiry = (expiresAt - now) / (1000 * 60 * 60)
    
    if (hoursUntilExpiry < 0) return 'Expired'
    if (hoursUntilExpiry < 24) return `Expires in ${Math.round(hoursUntilExpiry)} hours`
    if (hoursUntilExpiry < 168) return `Expires in ${Math.round(hoursUntilExpiry / 24)} days`
    return 'Valid'
})

const expiryStatusColor = computed(() => {
    const expiresAt = new Date(props.badge.expires_at)
    const now = new Date()
    const hoursUntilExpiry = (expiresAt - now) / (1000 * 60 * 60)
    
    if (hoursUntilExpiry < 0) return 'bg-red-500'
    if (hoursUntilExpiry < 24) return 'bg-yellow-500'
    return 'bg-green-500'
})

const verificationLevelColor = computed(() => {
    const level = props.badge.metadata?.verification_level
    return {
        'high': 'bg-green-500',
        'medium': 'bg-yellow-500',
        'low': 'bg-red-500'
    }[level] || 'bg-panel'
})

const badgeUrl = computed(() => {
    return `${window.location.origin}/verification/badge/${encodeURIComponent(props.token)}`
})

const embedUrl = computed(() => {
    return `${window.location.origin}/verification/embed/${encodeURIComponent(props.token)}`
})

const verificationUrl = computed(() => {
    return props.verification_url
})

const htmlEmbedCode = computed(() => {
    return `<a href="${badgeUrl.value}" target="_blank">
  <img src="${embedUrl.value}" alt="Verification Badge" style="max-width: 200px;" />
</a>`
})

const markdownEmbedCode = computed(() => {
    return `[![Verification Badge](${embedUrl.value})](${badgeUrl.value})`
})

// Methods
const formatDate = (dateString) => {
    try {
        return new Date(dateString).toLocaleString()
    } catch {
        return 'Invalid date'
    }
}

const copyToClipboard = async (text, type) => {
    try {
        await navigator.clipboard.writeText(text)
        showToast.value = true
        toastMessage.value = `${type} copied to clipboard!`
        setTimeout(() => {
            showToast.value = false
        }, 3000)
    } catch (err) {
        console.error('Failed to copy:', err)
    }
}

const downloadBadge = () => {
    const link = document.createElement('a')
    link.href = embedUrl.value
    link.download = `verification-badge-${props.badge.entity_id}.svg`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}

const handleVerified = (data) => {
    console.log('Badge verified:', data)
}

const handleError = (error) => {
    console.error('Badge error:', error)
}

const handleCopied = (url) => {
    showToast.value = true
    toastMessage.value = 'Badge URL copied to clipboard!'
    setTimeout(() => {
        showToast.value = false
    }, 3000)
}
</script>

<style scoped>
/* Custom animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeInUp {
    animation: fadeInUp 0.6s ease-out;
}

/* Custom scrollbar for textareas */
textarea::-webkit-scrollbar {
    width: 8px;
}

textarea::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

textarea::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

textarea::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Print styles */
@media print {
    .fixed {
        display: none !important;
    }
    
    .bg-gradient-to-br {
        background: white !important;
    }
    
    .shadow-lg {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>
