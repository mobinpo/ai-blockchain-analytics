<template>
    <TransitionRoot as="template" :show="show">
        <Dialog as="div" class="relative z-50" @close="$emit('close')">
            <TransitionChild
                as="template"
                enter="ease-out duration-300"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-200"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-panel bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                            <div class="absolute right-0 top-0 hidden pr-4 pt-4 sm:block">
                                <button
                                    type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                                    @click="$emit('close')"
                                >
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <CreditCardIcon class="h-6 w-6 text-brand-500" aria-hidden="true" />
                                </div>
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                    <DialogTitle as="h3" class="text-lg font-semibold leading-6 text-gray-900">
                                        Subscribe to {{ plan?.name }}
                                    </DialogTitle>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500">
                                            You're about to subscribe to the {{ plan?.name }} plan.
                                        </p>
                                        <div class="mt-4 p-4 bg-panel rounded-lg">
                                            <div class="flex justify-between items-center">
                                                <span class="font-medium">{{ plan?.name }} Plan</span>
                                                <span class="font-bold">
                                                    ${{ getCurrentPrice() }}
                                                    <span class="text-sm font-normal text-gray-500">
                                                        /{{ interval }}
                                                    </span>
                                                </span>
                                            </div>
                                            <div v-if="interval === 'yearly'" class="text-sm text-green-600 mt-1">
                                                Save ${{ getYearlySavings() }} compared to monthly billing
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stripe Elements Container -->
                            <div class="mt-6">
                                <div v-if="!stripeLoaded" class="text-center py-4">
                                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                                    <p class="mt-2 text-sm text-gray-500">Loading payment form...</p>
                                </div>
                                
                                <div v-else>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Card Information
                                    </label>
                                    <div 
                                        ref="cardElement" 
                                        class="p-3 border border-gray-300 rounded-md bg-white"
                                    ></div>
                                    
                                    <div v-if="cardError" class="mt-2 text-sm text-red-600">
                                        {{ cardError }}
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Privacy -->
                            <div class="mt-6">
                                <div class="flex items-start">
                                    <input
                                        id="terms"
                                        v-model="acceptedTerms"
                                        type="checkbox"
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300 rounded"
                                    >
                                    <label for="terms" class="ml-2 block text-sm text-gray-700">
                                        I agree to the 
                                        <a href="#" class="text-brand-500 hover:text-indigo-500">Terms of Service</a> 
                                        and 
                                        <a href="#" class="text-brand-500 hover:text-indigo-500">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-6 flex flex-col sm:flex-row-reverse gap-3">
                                <button
                                    type="button"
                                    :disabled="!canSubmit || processing"
                                    @click="handleSubscribe"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                                >
                                    <span v-if="processing">Processing...</span>
                                    <span v-else>Subscribe Now</span>
                                </button>
                                <button
                                    type="button"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-panel sm:mt-0 sm:w-auto"
                                    @click="$emit('close')"
                                >
                                    Cancel
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { CreditCardIcon, XMarkIcon } from '@heroicons/vue/24/outline'

// Props
const props = defineProps({
    show: Boolean,
    plan: Object,
    interval: String,
})

// Emits
const emit = defineEmits(['close', 'success'])

// State
const stripeLoaded = ref(false)
const stripe = ref(null)
const cardElement = ref(null)
const card = ref(null)
const cardError = ref('')
const processing = ref(false)
const acceptedTerms = ref(false)

// Computed
const canSubmit = computed(() => {
    return stripeLoaded.value && acceptedTerms.value && !processing.value
})

const getCurrentPrice = () => {
    if (!props.plan) return '0'
    return props.interval === 'yearly' ? props.plan.yearly_price : props.plan.monthly_price
}

const getYearlySavings = () => {
    if (!props.plan) return '0'
    const monthlyTotal = props.plan.monthly_price * 12
    const yearlySavings = monthlyTotal - props.plan.yearly_price
    return yearlySavings.toFixed(2)
}

// Methods
const loadStripe = async () => {
    if (window.Stripe) {
        stripe.value = window.Stripe(import.meta.env.VITE_STRIPE_KEY)
        stripeLoaded.value = true
        setupCardElement()
        return
    }

    // Load Stripe.js
    const script = document.createElement('script')
    script.src = 'https://js.stripe.com/v3/'
    script.onload = () => {
        stripe.value = window.Stripe(import.meta.env.VITE_STRIPE_KEY)
        stripeLoaded.value = true
        setupCardElement()
    }
    document.head.appendChild(script)
}

const setupCardElement = () => {
    if (!stripe.value || !cardElement.value) return

    const elements = stripe.value.elements()
    card.value = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#424770',
                '::placeholder': {
                    color: '#aab7c4',
                },
            },
        },
    })

    card.value.mount(cardElement.value)
    
    card.value.addEventListener('change', (event) => {
        cardError.value = event.error ? event.error.message : ''
    })
}

const handleSubscribe = async () => {
    if (!stripe.value || !card.value || !props.plan) return

    processing.value = true
    cardError.value = ''

    try {
        // Create payment method
        const { error: methodError, paymentMethod } = await stripe.value.createPaymentMethod({
            type: 'card',
            card: card.value,
        })

        if (methodError) {
            cardError.value = methodError.message
            processing.value = false
            return
        }

        // Submit to backend
        const response = await fetch(route('billing.subscribe'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                plan: props.plan.key,
                interval: props.interval,
                payment_method: paymentMethod.id,
            }),
        })

        const result = await response.json()

        if (result.success) {
            emit('success', result)
        } else if (result.requires_action) {
            // Handle 3D Secure or other authentication
            const { error: confirmError } = await stripe.value.confirmCardPayment(
                result.client_secret
            )

            if (confirmError) {
                cardError.value = confirmError.message
            } else {
                emit('success', result)
            }
        } else {
            cardError.value = result.error || 'An error occurred while processing your payment.'
        }
    } catch (error) {
        console.error('Payment error:', error)
        cardError.value = 'An unexpected error occurred. Please try again.'
    } finally {
        processing.value = false
    }
}

// Watchers
watch(() => props.show, (newShow) => {
    if (newShow && !stripeLoaded.value) {
        loadStripe()
    }
})

// Lifecycle
onMounted(() => {
    if (props.show) {
        loadStripe()
    }
})
</script>