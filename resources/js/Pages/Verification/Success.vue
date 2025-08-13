<template>
    <GuestLayout title="Verification Successful">
        <div class="min-h-screen bg-gradient-to-br from-green-50 to-blue-50 dark:from-gray-900 dark:to-gray-800 flex items-center justify-center p-4">
            <div class="max-w-2xl w-full">
                <!-- Success Card -->
                <div class="bg-white dark:bg-panel rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-green-500 to-green-600 p-8 text-center">
                        <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-12 h-12 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <h1 class="text-3xl font-bold text-white mb-2">
                            🎉 Verification Successful!
                        </h1>
                        <p class="text-green-100 text-lg">
                            Your smart contract has been successfully verified with enhanced security
                        </p>
                    </div>

                    <!-- Content -->
                    <div class="p-8">
                        <!-- Contract Information -->
                        <div class="bg-panel dark:bg-gray-700 rounded-xl p-6 mb-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                                Contract Details
                            </h2>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Contract Address:</span>
                                    <span class="font-mono text-sm bg-white dark:bg-gray-600 px-3 py-1 rounded border">
                                        {{ truncatedAddress }}
                                    </span>
                                </div>
                                
                                <div v-if="verification?.metadata?.project_name" class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Project Name:</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">
                                        {{ verification.metadata.project_name }}
                                    </span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Verified At:</span>
                                    <span class="text-gray-900 dark:text-white">
                                        {{ formattedVerificationDate }}
                                    </span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Verification Method:</span>
                                    <span class="text-blue-600 dark:text-blue-400 font-semibold">
                                        Enhanced SHA-256 + HMAC
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Verification Badge Display -->
                        <div class="bg-gradient-to-r from-blue-50 to-green-50 dark:from-gray-700 dark:to-gray-600 rounded-xl p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                Your Verification Badge
                            </h3>
                            
                            <div class="flex items-center justify-center mb-4">
                                <div class="p-4 bg-white dark:bg-panel rounded-lg shadow-sm">
                                    <EnhancedVerificationBadge
                                        :contract-address="verification?.contract_address"
                                        :auto-load="false"
                                    />
                                </div>
                            </div>
                            
                            <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                                This badge will now appear on your contract listings and can be embedded on your website
                            </p>
                        </div>

                        <!-- Security Features -->
                        <div v-if="security_features" class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                🛡️ Security Features Applied
                            </h3>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div v-if="security_features.multi_layer_protection" class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Multi-layer Signature Protection</span>
                                </div>
                                
                                <div v-if="security_features.anti_spoofing" class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Anti-spoofing Measures</span>
                                </div>
                                
                                <div v-if="security_features.replay_protection" class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Replay Attack Prevention</span>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Cryptographic Integrity</span>
                                </div>
                            </div>
                        </div>

                        <!-- Integration Instructions -->
                        <div class="bg-panel dark:bg-gray-700 rounded-xl p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                🔌 Integration Options
                            </h3>
                            
                            <div class="space-y-4">
                                <!-- Embed HTML -->
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Embed Badge HTML</h4>
                                    <div class="bg-gray-900 dark:bg-panel rounded-lg p-3 overflow-x-auto">
                                        <code class="text-green-400 text-sm">
                                            &lt;iframe src="{{ badgeEmbedUrl }}" width="200" height="50" frameborder="0"&gt;&lt;/iframe&gt;
                                        </code>
                                    </div>
                                    <button 
                                        @click="copyToClipboard(badgeEmbedCode)"
                                        class="mt-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                    >
                                        📋 Copy Embed Code
                                    </button>
                                </div>

                                <!-- API Endpoint -->
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">API Endpoint</h4>
                                    <div class="bg-gray-900 dark:bg-panel rounded-lg p-3 overflow-x-auto">
                                        <code class="text-green-400 text-sm">
                                            GET {{ apiEndpoint }}
                                        </code>
                                    </div>
                                    <button 
                                        @click="copyToClipboard(apiEndpoint)"
                                        class="mt-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                    >
                                        📋 Copy API URL
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a 
                                :href="route('enhanced-verification.manage')"
                                class="inline-flex items-center justify-center px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors font-medium"
                            >
                                📊 Manage Verifications
                            </a>
                            
                            <a 
                                :href="route('enhanced-verification.demo')"
                                class="inline-flex items-center justify-center px-6 py-3 bg-panel text-white rounded-lg hover:bg-gray-600 transition-colors font-medium"
                            >
                                🎮 Try Demo
                            </a>
                            
                            <a 
                                :href="route('dashboard')"
                                class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-panel dark:hover:bg-gray-700 transition-colors font-medium"
                            >
                                🏠 Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

<script setup>
import { computed } from 'vue'
// route function is available globally via ZiggyVue plugin
import GuestLayout from '@/Layouts/GuestLayout.vue'
import EnhancedVerificationBadge from '@/Components/Verification/EnhancedVerificationBadge.vue'

// Props
const props = defineProps({
    verification: {
        type: Object,
        required: true
    },
    badge_data: {
        type: Object,
        required: true
    },
    security_features: {
        type: Object,
        default: () => ({})
    }
})

// Computed properties
const truncatedAddress = computed(() => {
    const addr = props.verification?.contract_address || ''
    return addr.length > 10 ? `${addr.substring(0, 6)}...${addr.substring(addr.length - 4)}` : addr
})

const formattedVerificationDate = computed(() => {
    if (!props.verification?.verified_at) return 'Unknown'
    
    try {
        const date = new Date(props.verification.verified_at)
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    } catch (e) {
        return 'Invalid Date'
    }
})

const badgeEmbedUrl = computed(() => {
    return `${window.location.origin}/enhanced-verification/badge/${props.verification?.contract_address}`
})

const badgeEmbedCode = computed(() => {
    return `<iframe src="${badgeEmbedUrl.value}" width="200" height="50" frameborder="0"></iframe>`
})

const apiEndpoint = computed(() => {
    return `${window.location.origin}/enhanced-verification/status/${props.verification?.contract_address}`
})

// Methods
const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text)
        console.log('Copied to clipboard')
        // Could add a toast notification here
    } catch (err) {
        console.error('Failed to copy to clipboard:', err)
        // Fallback for older browsers
        const textArea = document.createElement('textarea')
        textArea.value = text
        document.body.appendChild(textArea)
        textArea.select()
        document.execCommand('copy')
        document.body.removeChild(textArea)
    }
}
</script>

<style scoped>
/* Custom animations */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Code styling */
code {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

/* Responsive design */
@media (max-width: 640px) {
    .grid-cols-2 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
}
</style>