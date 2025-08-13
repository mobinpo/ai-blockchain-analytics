<template>
    <div class="min-h-screen bg-panel flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <Head title="Verification Badge" />
        
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Smart Contract Verification Badge
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Verify the authenticity of this smart contract
                </p>
            </div>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-2xl">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <!-- Verification Badge Display -->
                <div v-if="badgeData" class="text-center">
                    <VerificationBadgeComponent 
                        :badge-data="badgeData"
                        :show-verification-link="false"
                        class="mx-auto mb-6"
                    />
                    
                    <!-- Badge Information -->
                    <div class="space-y-4">
                        <div class="border-t pt-4">
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Contract Address</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono break-all">
                                        {{ badgeData.contract_address }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Verification Level</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ badgeData.verification_level || 'Standard' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Verified Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ formatDate(badgeData.created_at) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Verified
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        
                        <div v-if="badgeData.verification_details" class="border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Verification Details</h4>
                            <div class="text-sm text-gray-600">
                                <p v-for="detail in badgeData.verification_details" :key="detail" class="mb-1">
                                    • {{ detail }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div v-else-if="loading" class="text-center">
                    <div class="animate-pulse">
                        <div class="bg-gray-200 h-32 w-32 rounded-full mx-auto mb-4"></div>
                        <div class="bg-gray-200 h-4 w-48 rounded mx-auto mb-2"></div>
                        <div class="bg-gray-200 h-4 w-32 rounded mx-auto"></div>
                    </div>
                </div>
                
                <!-- Error State -->
                <div v-else-if="error" class="text-center">
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    Verification Failed
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    {{ error }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Manual Verification Form -->
                <div class="mt-8 border-t pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Verify Another Badge
                    </h3>
                    <form @submit.prevent="verifyBadge" class="space-y-4">
                        <div>
                            <label for="badge_id" class="block text-sm font-medium text-gray-700">
                                Badge ID
                            </label>
                            <input 
                                v-model="form.badge_id" 
                                type="text" 
                                id="badge_id"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter badge ID"
                            />
                        </div>
                        <div>
                            <label for="signature" class="block text-sm font-medium text-gray-700">
                                Signature
                            </label>
                            <input 
                                v-model="form.signature" 
                                type="text" 
                                id="signature"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter signature"
                            />
                        </div>
                        <button 
                            type="submit" 
                            :disabled="!form.badge_id || !form.signature || verifying"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="verifying" class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Verifying...
                            </span>
                            <span v-else>Verify Badge</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3'
import VerificationBadgeComponent from '@/Components/Verification/VerificationBadge.vue'

export default {
    name: 'VerificationBadge',
    components: {
        Head,
        VerificationBadgeComponent
    },
    props: {
        badge_id: String,
        signature: String,
        type: String
    },
    data() {
        return {
            badgeData: null,
            loading: false,
            error: null,
            verifying: false,
            form: {
                badge_id: this.badge_id || '',
                signature: this.signature || ''
            }
        }
    },
    mounted() {
        if (this.badge_id && this.signature) {
            this.loadBadgeData()
        }
    },
    methods: {
        async loadBadgeData() {
            this.loading = true
            this.error = null
            
            try {
                const response = await fetch(`/api/verification-badge/verify`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    },
                    body: JSON.stringify({
                        badge_id: this.badge_id,
                        signature: this.signature
                    })
                })
                
                const data = await response.json()
                
                if (response.ok && data.success) {
                    this.badgeData = data.data
                } else {
                    this.error = data.message || 'Failed to verify badge'
                }
            } catch (error) {
                console.error('Verification error:', error)
                this.error = 'Unable to connect to verification service'
            } finally {
                this.loading = false
            }
        },
        
        async verifyBadge() {
            if (!this.form.badge_id || !this.form.signature) return
            
            this.verifying = true
            this.error = null
            this.badgeData = null
            
            try {
                const response = await fetch(`/api/verification-badge/verify`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    },
                    body: JSON.stringify({
                        badge_id: this.form.badge_id,
                        signature: this.form.signature
                    })
                })
                
                const data = await response.json()
                
                if (response.ok && data.success) {
                    this.badgeData = data.data
                    // Update URL without reloading
                    window.history.pushState({}, '', `/verification/badge?badge_id=${this.form.badge_id}&signature=${this.form.signature}`)
                } else {
                    this.error = data.message || 'Failed to verify badge'
                }
            } catch (error) {
                console.error('Verification error:', error)
                this.error = 'Unable to connect to verification service'
            } finally {
                this.verifying = false
            }
        },
        
        formatDate(dateString) {
            if (!dateString) return 'N/A'
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })
        }
    }
}
</script>

<style scoped>
/* Additional custom styles if needed */
</style>
