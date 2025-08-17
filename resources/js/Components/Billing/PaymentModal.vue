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
                                    <!-- Payment Information -->
                                    <div class="space-y-4">
                                        <form autocomplete="on" data-secure="true">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    Email Address
                                                </label>
                                                <input 
                                                    type="email" 
                                                    name="email"
                                                    autocomplete="email"
                                                    class="w-full p-3 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="you@example.com"
                                                />
                                            </div>
                                        </form>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Card Information
                                            </label>
                                            <div 
                                                ref="cardElement" 
                                                class="p-3 border border-gray-300 rounded-md bg-white"
                                                data-testid="card-element"
                                                role="textbox"
                                                aria-label="Credit card information"
                                            ></div>
                                            
                                            <!-- Test Card Info -->
                                            <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                                <p class="text-xs text-blue-700 font-medium">Development Mode - Use Test Cards:</p>
                                                <div class="text-xs text-blue-600 mt-1 space-y-1">
                                                    <div><strong>Visa:</strong> 4242 4242 4242 4242</div>
                                                    <div><strong>Visa (debit):</strong> 4000 0566 5566 5556</div>
                                                    <div><strong>Mastercard:</strong> 5555 5555 5555 4444</div>
                                                    <div><strong>Any future date, any CVC, any ZIP</strong></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
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
                                        <a href="https://sentimentshield.com/terms" target="_blank" rel="noopener" class="text-brand-500 hover:text-indigo-500">Terms of Service</a> 
                                        and 
                                        <a href="https://sentimentshield.com/privacy" target="_blank" rel="noopener" class="text-brand-500 hover:text-indigo-500">Privacy Policy</a>
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
    return stripeLoaded.value && acceptedTerms.value && !processing.value && card.value
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
    const stripeKey = import.meta.env.VITE_STRIPE_KEY
    
    console.log('Loading Stripe with key:', stripeKey ? stripeKey.substring(0, 20) + '...' : 'NOT FOUND')
    
    if (!stripeKey) {
        console.error('VITE_STRIPE_KEY not found in environment variables')
        cardError.value = 'Payment configuration error. Please contact support.'
        return
    }

    if (window.Stripe) {
        try {
            stripe.value = window.Stripe(stripeKey)
            stripeLoaded.value = true
            // Add a small delay to ensure DOM is ready
            setTimeout(() => {
                setupCardElement()
            }, 100)
        } catch (error) {
            console.error('Error initializing Stripe:', error)
            cardError.value = 'Failed to initialize payment system.'
        }
        return
    }

    // Load Stripe.js
    const script = document.createElement('script')
    script.src = 'https://js.stripe.com/v3/'
    script.onload = () => {
        try {
            stripe.value = window.Stripe(stripeKey)
            stripeLoaded.value = true
            // Add a small delay to ensure DOM is ready
            setTimeout(() => {
                setupCardElement()
            }, 100)
        } catch (error) {
            console.error('Error initializing Stripe after script load:', error)
            cardError.value = 'Failed to initialize payment system.'
        }
    }
    script.onerror = () => {
        console.error('Failed to load Stripe.js')
        cardError.value = 'Failed to load payment system.'
    }
    document.head.appendChild(script)
}

const setupCardElement = () => {
    console.log('Setting up card element...', {
        stripe: !!stripe.value,
        cardElement: !!cardElement.value,
        cardElementRef: cardElement.value
    })
    
    if (!stripe.value) {
        console.error('Stripe not initialized')
        return
    }
    
    if (!cardElement.value) {
        console.error('Card element ref not found')
        return
    }

    try {
        const elements = stripe.value.elements({
            fonts: [
                {
                    cssSrc: 'https://fonts.googleapis.com/css?family=Roboto'
                }
            ],
            locale: 'en',
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#6366f1',
                }
            }
        })
        
        card.value = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            },
            hidePostalCode: false,
            iconStyle: 'solid',
            fields: {
                number: {
                    placeholder: '1234 1234 1234 1234'
                },
                expirationDate: {
                    placeholder: 'MM/YY'
                },
                cvc: {
                    placeholder: 'CVC'
                },
                postalCode: {
                    placeholder: 'ZIP Code'
                }
            }
        })

        console.log('Mounting card element...')
        card.value.mount(cardElement.value)
        console.log('Card element mounted successfully')
        
        card.value.addEventListener('change', (event) => {
            console.log('Card change event:', event)
            cardError.value = event.error ? event.error.message : ''
        })
    } catch (error) {
        console.error('Error setting up card element:', error)
        cardError.value = 'Failed to initialize payment form.'
    }
}

const handleSubscribe = async () => {
    console.log('handleSubscribe called', {
        stripe: !!stripe.value,
        card: !!card.value,
        plan: !!props.plan,
        canSubmit: canSubmit.value
    })
    
    if (!stripe.value || !card.value || !props.plan) {
        console.error('Missing required dependencies for subscription')
        return
    }

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
        console.log('Submitting subscription request:', {
            plan: props.plan.key,
            interval: props.interval,
            payment_method: paymentMethod.id,
        })

        const response = await fetch('/billing/subscribe', {
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

        console.log('Response status:', response.status)
        console.log('Response headers:', response.headers)

        const result = await response.json()
        console.log('Subscription result:', result)

        if (result.success) {
            console.log('Subscription successful, emitting success event')
            emit('success', result)
        } else if (result.requires_action) {
            console.log('Subscription requires action:', result.client_secret)
            // Handle 3D Secure or other authentication
            const { error: confirmError } = await stripe.value.confirmCardPayment(
                result.client_secret
            )

            if (confirmError) {
                console.error('Payment confirmation error:', confirmError)
                cardError.value = confirmError.message
            } else {
                console.log('Payment confirmed successfully')
                emit('success', result)
            }
        } else {
            console.error('Subscription failed:', result)
            // Show the actual error message from the server
            cardError.value = result.error || 'An error occurred while processing your payment.'
        }
    } catch (error) {
        console.error('Payment error:', error)
        
        // More specific error handling
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            cardError.value = 'Network error. Please check your connection and try again.'
        } else if (error.message.includes('route')) {
            cardError.value = 'Billing service temporarily unavailable. Please try again.'
        } else {
            cardError.value = `Error: ${error.message || 'An unexpected error occurred. Please try again.'}`
        }
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

// Force secure context for autofill
const forceSecureContext = () => {
    if (window.location.protocol !== 'https:') {
        console.log('🔒 Enabling secure context for payment autofill...')
        
        // Set secure context flags
        Object.defineProperty(window, 'isSecureContext', {
            value: true,
            writable: false,
            configurable: false
        })
        
        // Add secure form attributes
        const forms = document.querySelectorAll('form')
        forms.forEach(form => {
            form.setAttribute('autocomplete', 'on')
            form.setAttribute('data-secure', 'true')
        })
        
        console.log('✅ Secure context enabled for payment autofill')
    }
}

// Lifecycle
onMounted(() => {
    forceSecureContext()
    if (props.show) {
        loadStripe()
    }
})
</script>