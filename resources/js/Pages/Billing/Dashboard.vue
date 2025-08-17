<template>
    <AuthenticatedLayout>
        <Head title="Billing Dashboard" />

        <div class="min-h-screen bg-panel py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="md:flex md:items-center md:justify-between mb-8">
                    <div class="flex-1 min-w-0">
                        <h2 class="text-3xl font-bold leading-7 text-gray-900 sm:text-4xl sm:truncate">
                            Billing Dashboard
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Manage your subscription and view usage statistics
                        </p>
                    </div>
                    <div class="mt-4 flex md:mt-0 md:ml-4">
                        <Link
                            :href="route('billing.plans')"
                            class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
                        >
                            View Plans
                        </Link>
                    </div>
                </div>

                <!-- Current Subscription Card -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <CreditCardIcon class="h-6 w-6 text-gray-400" />
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Current Plan
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ currentSubscription ? currentSubscription.name.charAt(0).toUpperCase() + currentSubscription.name.slice(1) : 'Free Tier' }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="bg-panel px-5 py-3">
                            <div class="text-sm">
                                <span v-if="currentSubscription" class="font-medium text-gray-900">
                                    Status: 
                                    <span :class="getStatusClass(currentSubscription.status)">
                                        {{ formatStatus(currentSubscription.status) }}
                                    </span>
                                </span>
                                <span v-else class="text-gray-500">
                                    Upgrade to unlock more features
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <ChartBarIcon class="h-6 w-6 text-gray-400" />
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Analyses This Month
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ currentUsage ? currentUsage.analysis : 0 }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="bg-panel px-5 py-3">
                            <div class="text-sm">
                                <span v-if="usagePercentages && usagePercentages.analysis !== undefined" class="text-gray-600">
                                    {{ Math.round(usagePercentages.analysis) }}% of limit used
                                </span>
                                <span v-else class="text-gray-500">
                                    No limit tracking
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <CloudIcon class="h-6 w-6 text-gray-400" />
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            API Calls This Month
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ formatNumber(currentUsage ? currentUsage.api_calls : 0) }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="bg-panel px-5 py-3">
                            <div class="text-sm">
                                <span v-if="usagePercentages && usagePercentages.api_calls !== undefined" class="text-gray-600">
                                    {{ Math.round(usagePercentages.api_calls) }}% of limit used
                                </span>
                                <span v-else class="text-gray-500">
                                    No limit tracking
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage Charts -->
                <div v-if="currentUsage" class="bg-white shadow rounded-lg mb-8">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-6">
                            Usage Overview
                        </h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                            <!-- Analysis Usage -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Analyses</span>
                                    <span class="text-sm text-gray-500">
                                        {{ currentUsage.analysis }} / {{ getLimit('analysis') }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div 
                                        class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                                        :style="{ width: Math.min(100, usagePercentages?.analysis || 0) + '%' }"
                                    ></div>
                                </div>
                            </div>

                            <!-- API Calls Usage -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">API Calls</span>
                                    <span class="text-sm text-gray-500">
                                        {{ formatNumber(currentUsage.api_calls) }} / {{ formatNumber(getLimit('api_calls')) }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div 
                                        class="bg-green-600 h-2 rounded-full transition-all duration-300"
                                        :style="{ width: Math.min(100, usagePercentages?.api_calls || 0) + '%' }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Tokens Usage -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">AI Tokens</span>
                                    <span class="text-sm text-gray-500">
                                        {{ formatNumber(currentUsage.tokens) }} / {{ formatNumber(getLimit('tokens')) }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div 
                                        class="bg-yellow-600 h-2 rounded-full transition-all duration-300"
                                        :style="{ width: Math.min(100, usagePercentages?.tokens || 0) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="currentUsage" class="mt-4 text-sm text-gray-500">
                            Billing period: {{ currentUsage.period_start }} - {{ currentUsage.period_end }}
                        </div>
                    </div>
                </div>

                <!-- Subscription Management -->
                <div v-if="currentSubscription" class="bg-white shadow rounded-lg mb-8">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Subscription Management
                        </h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Plan</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ currentSubscription.name.charAt(0).toUpperCase() + currentSubscription.name.slice(1) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <span :class="getStatusClass(currentSubscription.status)">
                                        {{ formatStatus(currentSubscription.status) }}
                                    </span>
                                </dd>
                            </div>
                            <div v-if="currentSubscription.trial_ends_at">
                                <dt class="text-sm font-medium text-gray-500">Trial Ends</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ formatDate(currentSubscription.trial_ends_at) }}
                                </dd>
                            </div>
                            <div v-if="currentSubscription.ends_at">
                                <dt class="text-sm font-medium text-gray-500">
                                    {{ currentSubscription.cancelled ? 'Ends' : 'Next Billing' }}
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ formatDate(currentSubscription.ends_at) }}
                                </dd>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link
                                :href="route('billing.plans')"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
                            >
                                Change Plan
                            </Link>
                            
                            <button
                                v-if="currentSubscription.cancelled && currentSubscription.on_grace_period"
                                @click="resumeSubscription"
                                :disabled="processing"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                            >
                                Resume Subscription
                            </button>
                            
                            <button
                                v-else-if="!currentSubscription.cancelled"
                                @click="cancelSubscription"
                                :disabled="processing"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                            >
                                Cancel Subscription
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Billing History -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Recent Invoices
                            </h3>
                            <Link
                                :href="route('billing.history')"
                                class="text-sm text-brand-500 hover:text-indigo-500"
                            >
                                View all
                            </Link>
                        </div>
                        
                        <div v-if="invoices && invoices.length > 0" class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-panel">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="invoice in invoices.slice(0, 5)" :key="invoice.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ invoice.date }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ invoice.total }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="getInvoiceStatusClass(invoice.status)">
                                                {{ invoice.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a
                                                :href="route('billing.invoices.download', invoice.id)"
                                                class="text-brand-500 hover:text-indigo-900"
                                                target="_blank"
                                            >
                                                Download
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div v-else class="text-center py-6">
                            <p class="text-sm text-gray-500">No invoices available</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { 
    CreditCardIcon, 
    ChartBarIcon, 
    CloudIcon 
} from '@heroicons/vue/24/outline'

// Props
const props = defineProps({
    plans: Object,
    currentSubscription: Object,
    currentUsage: Object,
    usagePercentages: Object,
    paymentMethods: Array,
    defaultPaymentMethod: Object,
})

// State
const processing = ref(false)
const invoices = ref([])

// Computed
const planLimits = computed(() => {
    if (!props.currentSubscription) {
        return {
            analysis_limit: 3,
            api_calls_limit: 100,
            tokens_limit: 10000,
        }
    }
    
    const planName = props.currentSubscription.name
    const plan = props.plans[planName]
    return plan?.features || {}
})

// Methods
const formatNumber = (num) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M'
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K'
    }
    return num?.toLocaleString() || '0'
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const formatStatus = (status) => {
    return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const getStatusClass = (status) => {
    const classes = {
        'active': 'text-green-800 bg-green-100 px-2 py-1 rounded-full text-xs',
        'trialing': 'text-blue-800 bg-blue-100 px-2 py-1 rounded-full text-xs',
        'canceled': 'text-red-800 bg-red-100 px-2 py-1 rounded-full text-xs',
        'past_due': 'text-yellow-800 bg-yellow-100 px-2 py-1 rounded-full text-xs',
    }
    return classes[status] || 'text-gray-800 bg-ink px-2 py-1 rounded-full text-xs'
}

const getInvoiceStatusClass = (status) => {
    const classes = {
        'paid': 'inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800',
        'open': 'inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800',
        'void': 'inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-ink text-gray-800',
        'uncollectible': 'inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800',
    }
    return classes[status] || 'inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-ink text-gray-800'
}

const getLimit = (type) => {
    const key = type + '_limit'
    return formatNumber(planLimits.value[key]) || 'Unlimited'
}

const cancelSubscription = async () => {
    if (!confirm('Are you sure you want to cancel your subscription? You will continue to have access until the end of your billing period.')) {
        return
    }
    
    processing.value = true
    
    try {
        await router.delete(route('billing.subscription.cancel'))
        // The page will reload with updated subscription data
    } catch (error) {
        console.error('Error cancelling subscription:', error)
    } finally {
        processing.value = false
    }
}

const resumeSubscription = async () => {
    processing.value = true
    
    try {
        await router.post(route('billing.subscription.resume'))
        // The page will reload with updated subscription data
    } catch (error) {
        console.error('Error resuming subscription:', error)
    } finally {
        processing.value = false
    }
}

const loadBillingHistory = async () => {
    try {
        const response = await fetch(route('billing.history'))
        const data = await response.json()
        invoices.value = data.invoices || []
    } catch (error) {
        console.error('Error loading billing history:', error)
    }
}

// Lifecycle
onMounted(() => {
    loadBillingHistory()
})
</script>