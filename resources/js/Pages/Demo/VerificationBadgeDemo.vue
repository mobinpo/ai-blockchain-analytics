<template>
    <AppLayout title="Verification Badge Demo">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg mb-8">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                    🛡️ Get Verified Badge System Demo
                                </h1>
                                <p class="mt-2 text-lg text-gray-600 dark:text-gray-400">
                                    Secure smart contract verification with SHA-256 + HMAC signed URLs
                                </p>
                            </div>
                            <div class="bg-green-100 dark:bg-green-900/20 rounded-lg p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                    <span class="text-sm font-medium text-green-800 dark:text-green-200">
                                        Production Ready
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Overview -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg mb-8">
                    <div class="p-6 sm:p-8">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                            🔒 Advanced Security Architecture
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- SHA-256 + HMAC -->
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-6 border border-blue-200 dark:border-blue-700">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-2xl">🔐</span>
                                </div>
                                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
                                    SHA-256 + HMAC
                                </h3>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    Cryptographic signatures with multi-layer authentication for tamper-proof verification
                                </p>
                            </div>

                            <!-- Anti-Spoofing -->
                            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-6 border border-green-200 dark:border-green-700">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-2xl">🛡️</span>
                                </div>
                                <h3 class="text-lg font-semibold text-green-900 dark:text-green-100 mb-2">
                                    Anti-Spoofing
                                </h3>
                                <p class="text-sm text-green-700 dark:text-green-300">
                                    Advanced protection against URL tampering and unauthorized verification attempts
                                </p>
                            </div>

                            <!-- Replay Protection -->
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-xl p-6 border border-purple-200 dark:border-purple-700">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                    </div>
                                    <span class="text-2xl">🔄</span>
                                </div>
                                <h3 class="text-lg font-semibold text-purple-900 dark:text-purple-100 mb-2">
                                    Replay Protection
                                </h3>
                                <p class="text-sm text-purple-700 dark:text-purple-300">
                                    One-time use tokens with cryptographic nonces prevent replay attacks
                                </p>
                            </div>

                            <!-- Time-Limited -->
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/20 rounded-xl p-6 border border-orange-200 dark:border-orange-700">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 bg-orange-600 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-2xl">⏰</span>
                                </div>
                                <h3 class="text-lg font-semibold text-orange-900 dark:text-orange-100 mb-2">
                                    Time-Limited
                                </h3>
                                <p class="text-sm text-orange-700 dark:text-orange-300">
                                    URLs expire after 1 hour to minimize exposure window and enhance security
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Demo -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg mb-8">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                🚀 Live Verification Demo
                            </h2>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="text-sm text-gray-600 dark:text-gray-400">System Active</span>
                            </div>
                        </div>
                        
                        <!-- Verification Badge Manager Component -->
                        <GetVerifiedBadgeManager 
                            :initial-contract-address="demoContract"
                        />
                    </div>
                </div>

                <!-- Example Contracts -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg mb-8">
                    <div class="p-6 sm:p-8">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                            📋 Example Smart Contracts
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div 
                                v-for="contract in exampleContracts" 
                                :key="contract.address"
                                class="bg-panel dark:bg-gray-700 rounded-lg p-4 cursor-pointer hover:bg-ink dark:hover:bg-gray-600 transition-colors"
                                @click="selectContract(contract)"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-semibold text-gray-900 dark:text-white">
                                        {{ contract.name }}
                                    </h3>
                                    <div class="px-2 py-1 bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                                        {{ contract.network }}
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    {{ contract.description }}
                                </p>
                                <div class="font-mono text-xs text-gray-500 dark:text-gray-400 break-all">
                                    {{ contract.address }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Reference -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg mb-8">
                    <div class="p-6 sm:p-8">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                            📚 API Reference
                        </h2>
                        
                        <div class="space-y-6">
                            <!-- Generate Verification URL -->
                            <div class="bg-panel dark:bg-gray-700 rounded-lg p-4">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                                    POST /api/verification/generate-enhanced
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    Generate a cryptographically secure verification URL with enhanced SHA-256 + HMAC protection
                                </p>
                                <pre class="bg-gray-900 text-green-400 p-3 rounded text-sm overflow-x-auto"><code>{
  "contract_address": "0x...",
  "user_id": "user123",
  "metadata": {
    "project_name": "My DeFi Project",
    "description": "A secure DeFi protocol"
  }
}</code></pre>
                            </div>

                            <!-- Check Status -->
                            <div class="bg-panel dark:bg-gray-700 rounded-lg p-4">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                                    GET /api/verification/status
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    Check the verification status of a smart contract
                                </p>
                                <pre class="bg-gray-900 text-green-400 p-3 rounded text-sm overflow-x-auto"><code>?contract_address=0x...</code></pre>
                            </div>

                            <!-- Get Badge -->
                            <div class="bg-panel dark:bg-gray-700 rounded-lg p-4">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                                    GET /api/verification/badge
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    Get the verification badge HTML and data for a verified contract
                                </p>
                                <pre class="bg-gray-900 text-green-400 p-3 rounded text-sm overflow-x-auto"><code>?contract_address=0x...&format=html</code></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Checklist -->
                <div class="bg-white dark:bg-panel overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 sm:p-8">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                            ✅ Security Checklist
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                    🔐 Cryptographic Protection
                                </h3>
                                <ul class="space-y-3">
                                    <li class="flex items-center gap-3">
                                        <div class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            SHA-256 cryptographic hash algorithm
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <div class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            HMAC (Hash-based Message Authentication Code)
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <div class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            Multi-layer signature verification
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <div class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            Secure random nonce generation
                                        </span>
                                    </li>
                                </ul>
                            </div>
                            
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                    🛡️ Anti-Spoofing Measures
                                </h3>
                                <ul class="space-y-3">
                                    <li class="flex items-center gap-3">
                                        <div class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            One-time use token system
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <div class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            Time-based URL expiration (1 hour)
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <div class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            Rate limiting protection (10 attempts/hour)
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <div class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            IP address and user agent binding
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import GetVerifiedBadgeManager from '@/Components/Verification/GetVerifiedBadgeManager.vue'

// Demo state
const demoContract = ref('0x1234567890123456789012345678901234567890')

// Example contracts for testing
const exampleContracts = ref([
    {
        name: 'Uniswap V3',
        description: 'Automated market maker protocol',
        address: '0x1F98431c8aD98523631AE4a59f267346ea31F984',
        network: 'Ethereum'
    },
    {
        name: 'Compound USDC',
        description: 'Interest-bearing USDC pool',
        address: '0x39AA39c021dfbaE8faC545936693aC917d5E7563',
        network: 'Ethereum'
    },
    {
        name: 'PancakeSwap Router',
        description: 'Decentralized exchange router',
        address: '0x10ED43C718714eb63d5aA57B78B54704E256024E',
        network: 'BSC'
    },
    {
        name: 'AAVE Lending Pool',
        description: 'Decentralized lending protocol',
        address: '0x7d2768dE32b0b80b7a3454c06BdAc94A69DDc7A9',
        network: 'Ethereum'
    },
    {
        name: 'SushiSwap Router',
        description: 'Multi-chain DEX router',
        address: '0xd9e1cE17f2641f24aE83637ab66a2cca9C378B9F',
        network: 'Ethereum'
    },
    {
        name: 'Demo Contract',
        description: 'Test smart contract for verification demo',
        address: '0x1234567890123456789012345678901234567890',
        network: 'Testnet'
    }
])

// Methods
const selectContract = (contract) => {
    demoContract.value = contract.address
    
    // Scroll to the demo section
    document.querySelector('.get-verified-badge-manager')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    })
}
</script>

<style scoped>
/* Custom gradient backgrounds */
.bg-gradient-to-br {
    background-image: linear-gradient(to bottom right, var(--tw-gradient-from), var(--tw-gradient-to));
}

/* Animation classes */
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

/* Code syntax highlighting */
pre code {
    font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
}

/* Hover effects */
.hover\\:bg-ink:hover {
    background-color: #f3f4f6;
}

.dark .hover\\:bg-gray-600:hover {
    background-color: #4b5563;
}
</style>