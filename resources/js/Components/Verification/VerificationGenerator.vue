<template>
  <div class="verification-generator">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <!-- Header -->
      <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">
          Generate Verification Badge
        </h3>
        <p class="text-sm text-gray-600">
          Create a cryptographically signed verification URL for your smart contract.
        </p>
      </div>

      <!-- Form -->
      <form @submit.prevent="generateVerificationUrl" class="space-y-4">
        <!-- Contract Address -->
        <div>
          <label for="contractAddress" class="block text-sm font-medium text-gray-700 mb-1">
            Contract Address *
          </label>
          <input
            id="contractAddress"
            v-model="form.contract_address"
            type="text"
            placeholder="0x..."
            pattern="^0x[a-fA-F0-9]{40}$"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            :class="{ 'border-red-300': errors.contract_address }"
          />
          <p v-if="errors.contract_address" class="mt-1 text-xs text-red-600">
            {{ errors.contract_address }}
          </p>
        </div>

        <!-- User ID -->
        <div>
          <label for="userId" class="block text-sm font-medium text-gray-700 mb-1">
            User ID *
          </label>
          <input
            id="userId"
            v-model="form.user_id"
            type="text"
            placeholder="your-user-id"
            required
            maxlength="100"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            :class="{ 'border-red-300': errors.user_id }"
          />
          <p v-if="errors.user_id" class="mt-1 text-xs text-red-600">
            {{ errors.user_id }}
          </p>
        </div>

        <!-- Metadata (Optional) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Project Name -->
          <div>
            <label for="projectName" class="block text-sm font-medium text-gray-700 mb-1">
              Project Name
            </label>
            <input
              id="projectName"
              v-model="form.metadata.project_name"
              type="text"
              placeholder="My DeFi Project"
              maxlength="100"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Website -->
          <div>
            <label for="website" class="block text-sm font-medium text-gray-700 mb-1">
              Website
            </label>
            <input
              id="website"
              v-model="form.metadata.website"
              type="url"
              placeholder="https://example.com"
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Description -->
        <div>
          <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
            Description
          </label>
          <textarea
            id="description"
            v-model="form.metadata.description"
            rows="3"
            placeholder="Brief description of your project..."
            maxlength="500"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <p class="mt-1 text-xs text-gray-500">
            {{ form.metadata.description?.length || 0 }}/500 characters
          </p>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-between pt-4">
          <div class="text-xs text-gray-500">
            * Required fields
          </div>
          <button
            type="submit"
            :disabled="loading"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg
              v-if="loading"
              class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              />
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            {{ loading ? 'Generating...' : 'Generate Verification URL' }}
          </button>
        </div>
      </form>

      <!-- Error Message -->
      <div v-if="error" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">
              Error generating verification URL
            </h3>
            <div class="mt-2 text-sm text-red-700">
              {{ error }}
            </div>
          </div>
        </div>
      </div>

      <!-- Success Result -->
      <div v-if="result" class="mt-6 space-y-4">
        <div class="p-4 bg-green-50 border border-green-200 rounded-md">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                <path
                  fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-green-800">
                Verification URL Generated Successfully
              </h3>
              <div class="mt-2 text-sm text-green-700">
                Your cryptographically signed verification URL is ready.
              </div>
            </div>
          </div>
        </div>

        <!-- URL Display -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Verification URL
          </label>
          <div class="flex">
            <input
              :value="result.verification_url"
              readonly
              class="flex-1 px-3 py-2 bg-panel border border-gray-300 rounded-l-md text-sm font-mono"
            />
            <button
              @click="copyToClipboard(result.verification_url)"
              class="px-3 py-2 bg-blue-600 border border-blue-600 text-white text-sm font-medium rounded-r-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Copy
            </button>
          </div>
        </div>

        <!-- URL Details -->
        <div class="bg-panel rounded-md p-4">
          <h4 class="text-sm font-medium text-gray-900 mb-2">URL Details</h4>
          <dl class="grid grid-cols-1 gap-2 text-xs">
            <div class="flex justify-between">
              <dt class="text-gray-500">Expires:</dt>
              <dd class="text-gray-900 font-mono">{{ formatDate(result.expires_at) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Valid for:</dt>
              <dd class="text-gray-900">{{ Math.floor(result.expires_in / 60) }} minutes</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Token ID:</dt>
              <dd class="text-gray-900 font-mono">{{ result.token.substring(0, 16) }}...</dd>
            </div>
          </dl>
        </div>

        <!-- Instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
          <h4 class="text-sm font-medium text-blue-900 mb-2">Next Steps</h4>
          <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
            <li>Share the verification URL with the contract owner</li>
            <li>They should click the URL to complete verification</li>
            <li>Once verified, the badge will appear on your contract</li>
            <li>The URL expires in {{ Math.floor(result.expires_in / 60) }} minutes</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'

interface VerificationResult {
  success: boolean
  verification_url: string
  token: string
  expires_at: string
  expires_in: number
}

interface FormData {
  contract_address: string
  user_id: string
  metadata: {
    project_name?: string
    description?: string
    website?: string
    social_links?: string[]
  }
}

const emit = defineEmits<{
  success: [result: VerificationResult]
  error: [error: string]
}>()

// Reactive data
const loading = ref(false)
const error = ref<string | null>(null)
const result = ref<VerificationResult | null>(null)
const errors = ref<Record<string, string>>({})

const form = reactive<FormData>({
  contract_address: '',
  user_id: '',
  metadata: {}
})

// Methods
async function generateVerificationUrl() {
  loading.value = true
  error.value = null
  errors.value = {}
  result.value = null

  try {
    // Client-side validation
    if (!isValidEthereumAddress(form.contract_address)) {
      errors.value.contract_address = 'Please enter a valid Ethereum address'
      return
    }

    if (!form.user_id.trim()) {
      errors.value.user_id = 'User ID is required'
      return
    }

    const response = await fetch('/api/verification/generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken()
      },
      body: JSON.stringify({
        contract_address: form.contract_address.toLowerCase(),
        user_id: form.user_id,
        metadata: form.metadata
      })
    })

    const data = await response.json()

    if (!response.ok) {
      if (data.details) {
        errors.value = data.details
        error.value = 'Please fix the validation errors below'
      } else {
        error.value = data.error || data.message || 'Failed to generate verification URL'
      }
      return
    }

    result.value = data
    emit('success', data)

  } catch (err) {
    const errorMessage = err instanceof Error ? err.message : 'Network error occurred'
    error.value = errorMessage
    emit('error', errorMessage)
  } finally {
    loading.value = false
  }
}

function isValidEthereumAddress(address: string): boolean {
  return /^0x[a-fA-F0-9]{40}$/.test(address)
}

function getCsrfToken(): string {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  return token || ''
}

async function copyToClipboard(text: string) {
  try {
    await navigator.clipboard.writeText(text)
    // Could show a toast notification here
  } catch (err) {
    console.error('Failed to copy:', err)
  }
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleString()
}

function resetForm() {
  form.contract_address = ''
  form.user_id = ''
  form.metadata = {}
  error.value = null
  errors.value = {}
  result.value = null
}

// Expose methods
defineExpose({
  resetForm,
  generateVerificationUrl
})
</script>

<style scoped>
/* Additional component-specific styles can go here */
.verification-generator {
  @apply max-w-2xl mx-auto;
}
</style>