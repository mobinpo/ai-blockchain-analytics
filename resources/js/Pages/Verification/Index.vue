<template>
    <AppLayout title="Get Your Smart Contract Verified">
        <template #header>
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
                Get Your Smart Contract Verified
            </h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Generate cryptographically signed verification badges with SHA-256 + HMAC security
            </p>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Statistics Banner -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl p-6 mb-8 text-white">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <div class="text-3xl font-bold">{{ formatNumber(stats.total_verifications) }}</div>
                            <div class="text-blue-100">Total Verifications</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold">{{ formatNumber(stats.active_verifications) }}</div>
                            <div class="text-blue-100">Active Badges</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold">{{ stats.verification_success_rate }}%</div>
                            <div class="text-blue-100">Success Rate</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold">{{ stats.average_verification_time }}s</div>
                            <div class="text-blue-100">Avg Time</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Verification Form -->
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                            🛡️ Generate Verification Badge
                        </h3>
                        
                        <form @submit.prevent="generateVerification" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Contract Address *
                                </label>
                                <input
                                    v-model="form.contract_address"
                                    type="text"
                                    placeholder="0x..."
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    :class="{ 'border-red-500': errors.contract_address }"
                                    required
                                />
                                <div v-if="errors.contract_address" class="text-red-500 text-sm mt-1">
                                    {{ errors.contract_address[0] }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Project Name
                                </label>
                                <input
                                    v-model="form.project_name"
                                    type="text"
                                    placeholder="My DeFi Protocol"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Description
                                </label>
                                <textarea
                                    v-model="form.description"
                                    placeholder="Brief description of your smart contract..."
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Website URL
                                    </label>
                                    <input
                                        v-model="form.website_url"
                                        type="url"
                                        placeholder="https://yourproject.com"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        GitHub URL
                                    </label>
                                    <input
                                        v-model="form.github_url"
                                        type="url"
                                        placeholder="https://github.com/user/repo"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Expiry (Hours)
                                </label>
                                <select
                                    v-model="form.expiry_hours"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                >
                                    <option value="24">24 Hours</option>
                                    <option value="48">48 Hours</option>
                                    <option value="72">3 Days</option>
                                    <option value="168">1 Week</option>
                                </select>
                            </div>

                            <button
                                type="submit"
                                :disabled="loading"
                                class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                            >
                                <span v-if="loading" class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Generating...
                                </span>
                                <span v-else>🛡️ Generate Verification Badge</span>
                            </button>
                        </form>

                        <!-- Quick Examples -->
                        <div class="mt-6 p-4 bg-panel dark:bg-gray-700 rounded-lg">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Quick Examples:</h4>
                            <div class="space-y-2">
                                <button
                                    @click="fillExample('uniswap')"
                                    class="block w-full text-left text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                >
                                    🦄 Uniswap V3 Router
                                </button>
                                <button
                                    @click="fillExample('aave')"
                                    class="block w-full text-left text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                >
                                    👻 Aave V3 Pool
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Results Panel -->
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                            📋 Verification Results
                        </h3>

                        <div v-if="!verification && !error" class="text-center text-gray-500 dark:text-gray-400 py-8">
                            <div class="text-6xl mb-4">🛡️</div>
                            <p>Generate a verification badge to see results here</p>
                        </div>

                        <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                            <div class="flex items-center">
                                <div class="text-red-400 mr-3">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="text-red-800 dark:text-red-200">{{ error }}</div>
                            </div>
                        </div>

                        <div v-if="verification" class="space-y-6">
                            <!-- Success Message -->
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="text-green-400 mr-3">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="text-green-800 dark:text-green-200 font-semibold">
                                        Verification Badge Generated Successfully!
                                    </div>
                                </div>
                            </div>

                            <!-- Badge Preview -->
                            <div class="space-y-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white">Badge Preview:</h4>
                                <div class="p-4 bg-panel dark:bg-gray-700 rounded-lg" v-html="verification.badge_html"></div>
                            </div>

                            <!-- Verification Details -->
                            <div class="space-y-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white">Verification Details:</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-600 dark:text-gray-400">Contract:</span>
                                        <div class="font-mono text-xs bg-ink dark:bg-gray-700 p-2 rounded mt-1">
                                            {{ verification.verification.contract_address }}
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-gray-600 dark:text-gray-400">Verification ID:</span>
                                        <div class="font-mono text-xs bg-ink dark:bg-gray-700 p-2 rounded mt-1">
                                            {{ verification.verification.verification_id }}
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-gray-600 dark:text-gray-400">Expires:</span>
                                        <div class="text-sm">{{ formatDate(verification.verification.expires_at) }}</div>
                                    </div>
                                    <div>
                                        <span class="text-gray-600 dark:text-gray-400">Signature:</span>
                                        <div class="font-mono text-xs bg-ink dark:bg-gray-700 p-2 rounded mt-1 truncate">
                                            {{ verification.verification.signature.substring(0, 20) }}...
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Verification URL -->
                            <div class="space-y-2">
                                <h4 class="font-semibold text-gray-900 dark:text-white">Verification URL:</h4>
                                <div class="flex items-center space-x-2">
                                    <input
                                        :value="verification.verification.verification_url"
                                        readonly
                                        class="flex-1 px-3 py-2 text-xs font-mono bg-ink dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded"
                                    />
                                    <button
                                        @click="copyToClipboard(verification.verification.verification_url)"
                                        class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
                                    >
                                        📋 Copy
                                    </button>
                                </div>
                            </div>

                            <!-- Embed Code -->
                            <div class="space-y-2">
                                <h4 class="font-semibold text-gray-900 dark:text-white">Embed Code:</h4>
                                <textarea
                                    :value="verification.embed_code"
                                    readonly
                                    rows="8"
                                    class="w-full px-3 py-2 text-xs font-mono bg-ink dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded"
                                ></textarea>
                                <button
                                    @click="copyToClipboard(verification.embed_code)"
                                    class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors"
                                >
                                    📋 Copy Embed Code
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Information -->
                <div class="mt-8 bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                        🔒 Security Features
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center p-4">
                            <div class="text-3xl mb-2">🔐</div>
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-2">HMAC-SHA256 Signing</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Every verification badge is cryptographically signed to prevent tampering
                            </p>
                        </div>
                        <div class="text-center p-4">
                            <div class="text-3xl mb-2">⏰</div>
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Time-Based Expiry</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Badges expire automatically to maintain security and freshness
                            </p>
                        </div>
                        <div class="text-center p-4">
                            <div class="text-3xl mb-2">🛡️</div>
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Anti-Spoofing</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Multi-layer validation prevents badge forgery and URL manipulation
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    stats: Object,
    title: String,
    description: String,
})

const loading = ref(false)
const verification = ref(null)
const error = ref(null)
const errors = ref({})

const form = reactive({
    contract_address: '',
    project_name: '',
    description: '',
    website_url: '',
    github_url: '',
    expiry_hours: 24,
})

const generateVerification = async () => {
    loading.value = true
    error.value = null
    errors.value = {}
    verification.value = null

    try {
        const response = await fetch('/api/verification/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(form)
        })

        const data = await response.json()

        if (data.success) {
            verification.value = data
        } else {
            if (data.errors) {
                errors.value = data.errors
            } else {
                error.value = data.error || 'Failed to generate verification'
            }
        }
    } catch (err) {
        error.value = 'Network error occurred. Please try again.'
    } finally {
        loading.value = false
    }
}

const fillExample = (type) => {
    if (type === 'uniswap') {
        form.contract_address = '0xE592427A0AEce92De3Edee1F18E0157C05861564'
        form.project_name = 'Uniswap V3 SwapRouter'
        form.description = 'Leading decentralized exchange protocol with concentrated liquidity'
        form.website_url = 'https://uniswap.org'
        form.github_url = 'https://github.com/uniswap/v3-periphery'
    } else if (type === 'aave') {
        form.contract_address = '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2'
        form.project_name = 'Aave V3 Pool'
        form.description = 'Premier decentralized lending protocol with cross-chain capabilities'
        form.website_url = 'https://aave.com'
        form.github_url = 'https://github.com/aave/aave-v3-core'
    }
}

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text)
        // Could add a toast notification here
    } catch (err) {
        console.error('Failed to copy text: ', err)
    }
}

const formatNumber = (num) => {
    return new Intl.NumberFormat().format(num)
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString()
}
</script>

<style scoped>
.verification-badge {
    /* Ensure badge styles are isolated */
    font-family: system-ui, -apple-system, sans-serif;
}
</style>