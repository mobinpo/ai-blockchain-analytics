<template>
    <AppLayout :title="title">
        <template #header>
            <div class="flex items-center space-x-3">
                <div class="text-green-500">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Contract Verified ✅
                    </h2>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">
                        This smart contract has been cryptographically verified
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Verification Status Banner -->
                <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-xl p-6 mb-8 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="bg-white/20 rounded-full p-3">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold">VERIFIED CONTRACT</h3>
                                <p class="text-green-100">Cryptographically authenticated by AI Blockchain Analytics</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-green-100">Verified At</div>
                            <div class="text-lg font-semibold">{{ formatDate(verification.verified_at) }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Contract Information -->
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="px-6 py-4 bg-panel dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Contract Information</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Project Name
                                </label>
                                <div class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ verification.project_name }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Contract Address
                                </label>
                                <div class="flex items-center space-x-2">
                                    <code class="flex-1 px-3 py-2 bg-ink dark:bg-gray-700 rounded text-sm font-mono">
                                        {{ verification.contract_address }}
                                    </code>
                                    <button
                                        @click="copyToClipboard(verification.contract_address)"
                                        class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors text-sm"
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>

                            <div v-if="verification.metadata?.description">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Description
                                </label>
                                <p class="text-gray-900 dark:text-white">
                                    {{ verification.metadata.description }}
                                </p>
                            </div>

                            <div class="flex space-x-4">
                                <div v-if="verification.metadata?.website_url">
                                    <a
                                        :href="verification.metadata.website_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16 8 8 0 000-16zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.56-.5-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.56.5.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.498-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118 12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z" clip-rule="evenodd"></path>
                                        </svg>
                                        Website
                                    </a>
                                </div>

                                <div v-if="verification.metadata?.github_url">
                                    <a
                                        :href="verification.metadata.github_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center px-4 py-2 bg-panel text-white rounded-lg hover:bg-gray-900 transition-colors"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.921.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        GitHub
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Verification Details -->
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="px-6 py-4 bg-panel dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Verification Details</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Verification ID
                                </label>
                                <code class="block px-3 py-2 bg-ink dark:bg-gray-700 rounded text-sm font-mono">
                                    {{ verification.verification_id }}
                                </code>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Verification Status
                                </label>
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                    <span class="text-green-600 dark:text-green-400 font-semibold">VALID & ACTIVE</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Expires At
                                </label>
                                <div class="text-gray-900 dark:text-white">
                                    {{ formatDate(verification.expires_at) }}
                                </div>
                                <div class="text-sm text-gray-500 mt-1">
                                    {{ getTimeUntilExpiry(verification.expires_at) }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Verified By
                                </label>
                                <div class="flex items-center space-x-2">
                                    <div class="w-6 h-6 bg-gradient-to-r from-blue-500 to-purple-500 rounded flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">AI</span>
                                    </div>
                                    <span class="text-gray-900 dark:text-white font-medium">AI Blockchain Analytics</span>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                                    <span>Security Level</span>
                                    <span class="font-semibold text-green-600">SHA-256 + HMAC</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                    <div class="bg-green-500 h-2 rounded-full w-full"></div>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Maximum security with cryptographic signing
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Verification Process -->
                <div class="mt-8 bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="px-6 py-4 bg-panel dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">🔐 Security Verification Process</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="text-center">
                                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/50 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <div class="text-blue-600 dark:text-blue-400 text-xl">1️⃣</div>
                                </div>
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Contract Analysis</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Smart contract code analyzed for security vulnerabilities
                                </p>
                            </div>
                            <div class="text-center">
                                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/50 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <div class="text-purple-600 dark:text-purple-400 text-xl">2️⃣</div>
                                </div>
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">HMAC Signature</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Cryptographic signature generated using SHA-256 + HMAC
                                </p>
                            </div>
                            <div class="text-center">
                                <div class="w-12 h-12 bg-green-100 dark:bg-green-900/50 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <div class="text-green-600 dark:text-green-400 text-xl">3️⃣</div>
                                </div>
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">URL Generation</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Tamper-proof verification URL created with embedded signature
                                </p>
                            </div>
                            <div class="text-center">
                                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/50 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <div class="text-emerald-600 dark:text-emerald-400 text-xl">4️⃣</div>
                                </div>
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Verification</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Real-time validation ensures authenticity and prevents spoofing
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trust Indicators -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg p-6 text-center">
                        <div class="text-3xl mb-3">🛡️</div>
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Anti-Tampering</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            URL signature prevents modification or spoofing attempts
                        </p>
                    </div>
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg p-6 text-center">
                        <div class="text-3xl mb-3">⏰</div>
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Time-Bound</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Verification expires automatically to maintain security freshness
                        </p>
                    </div>
                    <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg p-6 text-center">
                        <div class="text-3xl mb-3">🔍</div>
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Auditable</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Full verification trail available for security audits
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                    <a
                        href="/get-verified"
                        class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200"
                    >
                        🛡️ Get Your Contract Verified
                    </a>
                    <button
                        @click="copyCurrentUrl"
                        class="inline-flex items-center justify-center px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white font-semibold rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                    >
                        📋 Share This Verification
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    verification: Object,
    title: String,
    meta_description: String,
})

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text)
        // Could add a toast notification here
    } catch (err) {
        console.error('Failed to copy text: ', err)
    }
}

const copyCurrentUrl = async () => {
    await copyToClipboard(window.location.href)
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZoneName: 'short'
    })
}

const getTimeUntilExpiry = (expiresAt) => {
    const now = new Date()
    const expiry = new Date(expiresAt)
    const diff = expiry.getTime() - now.getTime()
    
    if (diff <= 0) {
        return 'Expired'
    }
    
    const hours = Math.floor(diff / (1000 * 60 * 60))
    const days = Math.floor(hours / 24)
    
    if (days > 0) {
        return `Expires in ${days} day${days > 1 ? 's' : ''}`
    } else if (hours > 0) {
        return `Expires in ${hours} hour${hours > 1 ? 's' : ''}`
    } else {
        const minutes = Math.floor(diff / (1000 * 60))
        return `Expires in ${minutes} minute${minutes > 1 ? 's' : ''}`
    }
}
</script>