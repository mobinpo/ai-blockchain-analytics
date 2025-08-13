<template>
    <div class="min-h-screen bg-panel py-12 sm:px-6 lg:px-8">
        <Head title="Verification Badge Generator" />
        
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Badge Generator
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Create verification badges for your smart contracts
                </p>
            </div>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-2xl">
            <VerificationGeneratorComponent />
        </div>
        
        <!-- Information Section -->
        <div class="mt-12 sm:mx-auto sm:w-full sm:max-w-4xl">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        How Verification Badges Work
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center">
                            <div class="w-12 h-12 mx-auto bg-blue-100 rounded-lg flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Generate</h4>
                            <p class="text-sm text-gray-500">
                                Enter your contract details and generate a cryptographically signed verification badge.
                            </p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-12 h-12 mx-auto bg-green-100 rounded-lg flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                </svg>
                            </div>
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Share</h4>
                            <p class="text-sm text-gray-500">
                                Get a shareable verification URL and embed code for your website or documentation.
                            </p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-12 h-12 mx-auto bg-purple-100 rounded-lg flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Verify</h4>
                            <p class="text-sm text-gray-500">
                                Anyone can verify the authenticity of your contract using the generated badge.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3'
import VerificationGeneratorComponent from '@/Components/Verification/VerificationGenerator.vue'

export default {
    name: 'VerificationGenerator',
    components: {
        Head,
        VerificationGeneratorComponent
    }
}
</script>

<style scoped>
/* Additional custom styles if needed */
</style>
