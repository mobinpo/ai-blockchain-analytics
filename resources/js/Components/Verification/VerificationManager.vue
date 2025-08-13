<template>
    <div class="verification-manager">
        <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <!-- Header -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            🛡️ Verification Manager
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Generate secure verification badges with SHA-256 + HMAC protection
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Security Level</div>
                        <div class="text-lg font-semibold text-blue-600">Enhanced</div>
                    </div>
                </div>
            </div>

            <!-- Quick Generate Form -->
            <div class="p-6">
                <div class="mb-6">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Generate Verification URL
                    </h4>
                    
                    <form @submit.prevent="generateVerificationUrl" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Contract Address -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Contract Address <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.contractAddress"
                                    type="text"
                                    placeholder="0x..."
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    :class="{ 'border-red-500': errors.contractAddress }"
                                    required
                                >
                                <p v-if="errors.contractAddress" class="mt-1 text-sm text-red-600">
                                    {{ errors.contractAddress }}
                                </p>
                            </div>

                            <!-- Project Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Project Name
                                </label>
                                <input
                                    v-model="form.metadata.project_name"
                                    type="text"
                                    placeholder="My DeFi Protocol"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                            </div>

                            <!-- Website -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Website URL
                                </label>
                                <input
                                    v-model="form.metadata.website"
                                    type="url"
                                    placeholder="https://example.com"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                            </div>

                            <!-- Category -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Category
                                </label>
                                <select
                                    v-model="form.metadata.category"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">Select category</option>
                                    <option value="DeFi">DeFi</option>
                                    <option value="NFT">NFT</option>
                                    <option value="Gaming">Gaming</option>
                                    <option value="Infrastructure">Infrastructure</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <!-- URL Lifetime -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    URL Lifetime
                                </label>
                                <select
                                    v-model="form.options.lifetime"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option :value="1800">30 minutes</option>
                                    <option :value="3600">1 hour</option>
                                    <option :value="7200">2 hours</option>
                                    <option :value="14400">4 hours</option>
                                </select>
                            </div>

                            <!-- Description -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Description
                                </label>
                                <textarea
                                    v-model="form.metadata.description"
                                    rows="3"
                                    placeholder="Brief description of your smart contract or project"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Generate Button -->
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="generating"
                                class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                            >
                                <svg v-if="generating" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ generating ? 'Generating...' : '🛡️ Generate Secure URL' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Generated URL Result -->
                <div v-if="generatedUrl" class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <h5 class="font-semibold text-green-900 dark:text-green-100 mb-3">
                        ✅ Secure Verification URL Generated
                    </h5>
                    
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-green-700 dark:text-green-300 mb-1">
                                Verification URL:
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    :value="generatedUrl.verification_url"
                                    readonly
                                    class="flex-1 px-3 py-2 bg-white dark:bg-gray-700 border border-green-300 dark:border-green-600 rounded text-sm font-mono"
                                >
                                <button
                                    @click="copyToClipboard(generatedUrl.verification_url)"
                                    class="px-3 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors text-sm"
                                >
                                    📋 Copy
                                </button>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-green-700 dark:text-green-300">Expires:</span>
                                <div class="text-green-600 dark:text-green-400">{{ formatExpiryTime(generatedUrl.expires_at) }}</div>
                            </div>
                            <div>
                                <span class="font-medium text-green-700 dark:text-green-300">Security:</span>
                                <div class="text-green-600 dark:text-green-400">{{ generatedUrl.security_level }}</div>
                            </div>
                            <div>
                                <span class="font-medium text-green-700 dark:text-green-300">Version:</span>
                                <div class="text-green-600 dark:text-green-400">{{ generatedUrl.signature_version }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Display -->
                <div v-if="error" class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <h5 class="font-semibold text-red-900 dark:text-red-100 mb-2">
                        ❌ Error
                    </h5>
                    <p class="text-red-700 dark:text-red-300 text-sm">{{ error }}</p>
                </div>

                <!-- Security Features -->
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <h5 class="font-semibold text-blue-900 dark:text-blue-100 mb-3">
                        🛡️ Enhanced Security Features
                    </h5>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                SHA-256 + HMAC Signatures
                            </div>
                            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Multi-layer Protection
                            </div>
                            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Anti-spoofing Measures
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Replay Attack Prevention
                            </div>
                            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                IP & User Agent Binding
                            </div>
                            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Cryptographic Nonces
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Badge Preview -->
        <div v-if="generatedUrl" class="mt-6 bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Badge Preview
            </h4>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                This is how the verification badge will appear once the URL is verified:
            </p>
            
            <!-- Mock verified badge -->
            <div class="inline-block p-4 bg-panel dark:bg-gray-700 rounded-lg">
                <EnhancedVerificationBadge
                    :contract-address="form.contractAddress"
                    :auto-load="false"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import EnhancedVerificationBadge from './EnhancedVerificationBadge.vue'

// Reactive state
const generating = ref(false)
const generatedUrl = ref(null)
const error = ref('')
const errors = reactive({})

// Form data
const form = reactive({
    contractAddress: '',
    metadata: {
        project_name: '',
        website: '',
        description: '',
        category: '',
        tags: []
    },
    options: {
        lifetime: 3600 // 1 hour default
    }
})

// Methods
const generateVerificationUrl = async () => {
    generating.value = true
    error.value = ''
    generatedUrl.value = null
    
    // Clear previous errors
    Object.keys(errors).forEach(key => delete errors[key])
    
    try {
        // Validate contract address
        if (!form.contractAddress) {
            errors.contractAddress = 'Contract address is required'
            return
        }
        
        if (!/^0x[a-fA-F0-9]{40}$/.test(form.contractAddress)) {
            errors.contractAddress = 'Invalid contract address format'
            return
        }

        const response = await axios.post('/enhanced-verification/generate', {
            contract_address: form.contractAddress,
            metadata: form.metadata,
            options: form.options
        })
        
        if (response.data.success) {
            generatedUrl.value = response.data.data
            
            // Reset form
            form.contractAddress = ''
            form.metadata = {
                project_name: '',
                website: '',
                description: '',
                category: '',
                tags: []
            }
        } else {
            throw new Error(response.data.error || 'Failed to generate verification URL')
        }
    } catch (err) {
        console.error('Error generating verification URL:', err)
        error.value = err.response?.data?.error || err.message || 'Failed to generate verification URL'
        
        if (err.response?.status === 409) {
            errors.contractAddress = 'This contract is already verified'
        }
    } finally {
        generating.value = false
    }
}

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text)
        // Could add a toast notification here
        console.log('Copied to clipboard')
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

const formatExpiryTime = (expiresAt) => {
    try {
        const date = new Date(expiresAt)
        const now = new Date()
        const diffMs = date.getTime() - now.getTime()
        const diffMinutes = Math.floor(diffMs / (1000 * 60))
        
        if (diffMinutes > 60) {
            const hours = Math.floor(diffMinutes / 60)
            const minutes = diffMinutes % 60
            return `${hours}h ${minutes}m`
        } else {
            return `${diffMinutes}m`
        }
    } catch (e) {
        return 'Invalid date'
    }
}
</script>

<style scoped>
/* Custom styles for the verification manager */
.verification-manager {
    @apply w-full;
}

/* Form input focus styles */
input:focus, select:focus, textarea:focus {
    @apply ring-2 ring-blue-500 border-blue-500;
}

/* Error state styles */
input.border-red-500, select.border-red-500, textarea.border-red-500 {
    @apply ring-2 ring-red-500;
}

/* Smooth transitions */
* {
    transition: all 0.2s ease;
}

/* Loading spinner animation */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>