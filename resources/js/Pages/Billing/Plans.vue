<template>
    <AuthenticatedLayout>
        <Head title="Subscription Plans" />

        <div class="min-h-screen bg-panel py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 sm:text-4xl">
                        Choose Your Plan
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        Select the perfect plan for your blockchain analysis needs
                    </p>
                </div>

                <!-- Billing Toggle -->
                <div class="flex justify-center mb-8">
                    <div class="flex items-center bg-white rounded-lg p-1 shadow-sm">
                        <button
                            @click="billingInterval = 'monthly'"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-md transition-all duration-200',
                                billingInterval === 'monthly'
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Monthly
                        </button>
                        <button
                            @click="billingInterval = 'yearly'"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 relative',
                                billingInterval === 'yearly'
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Yearly
                            <span class="absolute -top-1 -right-1 bg-green-500 text-white text-xs px-1 py-0.5 rounded-full">
                                Save 17%
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Plans Grid -->
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <div
                        v-for="(plan, planKey) in plans"
                        :key="planKey"
                        :class="[
                            'rounded-2xl shadow-xl relative',
                            planKey === 'professional' 
                                ? 'border-2 border-indigo-600 bg-white transform scale-105 z-10' 
                                : 'border border-gray-200 bg-white'
                        ]"
                    >
                        <!-- Popular Badge -->
                        <div v-if="planKey === 'professional'" class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                            <span class="bg-indigo-600 text-white px-4 py-1 rounded-full text-sm font-medium">
                                Most Popular
                            </span>
                        </div>

                        <div class="p-8">
                            <!-- Plan Header -->
                            <div class="text-center">
                                <h3 class="text-2xl font-bold text-gray-900">
                                    {{ plan.name }}
                                </h3>
                                <p class="mt-2 text-gray-600">
                                    {{ plan.description }}
                                </p>
                                
                                <!-- Pricing -->
                                <div class="mt-6">
                                    <div class="flex items-center justify-center">
                                        <span class="text-4xl font-bold text-gray-900">
                                            ${{ getCurrentPrice(plan) }}
                                        </span>
                                        <span class="text-gray-600 ml-2">
                                            /{{ billingInterval === 'yearly' ? 'year' : 'month' }}
                                        </span>
                                    </div>
                                    <div v-if="billingInterval === 'yearly'" class="mt-1 text-sm text-gray-500">
                                        ${{ (plan.yearly_price / 12).toFixed(2) }}/month billed annually
                                    </div>
                                </div>
                            </div>

                            <!-- Features List -->
                            <div class="mt-8">
                                <ul class="space-y-4">
                                    <li v-for="feature in plan.features_list.Features" :key="feature" class="flex items-start">
                                        <CheckIcon class="h-5 w-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" />
                                        <span class="text-gray-700">{{ feature }}</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Limits -->
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <div class="grid grid-cols-1 gap-3 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Analyses per month</span>
                                        <span class="font-medium text-gray-900">
                                            {{ formatLimit(plan.features_list['Analysis per month']) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">API calls per month</span>
                                        <span class="font-medium text-gray-900">
                                            {{ formatLimit(plan.features_list['API calls per month']) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">AI tokens per month</span>
                                        <span class="font-medium text-gray-900">
                                            {{ formatLimit(plan.features_list['AI tokens per month']) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Projects</span>
                                        <span class="font-medium text-gray-900">
                                            {{ formatLimit(plan.features_list['Projects']) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Support</span>
                                        <span class="font-medium text-gray-900">
                                            {{ plan.features_list['Support'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- CTA Button -->
                            <div class="mt-8">
                                <button
                                    v-if="!isCurrentPlan(planKey)"
                                    @click="selectPlan(planKey)"
                                    :disabled="processing"
                                    :class="[
                                        'w-full py-3 px-4 rounded-lg font-semibold text-center transition-all duration-200 disabled:opacity-50',
                                        planKey === 'professional'
                                            ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg'
                                            : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                    ]"
                                >
                                    <span v-if="processing && selectedPlan === planKey">
                                        Processing...
                                    </span>
                                    <span v-else>
                                        {{ getButtonText(planKey) }}
                                    </span>
                                </button>
                                <div v-else class="w-full py-3 px-4 rounded-lg font-semibold text-center bg-ink text-gray-600">
                                    Current Plan
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Free Tier Card -->
                <div class="mt-12 max-w-md mx-auto">
                    <div class="bg-ink rounded-lg p-6 text-center">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            Free Tier
                        </h3>
                        <p class="text-gray-600 mb-4">
                            Try our platform with limited features
                        </p>
                        <div class="text-sm text-gray-600 space-y-1">
                            <div>3 analyses per month</div>
                            <div>100 API calls per month</div>
                            <div>10K AI tokens per month</div>
                            <div>2 projects</div>
                            <div>Community support</div>
                        </div>
                        <div v-if="!currentPlan" class="mt-4 text-green-600 font-medium">
                            You're currently on the free tier
                        </div>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div class="mt-16 max-w-3xl mx-auto">
                    <h3 class="text-2xl font-bold text-gray-900 text-center mb-8">
                        Frequently Asked Questions
                    </h3>
                    <div class="space-y-6">
                        <div v-for="faq in faqs" :key="faq.question" class="bg-white rounded-lg p-6 shadow-sm">
                            <h4 class="font-semibold text-gray-900 mb-2">
                                {{ faq.question }}
                            </h4>
                            <p class="text-gray-600">
                                {{ faq.answer }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <PaymentModal
            :show="showPaymentModal"
            :plan="selectedPlanData"
            :interval="billingInterval"
            @close="showPaymentModal = false"
            @success="handlePaymentSuccess"
        />
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PaymentModal from '@/Components/Billing/PaymentModal.vue'
import { CheckIcon } from '@heroicons/vue/24/solid'

// Props
const props = defineProps({
    plans: Object,
    currentPlan: String,
})

// State
const billingInterval = ref('monthly')
const processing = ref(false)
const selectedPlan = ref(null)
const showPaymentModal = ref(false)
const selectedPlanData = ref(null)

// Computed
const currentPlan = computed(() => props.currentPlan)

// Data
const faqs = [
    {
        question: "Can I change my plan at any time?",
        answer: "Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately and your billing will be prorated accordingly."
    },
    {
        question: "What happens if I exceed my plan limits?",
        answer: "If you exceed your plan limits, additional usage will be charged at our standard overage rates. You'll receive notifications when approaching your limits."
    },
    {
        question: "Do you offer refunds?",
        answer: "We offer a 14-day money-back guarantee for new subscriptions. Contact our support team if you're not satisfied with our service."
    },
    {
        question: "Can I cancel my subscription?",
        answer: "Yes, you can cancel your subscription at any time. You'll continue to have access to your plan's features until the end of your billing period."
    },
    {
        question: "What payment methods do you accept?",
        answer: "We accept all major credit cards (Visa, MasterCard, American Express) and bank transfers for enterprise customers."
    }
]

// Methods
const getCurrentPrice = (plan) => {
    return billingInterval.value === 'yearly' ? plan.yearly_price : plan.monthly_price
}

const formatLimit = (limit) => {
    if (limit === 'Unlimited') return limit
    if (typeof limit === 'string' && limit.includes('K')) return limit
    if (typeof limit === 'string' && limit.includes('M')) return limit
    return limit?.toLocaleString() || '0'
}

const isCurrentPlan = (planKey) => {
    return currentPlan.value === planKey
}

const getButtonText = (planKey) => {
    if (!currentPlan.value) {
        return 'Get Started'
    }
    
    const planOrder = { starter: 1, professional: 2, enterprise: 3 }
    const currentOrder = planOrder[currentPlan.value] || 0
    const selectedOrder = planOrder[planKey] || 0
    
    if (selectedOrder > currentOrder) {
        return 'Upgrade'
    } else if (selectedOrder < currentOrder) {
        return 'Downgrade'
    }
    
    return 'Select Plan'
}

const selectPlan = (planKey) => {
    selectedPlan.value = planKey
    selectedPlanData.value = props.plans[planKey]
    showPaymentModal.value = true
}

const handlePaymentSuccess = (response) => {
    showPaymentModal.value = false
    processing.value = false
    
    // Redirect to billing dashboard
    router.visit(route('billing.index'), {
        onSuccess: () => {
            // Show success message
            console.log('Subscription updated successfully!')
        }
    })
}
</script>