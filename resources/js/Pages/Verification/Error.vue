<template>
    <GuestLayout title="Verification Error">
        <div class="min-h-screen bg-gradient-to-br from-red-50 to-orange-50 dark:from-gray-900 dark:to-gray-800 flex items-center justify-center p-4">
            <div class="max-w-2xl w-full">
                <!-- Error Card -->
                <div class="bg-white dark:bg-panel rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-red-500 to-red-600 p-8 text-center">
                        <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-12 h-12 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <h1 class="text-3xl font-bold text-white mb-2">
                            ⚠️ Verification Failed
                        </h1>
                        <p class="text-red-100 text-lg">
                            There was an issue with the verification process
                        </p>
                    </div>

                    <!-- Content -->
                    <div class="p-8">
                        <!-- Error Details -->
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-6 mb-6">
                            <h2 class="text-lg font-semibold text-red-900 dark:text-red-100 mb-3 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                Error Details
                            </h2>
                            
                            <div class="bg-white dark:bg-panel rounded-lg p-4 border border-red-200 dark:border-red-700">
                                <p class="text-red-800 dark:text-red-200 font-medium">
                                    {{ error || 'An unknown error occurred during verification' }}
                                </p>
                            </div>
                        </div>

                        <!-- Common Issues -->
                        <div class="bg-panel dark:bg-gray-700 rounded-xl p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                🔍 Common Issues
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-6 h-6 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center mt-0.5">
                                        <span class="text-yellow-600 dark:text-yellow-400 text-sm">1</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">URL Expired</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Verification URLs expire after a set time for security. Request a new verification URL.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-6 h-6 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center mt-0.5">
                                        <span class="text-yellow-600 dark:text-yellow-400 text-sm">2</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">Already Verified</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            This contract may already be verified. Check your verification management page.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-6 h-6 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center mt-0.5">
                                        <span class="text-yellow-600 dark:text-yellow-400 text-sm">3</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">Security Violation</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            The verification link may have been tampered with or accessed from a different location/device.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-6 h-6 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center mt-0.5">
                                        <span class="text-yellow-600 dark:text-yellow-400 text-sm">4</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">Rate Limiting</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Too many verification attempts. Please wait before trying again.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Security Information -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                🛡️ Security Information
                            </h3>
                            
                            <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                                <p>
                                    Our verification system uses advanced cryptographic security measures including:
                                </p>
                                
                                <ul class="list-disc list-inside space-y-1 ml-4">
                                    <li>SHA-256 + HMAC multi-layer signatures</li>
                                    <li>IP address and user agent binding</li>
                                    <li>One-time nonce usage to prevent replay attacks</li>
                                    <li>Time-bound URLs with configurable expiration</li>
                                </ul>
                                
                                <p>
                                    These measures ensure that verification links cannot be spoofed, tampered with, or reused by unauthorized parties.
                                </p>
                            </div>
                        </div>

                        <!-- Troubleshooting Steps -->
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                🔧 Troubleshooting Steps
                            </h3>
                            
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                        1
                                    </div>
                                    <span class="text-gray-700 dark:text-gray-300">
                                        Try generating a new verification URL from your dashboard
                                    </span>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                        2
                                    </div>
                                    <span class="text-gray-700 dark:text-gray-300">
                                        Ensure you're using the same device and browser that generated the URL
                                    </span>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                        3
                                    </div>
                                    <span class="text-gray-700 dark:text-gray-300">
                                        Check if your contract is already verified in your management page
                                    </span>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                        4
                                    </div>
                                    <span class="text-gray-700 dark:text-gray-300">
                                        Wait a few minutes if you've hit rate limits, then try again
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a 
                                :href="route('enhanced-verification.demo')"
                                class="inline-flex items-center justify-center px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors font-medium"
                            >
                                🔄 Try Again
                            </a>
                            
                            <a 
                                :href="route('enhanced-verification.manage')"
                                class="inline-flex items-center justify-center px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-medium"
                            >
                                📊 Manage Verifications
                            </a>
                            
                            <a 
                                :href="route('dashboard')"
                                class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-panel dark:hover:bg-gray-700 transition-colors font-medium"
                            >
                                🏠 Dashboard
                            </a>
                        </div>

                        <!-- Support Contact -->
                        <div class="mt-8 text-center">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Still having issues? 
                                <a href="mailto:support@example.com" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-medium">
                                    Contact Support
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

<script setup>
// route function is available globally via ZiggyVue plugin
import GuestLayout from '@/Layouts/GuestLayout.vue'

// Props
const props = defineProps({
    error: {
        type: String,
        default: 'An unknown error occurred during verification'
    }
})
</script>

<style scoped>
/* Custom animations */
.shake {
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

/* Responsive design */
@media (max-width: 640px) {
    .grid-cols-2 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
}
</style>