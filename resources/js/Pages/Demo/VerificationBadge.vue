<template>
    <Head title="Verification Badge Demo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Verification Badge Demo
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">
                                Verification Badge Demo
                            </h3>
                            <p class="text-gray-600">
                                This demo shows verification badge functionality. 
                                For the full demo experience, please visit the 
                                <inertia-link :href="route('verification-badge-demo')" class="text-blue-600 hover:text-blue-800">
                                    Verification Badge Demo page
                                </inertia-link>.
                            </p>
                        </div>

                        <!-- Simple verification badge display -->
                        <div class="border rounded-lg p-4">
                            <VerificationBadge 
                                :verified="true"
                                :contract-address="'0x1234567890123456789012345678901234567890'"
                                :verification-date="new Date()"
                                class="mb-4"
                            />
                            
                            <div class="text-sm text-gray-500">
                                Sample verification badge component
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import VerificationBadge from '@/Components/Verification/VerificationBadge.vue'
</script>
