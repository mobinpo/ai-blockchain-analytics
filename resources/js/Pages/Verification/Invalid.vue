<template>
    <div class="min-h-screen bg-panel flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <Head title="Invalid Verification" />
        
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-red-100 mb-4">
                    <span class="text-4xl">❌</span>
                </div>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Invalid Verification
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    This verification badge is invalid or has expired
                </p>
            </div>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="text-center space-y-4">
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <span class="text-red-400 text-lg">⚠️</span>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    Verification Failed
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p>This verification badge could not be validated. Possible reasons:</p>
                                    <ul class="list-disc list-inside mt-2 space-y-1">
                                        <li>The verification has expired</li>
                                        <li>The badge signature is invalid</li>
                                        <li>The contract address has changed</li>
                                        <li>The verification URL is malformed</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <Link 
                            :href="route('verification.index')"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            Get New Verification
                        </Link>
                        
                        <Link 
                            href="/"
                            class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            Return to Home
                        </Link>
                    </div>

                    <div class="mt-6 text-xs text-gray-500">
                        <p>Need help? Contact our support team for assistance with verification issues.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
    error: {
        type: String,
        default: 'Invalid verification badge'
    },
    details: {
        type: Object,
        default: () => ({})
    }
})
</script>
