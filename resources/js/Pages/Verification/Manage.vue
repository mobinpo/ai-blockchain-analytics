<template>
    <div class="manage-enhanced-verification">
        <Head title="Enhanced Verification Management" />
        
        <AuthenticatedLayout>
            <template #header>
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Enhanced Verification Management
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Manage your enhanced verification badges with SHA-256 + HMAC security
                        </p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <inertia-link
                            :href="route('enhanced-verification.generate')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Generate New Verification
                        </inertia-link>
                    </div>
                </div>
            </template>

            <div class="py-12">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    <!-- Quick Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white dark:bg-panel overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Total Verifications</dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ verifications.length }}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-panel overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Active</dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ stats.active }}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-panel overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Pending</dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ stats.pending }}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-panel overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Expired</dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ stats.expired }}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Verifications List -->
                    <div class="bg-white dark:bg-panel shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                Your Enhanced Verifications
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Manage and monitor your enhanced verification badges
                            </p>
                        </div>

                        <div class="overflow-hidden">
                            <div v-if="verifications.length === 0" class="p-6 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No verifications</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first enhanced verification.</p>
                                <div class="mt-6">
                                    <inertia-link
                                        :href="route('enhanced-verification.generate')"
                                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                                    >
                                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Generate Verification
                                    </inertia-link>
                                </div>
                            </div>

                            <div v-else>
                                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <li v-for="verification in verifications" :key="verification.id" class="px-6 py-4">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0">
                                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                        <svg class="h-6 w-6" :class="{
                                                            'text-green-600': verification.status === 'verified',
                                                            'text-yellow-600': verification.status === 'pending',
                                                            'text-red-600': verification.status === 'expired' || verification.status === 'revoked'
                                                        }" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="flex items-center">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                            {{ verification.contract_address }}
                                                        </div>
                                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                                                            'bg-green-100 text-green-800': verification.status === 'verified',
                                                            'bg-yellow-100 text-yellow-800': verification.status === 'pending',
                                                            'bg-red-100 text-red-800': verification.status === 'expired' || verification.status === 'revoked'
                                                        }">
                                                            {{ verification.status }}
                                                        </span>
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        Created: {{ new Date(verification.created_at).toLocaleDateString() }}
                                                        <span v-if="verification.verified_at"> • Verified: {{ new Date(verification.verified_at).toLocaleDateString() }}</span>
                                                        <span v-if="verification.expires_at"> • Expires: {{ new Date(verification.expires_at).toLocaleDateString() }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <button
                                                    v-if="verification.status === 'verified'"
                                                    @click="copyBadgeCode(verification)"
                                                    class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-panel"
                                                >
                                                    Copy Badge
                                                </button>
                                                <button
                                                    v-if="verification.status === 'verified'"
                                                    @click="revoke(verification.id)"
                                                    class="inline-flex items-center px-3 py-1 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50"
                                                >
                                                    Revoke
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref } from 'vue'
import axios from 'axios'

// Props from controller
const props = defineProps({
    verifications: Array,
    stats: Object
})

// Methods
const copyBadgeCode = (verification) => {
    const badgeCode = `<div data-verification-badge="${verification.contract_address}" data-verification-url="${verification.verification_url}"></div>`
    navigator.clipboard.writeText(badgeCode).then(() => {
        alert('Badge code copied to clipboard!')
    })
}

const revoke = async (verificationId) => {
    if (confirm('Are you sure you want to revoke this verification? This action cannot be undone.')) {
        try {
            await axios.post(route('enhanced-verification.revoke'), {
                verification_id: verificationId
            })
            
            // Reload the page to refresh data
            window.location.reload()
        } catch (error) {
            alert('Error revoking verification: ' + (error.response?.data?.message || error.message))
        }
    }
}
</script>
