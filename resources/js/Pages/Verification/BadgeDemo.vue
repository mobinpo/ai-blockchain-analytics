<template>
  <div class="badge-demo">
    <Head title="Verification Badge Demo" />
    
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800 py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="text-center mb-12">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
            🛡️ Verification Badge Demo
          </h1>
          <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            See how verification badges look and behave in different contexts and configurations.
          </p>
        </div>

        <!-- Contract Info -->
        <div v-if="contractAddress" class="bg-white dark:bg-panel rounded-lg shadow-lg p-6 mb-8">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                Contract Information
              </h2>
              <p class="text-sm text-gray-600 dark:text-gray-400 font-mono">
                {{ contractAddress }}
              </p>
              <div v-if="verificationStatus" class="mt-4 text-sm">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <span class="text-gray-500">Status:</span>
                    <span :class="verificationStatus.is_verified ? 'text-green-600' : 'text-red-600'" class="ml-1 font-medium">
                      {{ verificationStatus.is_verified ? 'Verified' : 'Not Verified' }}
                    </span>
                  </div>
                  <div v-if="verificationStatus.verified_at">
                    <span class="text-gray-500">Verified:</span>
                    <span class="text-gray-900 dark:text-white ml-1">
                      {{ formatDate(verificationStatus.verified_at) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <button
              @click="refreshStatus"
              :disabled="loading"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              {{ loading ? 'Checking...' : 'Check Status' }}
            </button>
          </div>
        </div>

        <!-- Badge Variants -->
        <div v-if="verificationStatus?.is_verified" class="space-y-8">
          
          <!-- Standard Badges -->
          <div class="bg-white dark:bg-panel rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
              Badge Variants
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <!-- Default Badge -->
              <div class="space-y-3">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Default</h4>
                <div class="p-4 bg-panel dark:bg-gray-700 rounded-lg flex items-center justify-center">
                  <VerificationBadge
                    :contract-address="contractAddress"
                    variant="default"
                    :auto-verify="false"
                  />
                </div>
                <code class="text-xs text-gray-500 block">variant="default"</code>
              </div>

              <!-- Compact Badge -->
              <div class="space-y-3">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Compact</h4>
                <div class="p-4 bg-panel dark:bg-gray-700 rounded-lg flex items-center justify-center">
                  <VerificationBadge
                    :contract-address="contractAddress"
                    variant="compact"
                    :auto-verify="false"
                  />
                </div>
                <code class="text-xs text-gray-500 block">variant="compact"</code>
              </div>

              <!-- Icon Only -->
              <div class="space-y-3">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Icon Only</h4>
                <div class="p-4 bg-panel dark:bg-gray-700 rounded-lg flex items-center justify-center">
                  <VerificationBadge
                    :contract-address="contractAddress"
                    variant="icon"
                    :auto-verify="false"
                  />
                </div>
                <code class="text-xs text-gray-500 block">variant="icon"</code>
              </div>

              <!-- Inline Badge -->
              <div class="space-y-3">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Inline</h4>
                <div class="p-4 bg-panel dark:bg-gray-700 rounded-lg">
                  <p class="text-sm text-gray-700 dark:text-gray-300">
                    Smart Contract 
                    <VerificationBadge
                      :contract-address="contractAddress"
                      variant="inline"
                      :auto-verify="false"
                    />
                    is trusted by the community.
                  </p>
                </div>
                <code class="text-xs text-gray-500 block">variant="inline"</code>
              </div>

              <!-- Custom Text -->
              <div class="space-y-3">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Custom Text</h4>
                <div class="p-4 bg-panel dark:bg-gray-700 rounded-lg flex items-center justify-center">
                  <VerificationBadge
                    :contract-address="contractAddress"
                    variant="default"
                    custom-text="Trusted"
                    :auto-verify="false"
                  />
                </div>
                <code class="text-xs text-gray-500 block">custom-text="Trusted"</code>
              </div>

              <!-- No Tooltip -->
              <div class="space-y-3">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">No Tooltip</h4>
                <div class="p-4 bg-panel dark:bg-gray-700 rounded-lg flex items-center justify-center">
                  <VerificationBadge
                    :contract-address="contractAddress"
                    variant="default"
                    :show-tooltip="false"
                    :auto-verify="false"
                  />
                </div>
                <code class="text-xs text-gray-500 block">:show-tooltip="false"</code>
              </div>
            </div>
          </div>

          <!-- Use Cases -->
          <div class="bg-white dark:bg-panel rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
              Use Cases
            </h3>

            <div class="space-y-6">
              <!-- Card Header -->
              <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <h4 class="font-medium text-gray-900 dark:text-white">DeFi Protocol</h4>
                    <p class="text-sm text-gray-500 font-mono">{{ truncateAddress(contractAddress) }}</p>
                  </div>
                  <VerificationBadge
                    :contract-address="contractAddress"
                    variant="compact"
                    :auto-verify="false"
                  />
                </div>
              </div>

              <!-- Table Row -->
              <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <table class="w-full">
                  <thead class="bg-panel dark:bg-gray-700">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Contract
                      </th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                      </th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr>
                      <td class="px-4 py-3">
                        <div class="flex items-center">
                          <div class="font-mono text-sm">{{ truncateAddress(contractAddress) }}</div>
                        </div>
                      </td>
                      <td class="px-4 py-3">
                        <VerificationBadge
                          :contract-address="contractAddress"
                          variant="compact"
                          :auto-verify="false"
                        />
                      </td>
                      <td class="px-4 py-3">
                        <button class="text-blue-600 hover:text-blue-500 text-sm" @click="showBadgeDetails(badge)">View Details</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- List Item -->
              <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="flex items-start space-x-3">
                  <VerificationBadge
                    :contract-address="contractAddress"
                    variant="icon"
                    :auto-verify="false"
                    class="mt-1"
                  />
                  <div class="flex-1">
                    <h5 class="font-medium text-gray-900 dark:text-white">Smart Contract Security Audit</h5>
                    <p class="text-sm text-gray-500 mt-1">
                      This contract has been verified and audited for security vulnerabilities. 
                      The verification badge ensures authenticity and prevents spoofing.
                    </p>
                    <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                      <span>Contract: {{ truncateAddress(contractAddress) }}</span>
                      <span>•</span>
                      <span>Verified: {{ formatDate(verificationStatus.verified_at) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Integration Guide -->
          <div class="bg-white dark:bg-panel rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
              Integration Examples
            </h3>

            <div class="space-y-6">
              <!-- Vue.js -->
              <div>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Vue.js Component</h4>
                <pre class="bg-ink dark:bg-gray-700 p-4 rounded-lg text-sm overflow-x-auto"><code>&lt;template&gt;
  &lt;VerificationBadge 
    contract-address="{{ contractAddress }}"
    variant="default"
    :show-tooltip="true"
  /&gt;
&lt;/template&gt;

&lt;script setup&gt;
import VerificationBadge from './VerificationBadge.vue'
&lt;/script&gt;</code></pre>
              </div>

              <!-- HTML/JavaScript -->
              <div>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">HTML + JavaScript</h4>
                <pre class="bg-ink dark:bg-gray-700 p-4 rounded-lg text-sm overflow-x-auto"><code>&lt;div id="badge-container"&gt;&lt;/div&gt;

&lt;script&gt;
fetch('/api/verification/badge?contract_address={{ contractAddress }}&amp;format=html')
  .then(r =&gt; r.json())
  .then(d =&gt; {
    document.getElementById('badge-container').innerHTML = d.badge_html
  })
&lt;/script&gt;</code></pre>
              </div>

              <!-- API Response -->
              <div>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">API Response Example</h4>
                <pre class="bg-ink dark:bg-gray-700 p-4 rounded-lg text-sm overflow-x-auto"><code>{
  "is_verified": true,
  "contract_address": "{{ contractAddress }}",
  "verified_at": "{{ formatDate(verificationStatus.verified_at) }}",
  "badge_html": "&lt;div class=\"verification-badge verified\"&gt;...&lt;/div&gt;"
}</code></pre>
              </div>
            </div>
          </div>

        </div>

        <!-- Not Verified State -->
        <div v-else-if="verificationStatus && !verificationStatus.is_verified" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center">
          <Icon name="shield-exclamation" class="w-16 h-16 text-yellow-500 mx-auto mb-4" />
          <h3 class="text-lg font-medium text-yellow-800 dark:text-yellow-200 mb-2">
            Contract Not Verified
          </h3>
          <p class="text-yellow-700 dark:text-yellow-300 mb-4">
            This contract address is not verified. Verification badges will not be displayed.
          </p>
          <Link
            href="/verification/get-verified"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-yellow-800 bg-yellow-100 hover:bg-yellow-200"
          >
            Verify Contract
          </Link>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6 text-center">
          <Icon name="x-circle" class="w-16 h-16 text-red-500 mx-auto mb-4" />
          <h3 class="text-lg font-medium text-red-800 dark:text-red-200 mb-2">
            Error Loading Badge
          </h3>
          <p class="text-red-700 dark:text-red-300 mb-4">
            {{ error }}
          </p>
          <button
            @click="refreshStatus"
            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
          >
            Try Again
          </button>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="bg-white dark:bg-panel rounded-lg shadow-lg p-12 text-center">
          <Icon name="spinner" class="w-12 h-12 text-blue-600 animate-spin mx-auto mb-4" />
          <p class="text-gray-600 dark:text-gray-400">Loading verification status...</p>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import VerificationBadge from '@/Components/Verification/VerificationBadge.vue'
import Icon from '@/Components/Icon.vue'

// Props
const props = defineProps({
  contract: {
    type: String,
    default: ''
  }
})

// Reactive data
const loading = ref(false)
const error = ref(null)
const verificationStatus = ref(null)
const contractAddress = ref(props.contract || new URLSearchParams(window.location.search).get('contract') || '0x1234567890123456789012345678901234567890')

// Methods
async function refreshStatus() {
  if (!contractAddress.value) return

  loading.value = true
  error.value = null

  try {
    const response = await fetch(`/api/verification/status?contract_address=${encodeURIComponent(contractAddress.value)}`)
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }

    const data = await response.json()
    verificationStatus.value = data

  } catch (err) {
    console.error('Failed to check verification status:', err)
    error.value = err.message
  } finally {
    loading.value = false
  }
}

function formatDate(dateString) {
  if (!dateString) return 'Unknown'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

function truncateAddress(address) {
  if (!address) return ''
  return `${address.slice(0, 6)}...${address.slice(-4)}`
}

// Lifecycle
onMounted(() => {
  refreshStatus()
})
</script>

<style scoped>
.badge-demo {
  @apply min-h-screen;
}

code {
  @apply font-mono text-sm;
}

pre {
  @apply font-mono text-sm;
}
</style>