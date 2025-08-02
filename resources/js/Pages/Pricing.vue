<template>
  <Head title="Pricing" />
  
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <Link href="/" class="flex items-center">
              <ApplicationLogo class="block h-9 w-auto" />
              <span class="ml-2 text-xl font-semibold text-gray-900">AI Blockchain Analytics</span>
            </Link>
          </div>
          
          <div class="flex items-center space-x-4">
            <div v-if="canLogin">
              <Link
                v-if="$page.props.auth.user"
                :href="route('dashboard')"
                class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium"
              >
                Dashboard
              </Link>
              <template v-else>
                <Link
                  :href="route('login')"
                  class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium"
                >
                  Log in
                </Link>
                <Link
                  v-if="canRegister"
                  :href="route('register')"
                  class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                >
                  Sign up
                </Link>
              </template>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <div class="py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl font-extrabold text-gray-900 sm:text-5xl lg:text-6xl">
          Simple, transparent pricing
        </h1>
        <p class="mt-6 text-xl text-gray-600 max-w-3xl mx-auto">
          Choose the perfect plan for your blockchain analytics needs. Start your free trial today.
        </p>
      </div>
    </div>

    <!-- Billing Toggle -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-center mb-12">
        <div class="bg-gray-100 p-1 rounded-lg">
          <button
            @click="billingInterval = 'monthly'"
            :class="[
              'px-6 py-2 text-sm font-medium rounded-md transition-colors',
              billingInterval === 'monthly'
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-gray-600 hover:text-gray-900'
            ]"
          >
            Monthly
          </button>
          <button
            @click="billingInterval = 'yearly'"
            :class="[
              'px-6 py-2 text-sm font-medium rounded-md transition-colors relative',
              billingInterval === 'yearly'
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-gray-600 hover:text-gray-900'
            ]"
          >
            Yearly
            <span class="absolute -top-2 -right-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full">
              Save 20%
            </span>
          </button>
        </div>
      </div>

      <!-- Pricing Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
        <div
          v-for="plan in currentPlans"
          :key="plan.id"
          :class="[
            'bg-white rounded-2xl shadow-sm border-2 p-8 relative',
            plan.plan_tier === 'professional'
              ? 'border-indigo-200 ring-1 ring-indigo-200'
              : 'border-gray-200'
          ]"
        >
          <!-- Popular Badge -->
          <div
            v-if="plan.plan_tier === 'professional'"
            class="absolute -top-4 left-1/2 transform -translate-x-1/2"
          >
            <span class="bg-indigo-600 text-white px-4 py-1 rounded-full text-sm font-medium">
              Most Popular
            </span>
          </div>

          <div class="text-center">
            <h3 class="text-2xl font-bold text-gray-900">
              {{ plan.name.replace(' Annual', '') }}
            </h3>
            <div class="mt-4">
              <span class="text-5xl font-extrabold text-gray-900">
                ${{ Math.floor(plan.price_in_dollars) }}
              </span>
              <span class="text-xl text-gray-600">
                /{{ plan.is_annual ? 'year' : 'month' }}
              </span>
            </div>
            <div v-if="plan.is_annual && plan.savings_percentage" class="mt-2">
              <span class="text-sm text-green-600 font-medium">
                Save {{ plan.savings_percentage }}% annually
              </span>
            </div>
            <div v-if="plan.trial_period_days > 0" class="mt-2">
              <span class="text-sm text-indigo-600 font-medium">
                {{ plan.trial_period_days }}-day free trial
              </span>
            </div>
          </div>

          <!-- Features -->
          <ul class="mt-8 space-y-4">
            <li
              v-for="feature in plan.features"
              :key="feature"
              class="flex items-start"
            >
              <svg
                class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
              <span class="ml-3 text-gray-700">{{ feature }}</span>
            </li>
          </ul>

          <!-- Limits -->
          <div class="mt-6 space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Analysis limit:</span>
              <span class="font-medium text-gray-900">
                {{ plan.analysis_limit === -1 ? 'Unlimited' : plan.analysis_limit.toLocaleString() }}
              </span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Projects:</span>
              <span class="font-medium text-gray-900">
                {{ plan.project_limit === -1 ? 'Unlimited' : plan.project_limit }}
              </span>
            </div>
          </div>

          <!-- CTA Button -->
          <div class="mt-8">
            <button
              @click="selectPlan(plan)"
              :class="[
                'w-full py-3 px-4 rounded-lg font-medium text-sm transition-colors',
                plan.plan_tier === 'professional'
                  ? 'bg-indigo-600 hover:bg-indigo-700 text-white'
                  : 'bg-gray-900 hover:bg-gray-800 text-white'
              ]"
            >
              {{ $page.props.auth.user ? 'Choose Plan' : 'Get Started' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Features Comparison -->
    <div class="bg-white py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-extrabold text-gray-900">
            Compare all features
          </h2>
          <p class="mt-4 text-lg text-gray-600">
            Everything you need to analyze blockchain data with confidence.
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr>
                <th class="text-left py-4 pr-4">Features</th>
                <th class="text-center py-4 px-4">Starter</th>
                <th class="text-center py-4 px-4">Professional</th>
                <th class="text-center py-4 px-4">Enterprise</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="feature in comparisonFeatures" :key="feature.name">
                <td class="py-4 pr-4 font-medium text-gray-900">
                  {{ feature.name }}
                </td>
                <td class="text-center py-4 px-4">
                  <component
                    :is="getFeatureComponent(feature.starter)"
                    :value="feature.starter"
                  />
                </td>
                <td class="text-center py-4 px-4">
                  <component
                    :is="getFeatureComponent(feature.professional)"
                    :value="feature.professional"
                  />
                </td>
                <td class="text-center py-4 px-4">
                  <component
                    :is="getFeatureComponent(feature.enterprise)"
                    :value="feature.enterprise"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- FAQ Section -->
    <div class="bg-gray-50 py-16">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-extrabold text-gray-900">
            Frequently asked questions
          </h2>
        </div>
        
        <div class="space-y-8">
          <div v-for="faq in faqs" :key="faq.question">
            <h3 class="text-lg font-medium text-gray-900 mb-2">
              {{ faq.question }}
            </h3>
            <p class="text-gray-600">
              {{ faq.answer }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'

const props = defineProps({
  monthlyPlans: Array,
  yearlyPlans: Array,
  canLogin: Boolean,
  canRegister: Boolean,
})

const billingInterval = ref('monthly')

const currentPlans = computed(() => {
  return billingInterval.value === 'yearly' ? props.yearlyPlans : props.monthlyPlans
})

const comparisonFeatures = [
  {
    name: 'Blockchain Analysis',
    starter: 10,
    professional: 100,
    enterprise: 'Unlimited'
  },
  {
    name: 'Smart Contract Scanning',
    starter: true,
    professional: true,
    enterprise: true
  },
  {
    name: 'AI-Powered Insights',
    starter: false,
    professional: true,
    enterprise: true
  },
  {
    name: 'Real-time Monitoring',
    starter: false,
    professional: true,
    enterprise: true
  },
  {
    name: 'Custom Reports',
    starter: false,
    professional: true,
    enterprise: true
  },
  {
    name: 'Priority Support',
    starter: false,
    professional: false,
    enterprise: true
  },
  {
    name: 'White-label Reports',
    starter: false,
    professional: false,
    enterprise: true
  },
  {
    name: 'Dedicated Account Manager',
    starter: false,
    professional: false,
    enterprise: true
  }
]

const faqs = [
  {
    question: 'Can I change my plan at any time?',
    answer: 'Yes, you can upgrade or downgrade your plan at any time. Changes will be prorated and reflected in your next billing cycle.'
  },
  {
    question: 'Do you offer a free trial?',
    answer: 'Yes, all plans come with a free trial period. Starter and Professional plans include a 14-day trial, while Enterprise includes a 30-day trial.'
  },
  {
    question: 'What payment methods do you accept?',
    answer: 'We accept all major credit cards (Visa, MasterCard, American Express) and support secure payments through Stripe.'
  },
  {
    question: 'Can I cancel my subscription anytime?',
    answer: 'Yes, you can cancel your subscription at any time. Your access will continue until the end of your current billing period.'
  },
  {
    question: 'Do you offer discounts for annual billing?',
    answer: 'Yes, annual billing offers a 20% discount compared to monthly billing across all plans.'
  },
  {
    question: 'Is there a setup fee?',
    answer: 'No, there are no setup fees or hidden charges. You only pay the plan price listed above.'
  }
]

const getFeatureComponent = (value) => {
  if (typeof value === 'boolean') {
    return 'FeatureBool'
  } else if (typeof value === 'number') {
    return 'FeatureNumber'
  } else {
    return 'FeatureText'
  }
}

const selectPlan = (plan) => {
  if (!props.canLogin || !window.Laravel?.user) {
    // Redirect to registration with plan pre-selected
    window.location.href = route('register', { plan: plan.slug })
  } else {
    // Redirect to subscription page
    window.location.href = route('subscription.index', { plan: plan.slug })
  }
}
</script>

<script>
// Feature display components
const FeatureBool = {
  props: ['value'],
  template: `
    <svg v-if="value" class="h-5 w-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
    </svg>
    <svg v-else class="h-5 w-5 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
    </svg>
  `
}

const FeatureNumber = {
  props: ['value'],
  template: `<span class="text-gray-900 font-medium">{{ value.toLocaleString() }}</span>`
}

const FeatureText = {
  props: ['value'],
  template: `<span class="text-gray-900 font-medium">{{ value }}</span>`
}

export default {
  components: {
    FeatureBool,
    FeatureNumber,
    FeatureText
  }
}
</script>