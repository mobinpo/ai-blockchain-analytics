<template>
    <div class="get-verified-badge-manager">
        <!-- Header -->
        <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        🛡️ Get Verified Badge Manager
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Secure contract verification with SHA-256 + HMAC signed URLs
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full" :class="isGenerating ? 'bg-yellow-500 animate-pulse' : 'bg-green-500'"></div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ isGenerating ? 'Processing...' : 'Ready' }}
                    </span>
                </div>
            </div>

            <!-- Security Features -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 bg-blue-100 dark:bg-blue-900/40 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-blue-900 dark:text-blue-100">SHA-256 + HMAC</div>
                            <div class="text-xs text-blue-600 dark:text-blue-400">Cryptographic Security</div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 bg-green-100 dark:bg-green-900/40 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-green-900 dark:text-green-100">Anti-Spoofing</div>
                            <div class="text-xs text-green-600 dark:text-green-400">Tamper Proof</div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 bg-purple-100 dark:bg-purple-900/40 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-purple-900 dark:text-purple-100">Replay Protection</div>
                            <div class="text-xs text-purple-600 dark:text-purple-400">One-time Use</div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 bg-orange-100 dark:bg-orange-900/40 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-orange-900 dark:text-orange-100">Time-Limited</div>
                            <div class="text-xs text-orange-600 dark:text-orange-400">Expires 1hr</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Generate Verification URL -->
            <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    🔗 Generate Verification URL
                </h3>
                
                <form @submit.prevent="generateVerificationUrl" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Contract Address
                        </label>
                        <input
                            v-model="form.contractAddress"
                            type="text"
                            placeholder="0x..."
                            pattern="^0x[a-fA-F0-9]{40}$"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            :class="{ 'border-red-500': form.errors.contractAddress }"
                            required
                        />
                        <p v-if="form.errors.contractAddress" class="mt-1 text-sm text-red-600">
                            {{ form.errors.contractAddress }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            User ID
                        </label>
                        <input
                            v-model="form.userId"
                            type="text"
                            placeholder="user123"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            :class="{ 'border-red-500': form.errors.userId }"
                            required
                        />
                        <p v-if="form.errors.userId" class="mt-1 text-sm text-red-600">
                            {{ form.errors.userId }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Project Name (Optional)
                        </label>
                        <input
                            v-model="form.projectName"
                            type="text"
                            placeholder="My DeFi Project"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description (Optional)
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            placeholder="Brief description of your project..."
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Security Level
                        </label>
                        <select
                            v-model="form.securityLevel"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="standard">Standard (SHA-256)</option>
                            <option value="enhanced">Enhanced (SHA-256 + HMAC)</option>
                        </select>
                    </div>

                    <button
                        type="submit"
                        :disabled="isGenerating"
                        class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors duration-200"
                    >
                        <svg v-if="isGenerating" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        {{ isGenerating ? 'Generating...' : 'Generate Secure URL' }}
                    </button>
                </form>
            </div>

            <!-- Check Verification Status -->
            <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    🔍 Check Verification Status
                </h3>
                
                <form @submit.prevent="checkVerificationStatus" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Contract Address
                        </label>
                        <input
                            v-model="checkForm.contractAddress"
                            type="text"
                            placeholder="0x..."
                            pattern="^0x[a-fA-F0-9]{40}$"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            required
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="isChecking"
                        class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white font-medium rounded-lg transition-colors duration-200"
                    >
                        <svg v-if="isChecking" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ isChecking ? 'Checking...' : 'Check Status' }}
                    </button>
                </form>

                <!-- Status Display -->
                <div v-if="verificationStatus" class="mt-4 p-4 rounded-lg border" :class="verificationStatus.is_verified ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-panel dark:bg-gray-900/20 border-gray-200 dark:border-gray-700'">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-4 h-4 rounded-full" :class="verificationStatus.is_verified ? 'bg-green-500' : 'bg-gray-400'"></div>
                        <span class="font-medium" :class="verificationStatus.is_verified ? 'text-green-800 dark:text-green-200' : 'text-gray-800 dark:text-gray-200'">
                            {{ verificationStatus.is_verified ? 'Verified' : 'Not Verified' }}
                        </span>
                    </div>
                    
                    <div v-if="verificationStatus.is_verified" class="text-sm space-y-1">
                        <div><strong>Verified:</strong> {{ verificationStatus.verified_at }}</div>
                        <div><strong>Method:</strong> {{ verificationStatus.verification_method }}</div>
                        <div v-if="verificationStatus.security_level"><strong>Security:</strong> {{ verificationStatus.security_level }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generated URL Display -->
        <div v-if="generatedUrl" class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                ✅ Verification URL Generated
            </h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Secure Verification URL
                    </label>
                    <div class="flex gap-2">
                        <input
                            :value="generatedUrl.verification_url"
                            readonly
                            class="flex-1 px-3 py-2 bg-panel dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm"
                        />
                        <button
                            @click="copyToClipboard(generatedUrl.verification_url)"
                            class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            📋
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Expires:</span>
                        <div class="font-medium text-gray-900 dark:text-white">{{ formatExpiryTime(generatedUrl.expires_at) }}</div>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Security:</span>
                        <div class="font-medium text-gray-900 dark:text-white">{{ generatedUrl.security_level || 'Standard' }}</div>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Token:</span>
                        <div class="font-medium text-gray-900 dark:text-white font-mono">{{ generatedUrl.nonce ? generatedUrl.nonce.substring(0, 8) + '...' : 'N/A' }}</div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        @click="testVerificationUrl"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm"
                    >
                        🧪 Test Verification
                    </button>
                    <button
                        @click="shareVerificationUrl"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
                    >
                        📤 Share URL
                    </button>
                    <button
                        @click="generateQrCode"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm"
                    >
                        📱 QR Code
                    </button>
                </div>
            </div>
        </div>

        <!-- Verification Badge Preview -->
        <div v-if="badgePreview" class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                🏆 Badge Preview
            </h3>
            
            <div class="space-y-4">
                <div v-html="badgePreview.badge_html" class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-panel dark:bg-gray-700"></div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Badge HTML</h4>
                        <textarea
                            :value="badgePreview.badge_html"
                            readonly
                            rows="6"
                            class="w-full px-3 py-2 bg-panel dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-xs font-mono"
                        ></textarea>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Badge Data</h4>
                        <pre class="w-full h-32 px-3 py-2 bg-panel dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-xs font-mono overflow-auto">{{ JSON.stringify(badgePreview.badge_data || {}, null, 2) }}</pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Information -->
        <div class="bg-white dark:bg-panel rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                🔒 Security Features
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Cryptographic Protection</h4>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            SHA-256 cryptographic signatures
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            HMAC authentication codes
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Multi-layer signature verification
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Cryptographic nonce protection
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Anti-Spoofing Measures</h4>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            One-time use tokens (replay protection)
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Time-limited URL expiration
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            IP address binding (optional)
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Rate limiting protection
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'

// Props
const props = defineProps({
    initialContractAddress: {
        type: String,
        default: ''
    }
})

// Reactive state
const isGenerating = ref(false)
const isChecking = ref(false)
const generatedUrl = ref(null)
const verificationStatus = ref(null)
const badgePreview = ref(null)

// Form data
const form = reactive({
    contractAddress: props.initialContractAddress,
    userId: '',
    projectName: '',
    description: '',
    securityLevel: 'enhanced',
    errors: {}
})

const checkForm = reactive({
    contractAddress: ''
})

// Methods
const generateVerificationUrl = async () => {
    if (isGenerating.value) return
    
    // Clear previous errors
    form.errors = {}
    
    // Validate form
    if (!form.contractAddress) {
        form.errors.contractAddress = 'Contract address is required'
        return
    }
    
    if (!form.contractAddress.match(/^0x[a-fA-F0-9]{40}$/)) {
        form.errors.contractAddress = 'Invalid contract address format'
        return
    }
    
    if (!form.userId) {
        form.errors.userId = 'User ID is required'
        return
    }
    
    isGenerating.value = true
    
    try {
        const payload = {
            contract_address: form.contractAddress,
            user_id: form.userId,
            metadata: {
                project_name: form.projectName,
                description: form.description
            }
        }
        
        // Choose the appropriate service based on security level
        const endpoint = form.securityLevel === 'enhanced' 
            ? '/api/verification/generate-enhanced'
            : '/api/verification/generate'
        
        const response = await axios.post(endpoint, payload)
        
        if (response.data.success) {
            generatedUrl.value = response.data
            
            // Also check verification status
            await checkVerificationStatus()
        } else {
            throw new Error(response.data.message || 'Failed to generate verification URL')
        }
        
    } catch (error) {
        console.error('Failed to generate verification URL:', error)
        
        if (error.response?.data?.details) {
            // Handle validation errors
            form.errors = error.response.data.details
        } else {
            alert(`Error: ${error.response?.data?.message || error.message}`)
        }
    } finally {
        isGenerating.value = false
    }
}

const checkVerificationStatus = async () => {
    const addressToCheck = checkForm.contractAddress || form.contractAddress
    if (!addressToCheck) return
    
    isChecking.value = true
    
    try {
        const response = await axios.get('/api/verification/status', {
            params: { contract_address: addressToCheck }
        })
        
        verificationStatus.value = response.data
        
        // If verified, get the badge
        if (response.data.is_verified) {
            await getBadgePreview(addressToCheck)
        }
        
    } catch (error) {
        console.error('Failed to check verification status:', error)
        alert(`Error: ${error.response?.data?.message || error.message}`)
    } finally {
        isChecking.value = false
    }
}

const getBadgePreview = async (contractAddress) => {
    try {
        const response = await axios.get('/api/verification/badge', {
            params: { 
                contract_address: contractAddress,
                format: 'html'
            }
        })
        
        if (response.data.is_verified) {
            badgePreview.value = response.data
        }
        
    } catch (error) {
        console.error('Failed to get badge preview:', error)
    }
}

const testVerificationUrl = () => {
    if (generatedUrl.value?.verification_url) {
        window.open(generatedUrl.value.verification_url, '_blank')
    }
}

const shareVerificationUrl = async () => {
    if (generatedUrl.value?.verification_url) {
        try {
            await navigator.share({
                title: 'Contract Verification',
                text: 'Verify this smart contract',
                url: generatedUrl.value.verification_url
            })
        } catch (error) {
            // Fallback to clipboard
            await copyToClipboard(generatedUrl.value.verification_url)
        }
    }
}

const generateQrCode = () => {
    if (generatedUrl.value?.verification_url) {
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(generatedUrl.value.verification_url)}`
        window.open(qrUrl, '_blank')
    }
}

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text)
        
        // Show temporary feedback
        const button = event.target
        const originalText = button.textContent
        button.textContent = '✅'
        setTimeout(() => {
            button.textContent = originalText
        }, 2000)
    } catch (error) {
        console.error('Failed to copy to clipboard:', error)
    }
}

const formatExpiryTime = (expiresAt) => {
    if (!expiresAt) return 'Unknown'
    
    const expiryDate = new Date(expiresAt)
    const now = new Date()
    const diffMs = expiryDate - now
    
    if (diffMs <= 0) {
        return 'Expired'
    }
    
    const diffMinutes = Math.floor(diffMs / (1000 * 60))
    const diffHours = Math.floor(diffMinutes / 60)
    
    if (diffHours > 0) {
        return `${diffHours}h ${diffMinutes % 60}m`
    } else {
        return `${diffMinutes}m`
    }
}

// Auto-populate user ID if available
if (window.Laravel?.user?.id) {
    form.userId = window.Laravel.user.id.toString()
}
</script>

<style scoped>
.get-verified-badge-manager {
    @apply max-w-6xl mx-auto p-4;
}

/* Custom animations */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Badge preview styling */
.get-verified-badge-manager >>> .verification-badge,
.get-verified-badge-manager >>> .enhanced-verification-badge {
    @apply inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium;
}

.get-verified-badge-manager >>> .verification-badge.verified,
.get-verified-badge-manager >>> .enhanced-verification-badge.verified {
    @apply bg-green-100 text-green-800 border border-green-200;
}

.get-verified-badge-manager >>> .badge-tooltip {
    @apply absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 pointer-events-none transition-opacity;
}

.get-verified-badge-manager >>> .verification-badge:hover .badge-tooltip,
.get-verified-badge-manager >>> .enhanced-verification-badge:hover .badge-tooltip {
    @apply opacity-100;
}
</style>
