<template>
    <div class="get-verified-page">
        <Head title="Get Verified - Blockchain Analytics" />
        
        <AuthenticatedLayout>
            <template #header>
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                            🛡️ Get Verified Badge
                        </h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Secure your smart contract with SHA-256 + HMAC cryptographic signatures
                        </p>
                    </div>
                    
                    <!-- Verification Stats -->
                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                        <div class="text-center">
                            <div class="font-semibold text-gray-900">{{ stats.total_verified }}</div>
                            <div>Verified</div>
                        </div>
                        <div class="text-center">
                            <div class="font-semibold text-gray-900">{{ stats.verified_today }}</div>
                            <div>Today</div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Enhanced Get Verified Dashboard -->
            <GetVerifiedDashboard
                :initial-stats="stats"
                :initial-verifications="recentVerifications"
                @verification-success="handleVerificationSuccess"
                @verification-error="handleVerificationError"
            />
            
            <!-- Success/Error Messages -->
            <div v-if="message" class="fixed bottom-4 right-4 z-50 max-w-md">
                <div 
                    :class="messageClass"
                    class="rounded-lg p-4 shadow-lg border"
                >
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg 
                                v-if="messageType === 'success'"
                                class="h-5 w-5 text-green-400" 
                                viewBox="0 0 20 20" 
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <svg 
                                v-else
                                class="h-5 w-5 text-red-400" 
                                viewBox="0 0 20 20" 
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p :class="messageTextClass" class="text-sm font-medium">
                                {{ message }}
                            </p>
                        </div>
                        <button @click="message = ''" class="ml-3 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import GetVerifiedDashboard from '@/Components/Verification/GetVerifiedDashboard.vue'

// Define props
const props = defineProps({
    initialStats: {
        type: Object,
        default: () => ({
            total_verified: 0,
            verified_today: 0,
            verified_this_week: 0,
            verified_this_month: 0
        })
    },
    initialVerifications: {
        type: Array,
        default: () => []
    }
})

// Get page data and CSRF token
const page = usePage()

// Helper function to get authenticated fetch headers (kept for potential future API calls)
const getAuthHeaders = () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest'
    }
}

// Reactive data (initialized with props)
const stats = ref(props.initialStats)
const recentVerifications = ref(props.initialVerifications)
const message = ref('')
const messageType = ref('success')

// Computed
const messageClass = computed(() => {
    return messageType.value === 'success' 
        ? 'bg-green-50 border border-green-200'
        : 'bg-red-50 border border-red-200'
})

const messageTextClass = computed(() => {
    return messageType.value === 'success' 
        ? 'text-green-800'
        : 'text-red-800'
})

// Methods
async function loadStats() {
    try {
        // Stats endpoint is now public, so we can call it without auth headers
        const response = await fetch('/api/verification/stats', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        
        if (response.ok) {
            const data = await response.json()
            if (data.success) {
                stats.value = data.data
            } else {
                // If response indicates failure, use default values
                stats.value = {
                    total_verified: 0,
                    verified_today: 0,
                    verified_this_week: 0,
                    verified_this_month: 0
                }
            }
        } else {
            console.error('Failed to load verification stats:', response.status, response.statusText)
            // Set fallback values on error
            stats.value = {
                total_verified: 0,
                verified_today: 0,
                verified_this_week: 0,
                verified_this_month: 0
            }
        }
    } catch (error) {
        console.error('Failed to load verification stats:', error)
        // Set fallback values on error
        stats.value = {
            total_verified: 0,
            verified_today: 0,
            verified_this_week: 0,
            verified_this_month: 0
        }
    }
}

async function loadRecentVerifications() {
    try {
        const response = await fetch('/api/verification/verified', {
            method: 'GET',
            headers: getAuthHeaders(),
            credentials: 'include' // Changed from 'same-origin' to 'include' for better session handling
        })
        
        if (response.ok) {
            const data = await response.json()
            recentVerifications.value = data.success ? (data.data?.verified_contracts?.slice(0, 5) || []) : []
        } else if (response.status === 401) {
            console.warn('Authentication required for recent verifications')
            recentVerifications.value = []
        } else {
            console.error('Failed to load recent verifications:', response.status, response.statusText)
            recentVerifications.value = []
        }
    } catch (error) {
        console.error('Failed to load recent verifications:', error)
        recentVerifications.value = []
    }
}

function handleVerificationSuccess(result) {
    message.value = 'Verification URL generated successfully! Check your email or copy the URL below.'
    messageType.value = 'success'
    
    // Refresh stats
    loadStats()
    
    // Clear message after 5 seconds
    setTimeout(() => {
        message.value = ''
    }, 5000)
}

function handleVerificationError(error) {
    message.value = `Failed to generate verification URL: ${error}`
    messageType.value = 'error'
    
    // Clear message after 5 seconds
    setTimeout(() => {
        message.value = ''
    }, 5000)
}

function truncateAddress(address) {
    if (!address) return ''
    return `${address.slice(0, 6)}...${address.slice(-4)}`
}

function formatDate(dateString) {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString()
}

// Data is now passed through Inertia props, no need for API calls on mount
// Keep the functions available for potential refresh functionality
onMounted(() => {
    // Data already loaded through Inertia props
    console.log('Verification page loaded with initial data:', {
        stats: stats.value,
        verifications: recentVerifications.value
    })
})
</script>

<style scoped>
.get-verified-page {
    min-height: 100vh;
}

/* Custom animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.verification-generator {
    animation: fadeIn 0.5s ease-out;
}
</style>