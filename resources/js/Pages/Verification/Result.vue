<template>
    <div class="min-h-screen bg-panel flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <Head title="Verification Result" />
        
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Verification Result
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Smart contract verification result
                </p>
            </div>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-2xl">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <!-- Success Result -->
                <div v-if="result && result.verified" class="text-center">
                    <!-- Success Icon -->
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                        <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        ✅ Verification Successful
                    </h3>
                    
                    <!-- Verification Details -->
                    <div class="space-y-4">
                        <div class="border-t pt-4">
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                                <div v-if="result.entity_id">
                                    <dt class="text-sm font-medium text-gray-500">Entity ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono break-all">
                                        {{ result.entity_id }}
                                    </dd>
                                </div>
                                <div v-if="result.badge_type">
                                    <dt class="text-sm font-medium text-gray-500">Badge Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ result.badge_type }}
                                    </dd>
                                </div>
                                <div v-if="result.entity_type">
                                    <dt class="text-sm font-medium text-gray-500">Entity Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ result.entity_type }}
                                    </dd>
                                </div>
                                <div v-if="result.verified_at">
                                    <dt class="text-sm font-medium text-gray-500">Verified At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ formatDate(result.verified_at) }}
                                    </dd>
                                </div>
                                <div v-if="result.issued_at">
                                    <dt class="text-sm font-medium text-gray-500">Issued At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ formatDate(result.issued_at) }}
                                    </dd>
                                </div>
                                <div v-if="result.expires_at">
                                    <dt class="text-sm font-medium text-gray-500">Expires At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ formatDate(result.expires_at) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        
                        <div v-if="result.metadata && Object.keys(result.metadata).length > 0" class="border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Additional Information</h4>
                            <div class="text-sm text-gray-600 space-y-1">
                                <div v-for="(value, key) in result.metadata" :key="key" class="flex justify-between">
                                    <span class="font-medium">{{ formatKey(key) }}:</span>
                                    <span>{{ value }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Badge URL if available -->
                    <div v-if="badge_url" class="mt-6 pt-4 border-t">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Badge URL</h4>
                        <div class="flex items-center space-x-2">
                            <input 
                                :value="badge_url" 
                                readonly 
                                class="flex-1 text-xs text-gray-600 bg-panel border border-gray-200 rounded px-2 py-1 font-mono"
                            />
                            <button 
                                @click="copyToClipboard(badge_url)"
                                class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                Copy
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Error Result -->
                <div v-else class="text-center">
                    <!-- Error Icon -->
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                        <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        ❌ Verification Failed
                    </h3>
                    
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="text-sm text-red-700">
                            {{ result?.error || 'The verification could not be completed. Please check the token and try again.' }}
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="mt-8 flex justify-center space-x-4">
                    <button 
                        @click="goBack"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        Go Back
                    </button>
                    <Link 
                        href="/get-verified"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        Generate New Badge
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'

export default {
    name: 'VerificationResult',
    components: {
        Head,
        Link
    },
    props: {
        result: {
            type: Object,
            default: () => ({})
        },
        token: {
            type: String,
            default: ''
        },
        badge_url: {
            type: String,
            default: ''
        }
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return 'N/A'
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })
        },
        
        formatKey(key) {
            return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
        },
        
        async copyToClipboard(text) {
            try {
                await navigator.clipboard.writeText(text)
                // You could add a toast notification here
                alert('Badge URL copied to clipboard!')
            } catch (err) {
                // Fallback for older browsers
                const textArea = document.createElement('textarea')
                textArea.value = text
                document.body.appendChild(textArea)
                textArea.select()
                document.execCommand('copy')
                document.body.removeChild(textArea)
                alert('Badge URL copied to clipboard!')
            }
        },
        
        goBack() {
            if (window.history.length > 1) {
                window.history.back()
            } else {
                this.$inertia.visit('/get-verified')
            }
        }
    }
}
</script>

<style scoped>
/* Additional custom styles if needed */
</style>