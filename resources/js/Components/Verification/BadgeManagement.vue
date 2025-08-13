<template>
  <div class="badge-management">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
          Badge Management
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mt-1">
          Manage your verified contracts and embed badges
        </p>
      </div>
      
      <div class="flex items-center space-x-4">
        <div class="text-sm text-gray-500">
          Total Verified: <span class="font-semibold text-gray-900 dark:text-white">{{ verifiedContracts.length }}</span>
        </div>
        <button
          @click="refreshData"
          :disabled="loading"
          class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
        >
          <Icon :name="loading ? 'spinner' : 'refresh'" :class="loading ? 'animate-spin' : ''" class="w-4 h-4 mr-1" />
          Refresh
        </button>
      </div>
    </div>

    <!-- Search and Filter -->
    <div class="mb-6 flex flex-col sm:flex-row gap-4">
      <div class="flex-1">
        <input
          v-model="searchTerm"
          type="text"
          placeholder="Search by contract address or project name..."
          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        />
      </div>
      <div class="flex space-x-2">
        <select
          v-model="sortBy"
          class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        >
          <option value="verified_at">Sort by Date</option>
          <option value="project_name">Sort by Name</option>
          <option value="contract_address">Sort by Address</option>
        </select>
        <button
          @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'"
          class="px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:text-gray-300"
        >
          <Icon :name="sortOrder === 'asc' ? 'sort-ascending' : 'sort-descending'" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Contract Cards -->
    <div v-if="filteredContracts.length > 0" class="space-y-4">
      <div
        v-for="contract in filteredContracts"
        :key="contract.contract_address"
        class="bg-white dark:bg-panel border border-gray-200 dark:border-gray-700 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow"
      >
        <div class="flex items-start justify-between">
          <!-- Contract Info -->
          <div class="flex-1">
            <div class="flex items-center space-x-3 mb-3">
              <VerificationBadge
                :contract-address="contract.contract_address"
                :auto-verify="false"
                :show-tooltip="false"
                variant="default"
                class="flex-shrink-0"
              />
              <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                  {{ contract.metadata?.project_name || 'Verified Contract' }}
                </h3>
                <p class="text-sm text-gray-500 font-mono">
                  {{ contract.contract_address }}
                </p>
              </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
              <div>
                <span class="text-gray-500">Verified:</span>
                <span class="text-gray-900 dark:text-white ml-1">
                  {{ formatDate(contract.verified_at) }}
                </span>
              </div>
              <div>
                <span class="text-gray-500">Method:</span>
                <span class="text-gray-900 dark:text-white ml-1">
                  {{ contract.verification_method || 'Signed URL' }}
                </span>
              </div>
              <div v-if="contract.metadata?.website">
                <span class="text-gray-500">Website:</span>
                <a 
                  :href="contract.metadata.website" 
                  target="_blank"
                  class="text-blue-600 hover:text-blue-500 ml-1"
                >
                  Visit →
                </a>
              </div>
            </div>
            
            <p v-if="contract.metadata?.description" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
              {{ contract.metadata.description }}
            </p>
          </div>

          <!-- Actions -->
          <div class="flex items-center space-x-2 ml-4">
            <button
              @click="showEmbedCode(contract)"
              class="p-2 text-gray-400 hover:text-blue-600 transition-colors"
              title="Get embed code"
            >
              <Icon name="code" class="w-5 h-5" />
            </button>
            <button
              @click="copyAddress(contract.contract_address)"
              class="p-2 text-gray-400 hover:text-green-600 transition-colors"
              title="Copy address"
            >
              <Icon name="clipboard" class="w-5 h-5" />
            </button>
            <button
              @click="openBadgeDemo(contract)"
              class="p-2 text-gray-400 hover:text-purple-600 transition-colors"
              title="Preview badge"
            >
              <Icon name="eye" class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading" class="text-center py-12">
      <Icon name="shield-check" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
        {{ searchTerm ? 'No matching contracts found' : 'No verified contracts' }}
      </h3>
      <p class="text-gray-500 mb-6">
        {{ searchTerm ? 'Try adjusting your search terms' : 'Get started by verifying your first contract' }}
      </p>
      <Link
        href="/verification/get-verified"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
      >
        <Icon name="plus" class="w-4 h-4 mr-2" />
        Verify Contract
      </Link>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="flex items-center space-x-2 text-gray-500">
        <Icon name="spinner" class="w-6 h-6 animate-spin" />
        <span>Loading contracts...</span>
      </div>
    </div>

    <!-- Embed Code Modal -->
    <div
      v-if="showEmbedModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      @click="closeEmbedModal"
    >
      <div
        class="bg-white dark:bg-panel rounded-lg max-w-4xl w-full max-h-[80vh] overflow-y-auto"
        @click.stop
      >
        <div class="p-6">
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              Embed Badge: {{ selectedContract?.metadata?.project_name || 'Contract' }}
            </h3>
            <button
              @click="closeEmbedModal"
              class="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <Icon name="x" class="w-6 h-6" />
            </button>
          </div>

          <!-- Badge Preview -->
          <div class="mb-6 p-4 bg-panel dark:bg-gray-700 rounded-lg">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Preview:</h4>
            <div class="flex flex-wrap gap-4">
              <VerificationBadge
                v-if="selectedContract"
                :contract-address="selectedContract.contract_address"
                variant="default"
                :auto-verify="false"
              />
              <VerificationBadge
                v-if="selectedContract"
                :contract-address="selectedContract.contract_address"
                variant="compact"
                :auto-verify="false"
              />
              <VerificationBadge
                v-if="selectedContract"
                :contract-address="selectedContract.contract_address"
                variant="icon"
                :auto-verify="false"
              />
            </div>
          </div>

          <!-- Embed Options -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- HTML Embed -->
            <div>
              <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">HTML Embed</h4>
              <div class="space-y-3">
                <div>
                  <label class="text-xs text-gray-500 block mb-1">Default Badge</label>
                  <textarea
                    :value="getEmbedCode('html', 'default')"
                    readonly
                    class="w-full h-20 px-3 py-2 text-xs font-mono bg-ink dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md resize-none"
                  />
                  <button
                    @click="copyToClipboard(getEmbedCode('html', 'default'))"
                    class="mt-1 text-xs text-blue-600 hover:text-blue-500"
                  >
                    Copy HTML
                  </button>
                </div>
              </div>
            </div>

            <!-- Vue Component -->
            <div>
              <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Vue Component</h4>
              <div class="space-y-3">
                <div>
                  <label class="text-xs text-gray-500 block mb-1">Component Usage</label>
                  <textarea
                    :value="getEmbedCode('vue', 'default')"
                    readonly
                    class="w-full h-20 px-3 py-2 text-xs font-mono bg-ink dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md resize-none"
                  />
                  <button
                    @click="copyToClipboard(getEmbedCode('vue', 'default'))"
                    class="mt-1 text-xs text-blue-600 hover:text-blue-500"
                  >
                    Copy Vue
                  </button>
                </div>
              </div>
            </div>

            <!-- React Component -->
            <div>
              <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">React Component</h4>
              <div class="space-y-3">
                <div>
                  <label class="text-xs text-gray-500 block mb-1">JSX Usage</label>
                  <textarea
                    :value="getEmbedCode('react', 'default')"
                    readonly
                    class="w-full h-20 px-3 py-2 text-xs font-mono bg-ink dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md resize-none"
                  />
                  <button
                    @click="copyToClipboard(getEmbedCode('react', 'default'))"
                    class="mt-1 text-xs text-blue-600 hover:text-blue-500"
                  >
                    Copy React
                  </button>
                </div>
              </div>
            </div>

            <!-- API Usage -->
            <div>
              <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">API Usage</h4>
              <div class="space-y-3">
                <div>
                  <label class="text-xs text-gray-500 block mb-1">Badge API Endpoint</label>
                  <textarea
                    :value="getEmbedCode('api', 'default')"
                    readonly
                    class="w-full h-20 px-3 py-2 text-xs font-mono bg-ink dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md resize-none"
                  />
                  <button
                    @click="copyToClipboard(getEmbedCode('api', 'default'))"
                    class="mt-1 text-xs text-blue-600 hover:text-blue-500"
                  >
                    Copy API
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Security Notice -->
          <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <div class="flex items-start space-x-3">
              <Icon name="shield-check" class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
              <div>
                <h5 class="text-sm font-medium text-blue-900 dark:text-blue-200">Security Features</h5>
                <p class="text-sm text-blue-800 dark:text-blue-300 mt-1">
                  All badges are cryptographically verified using SHA-256 + HMAC signatures. 
                  Badges automatically validate against our secure API to prevent spoofing.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Notifications -->
    <div
      v-if="toast.show"
      :class="[
        'fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 transition-all duration-300',
        toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'
      ]"
    >
      <div class="flex items-center space-x-2">
        <Icon :name="toast.type === 'success' ? 'check-circle' : 'x-circle'" class="w-5 h-5" />
        <span>{{ toast.message }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import VerificationBadge from './VerificationBadge.vue'
import Icon from '../Icon.vue'

// Props
const props = defineProps({
  initialContracts: {
    type: Array,
    default: () => []
  }
})

// Reactive data
const loading = ref(false)
const verifiedContracts = ref(props.initialContracts)
const searchTerm = ref('')
const sortBy = ref('verified_at')
const sortOrder = ref('desc')
const showEmbedModal = ref(false)
const selectedContract = ref(null)
const toast = ref({
  show: false,
  type: 'success',
  message: ''
})

// Computed
const filteredContracts = computed(() => {
  let filtered = verifiedContracts.value.filter(contract => {
    const searchLower = searchTerm.value.toLowerCase()
    return (
      contract.contract_address.toLowerCase().includes(searchLower) ||
      (contract.metadata?.project_name || '').toLowerCase().includes(searchLower) ||
      (contract.metadata?.description || '').toLowerCase().includes(searchLower)
    )
  })

  // Sort
  filtered.sort((a, b) => {
    let aVal = a[sortBy.value] || ''
    let bVal = b[sortBy.value] || ''
    
    if (sortBy.value === 'project_name') {
      aVal = a.metadata?.project_name || ''
      bVal = b.metadata?.project_name || ''
    }

    if (typeof aVal === 'string') {
      aVal = aVal.toLowerCase()
      bVal = bVal.toLowerCase()
    }

    if (sortOrder.value === 'asc') {
      return aVal > bVal ? 1 : -1
    } else {
      return aVal < bVal ? 1 : -1
    }
  })

  return filtered
})

// Methods
async function refreshData() {
  loading.value = true
  try {
    const response = await fetch('/api/verification/verified', {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken()
      },
      credentials: 'include'
    })

    if (response.ok) {
      const data = await response.json()
      verifiedContracts.value = data.verified_contracts || []
    } else {
      showToast('Failed to refresh contracts', 'error')
    }
  } catch (error) {
    console.error('Refresh failed:', error)
    showToast('Failed to refresh contracts', 'error')
  } finally {
    loading.value = false
  }
}

function showEmbedCode(contract) {
  selectedContract.value = contract
  showEmbedModal.value = true
}

function closeEmbedModal() {
  showEmbedModal.value = false
  selectedContract.value = null
}

function openBadgeDemo(contract) {
  // Open a new window with the badge demo
  const url = `/verification/badge-demo?contract=${contract.contract_address}`
  window.open(url, '_blank', 'width=800,height=600')
}

async function copyAddress(address) {
  try {
    await navigator.clipboard.writeText(address)
    showToast('Address copied to clipboard', 'success')
  } catch (error) {
    console.error('Copy failed:', error)
    showToast('Failed to copy address', 'error')
  }
}

async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text)
    showToast('Code copied to clipboard', 'success')
  } catch (error) {
    console.error('Copy failed:', error)
    showToast('Failed to copy code', 'error')
  }
}

function getEmbedCode(type, variant) {
  if (!selectedContract.value) return ''

  const address = selectedContract.value.contract_address
  const baseUrl = window.location.origin

  switch (type) {
    case 'html':
      return generateHtmlEmbed(address, baseUrl)

    case 'vue':
      return generateVueEmbed(address, variant)

    case 'react':
      return generateReactEmbed(address, variant)

    case 'api':
      return generateApiEmbed(address, baseUrl)

    default:
      return ''
  }
}

function generateHtmlEmbed(address, baseUrl) {
  const lines = [
    '<!-- Verification Badge -->',
    '<div id="verification-badge-' + address + '"></div>',
    '<' + 'script>',
    '  fetch("' + baseUrl + '/api/verification/badge?contract_address=' + address + '&format=html")',
    '    .then(r => r.json())',
    '    .then(d => {',
    '      document.getElementById("verification-badge-' + address + '").innerHTML = d.badge_html || ""',
    '    })',
    '</' + 'script>'
  ]
  return lines.join('\n')
}

function generateVueEmbed(address, variant) {
  const lines = [
    '<' + 'template>',
    '  <VerificationBadge',
    '    contract-address="' + address + '"',
    '    variant="' + variant + '"',
    '  />',
    '</' + 'template>',
    '',
    '<' + 'script setup>',
    'import VerificationBadge from "@/Components/Verification/VerificationBadge.vue"',
    '</' + 'script>'
  ]
  return lines.join('\n')
}

function generateReactEmbed(address, variant) {
  const lines = [
    'import VerificationBadge from "./VerificationBadge"',
    '',
    'function MyComponent() {',
    '  return (',
    '    <VerificationBadge',
    '      contractAddress="' + address + '"',
    '      variant="' + variant + '"',
    '    />',
    '  )',
    '}'
  ]
  return lines.join('\n')
}

function generateApiEmbed(address, baseUrl) {
  const lines = [
    '// Badge API Endpoints',
    'GET ' + baseUrl + '/api/verification/status?contract_address=' + address,
    'GET ' + baseUrl + '/api/verification/badge?contract_address=' + address + '&format=html',
    'GET ' + baseUrl + '/api/verification/badge?contract_address=' + address + '&format=json',
    '',
    '// Example Response',
    '{',
    '  "is_verified": true,',
    '  "contract_address": "' + address + '",',
    '  "verified_at": "2024-01-15",',
    '  "badge_html": "..."',
    '}'
  ]
  return lines.join('\n')
}

function showToast(message, type = 'success') {
  toast.value = {
    show: true,
    type,
    message
  }

  setTimeout(() => {
    toast.value.show = false
  }, 3000)
}

function formatDate(dateString) {
  if (!dateString) return 'Unknown'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

// Lifecycle
onMounted(() => {
  // Data already loaded from props
})
</script>

<style scoped>
.badge-management {
  @apply max-w-6xl mx-auto p-6;
}

/* Custom scrollbar for modal */
.overflow-y-auto::-webkit-scrollbar {
  @apply w-2;
}

.overflow-y-auto::-webkit-scrollbar-track {
  @apply bg-ink;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  @apply bg-gray-400 rounded-full;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  @apply bg-panel;
}

/* Toast animation */
.transition-all {
  transition: all 0.3s ease-in-out;
}
</style>