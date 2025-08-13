<template>
  <div class="min-h-screen bg-panel">
    <!-- Header -->
    <div class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-6">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Cache Management</h1>
            <p class="mt-1 text-sm text-gray-500">
              Monitor and manage API cache to avoid rate limits
            </p>
          </div>
          <div class="flex space-x-3">
            <button
              @click="refreshStats"
              :disabled="loading"
              class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              <ArrowPathIcon class="h-4 w-4 mr-2" :class="{ 'animate-spin': loading }" />
              Refresh
            </button>
            <button
              @click="showCleanupModal = true"
              class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              <TrashIcon class="h-4 w-4 mr-2" />
              Cleanup
            </button>
            <button
              @click="showWarmModal = true"
              class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
            >
              <FireIcon class="h-4 w-4 mr-2" />
              Warm Cache
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Health Status -->
      <div class="mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div :class="[
                  'h-8 w-8 rounded-full flex items-center justify-center',
                  health.status === 'healthy' ? 'bg-green-100' : 'bg-yellow-100'
                ]">
                  <CheckCircleIcon 
                    v-if="health.status === 'healthy'" 
                    class="h-5 w-5 text-green-600" 
                  />
                  <ExclamationTriangleIcon 
                    v-else 
                    class="h-5 w-5 text-yellow-600" 
                  />
                </div>
              </div>
              <div class="ml-5">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Cache Health: {{ health.status === 'healthy' ? 'Healthy' : 'Needs Attention' }}
                </h3>
                <div class="mt-2 max-w-xl text-sm text-gray-500">
                  <ul v-if="health.issues && health.issues.length > 0" class="list-disc list-inside">
                    <li v-for="issue in health.issues" :key="issue" class="text-yellow-600">
                      {{ issue }}
                    </li>
                  </ul>
                  <p v-else class="text-green-600">All cache metrics are within optimal ranges</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <ServerIcon class="h-6 w-6 text-gray-400" />
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">Total Entries</dt>
                  <dd class="text-lg font-medium text-gray-900">
                    {{ formatNumber(stats.total_entries) }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <ChartBarIcon class="h-6 w-6 text-green-400" />
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">Hit Ratio</dt>
                  <dd class="text-lg font-medium text-gray-900">
                    {{ stats.cache_hit_ratio }}%
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <CurrencyDollarIcon class="h-6 w-6 text-blue-400" />
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">API Calls Saved</dt>
                  <dd class="text-lg font-medium text-gray-900">
                    {{ formatNumber(stats.api_cost_saved) }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <CircleStackIcon class="h-6 w-6 text-purple-400" />
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">Cache Size</dt>
                  <dd class="text-lg font-medium text-gray-900">
                    {{ stats.cache_size_mb }} MB
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- API Sources Performance -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white shadow rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">CoinGecko Cache Performance</h3>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div class="flex justify-between">
                <span class="text-sm text-gray-500">Hit Ratio:</span>
                <span class="text-sm font-medium">{{ coinGeckoStats.cache_hit_ratio }}%</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-500">API Calls Saved:</span>
                <span class="text-sm font-medium">{{ formatNumber(coinGeckoStats.total_api_calls_saved) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-500">Estimated Cost Saved:</span>
                <span class="text-sm font-medium">${{ coinGeckoStats.estimated_cost_saved.toFixed(2) }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white shadow rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Blockchain APIs Performance</h3>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div class="space-y-2">
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500">Etherscan Hits:</span>
                  <span class="text-sm font-medium">{{ formatNumber(blockchainStats.etherscan.total_hits) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500">Moralis Hits:</span>
                  <span class="text-sm font-medium">{{ formatNumber(blockchainStats.moralis.total_hits) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500">Total Saved:</span>
                  <span class="text-sm font-medium">{{ formatNumber(blockchainStats.total_blockchain_calls_saved) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cache Entries Table -->
      <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">Cache Entries</h3>
            <div class="flex space-x-3">
              <select 
                v-model="filters.api_source" 
                @change="loadEntries"
                class="text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Sources</option>
                <option v-for="source in stats.api_sources" :key="source" :value="source">
                  {{ source }}
                </option>
              </select>
              <select 
                v-model="filters.status" 
                @change="loadEntries"
                class="text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="expired">Expired</option>
                <option value="invalidated">Invalidated</option>
              </select>
              <input
                v-model="filters.search"
                @input="debounceSearch"
                placeholder="Search..."
                class="text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-panel">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  API Source
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Resource Type
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Resource ID
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Hits
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Expires
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="entry in entries.data" :key="entry.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  <span :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                    getSourceColor(entry.api_source)
                  ]">
                    {{ entry.api_source }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ entry.resource_type }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate">
                  {{ entry.resource_id || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ entry.hit_count }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                    getStatusColor(getEntryStatus(entry))
                  ]">
                    {{ getEntryStatus(entry) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(entry.expires_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <button
                    @click="invalidateEntry(entry.id)"
                    class="text-red-600 hover:text-red-900"
                  >
                    Invalidate
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <div v-if="entries.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <button
                @click="changePage(entries.current_page - 1)"
                :disabled="entries.current_page <= 1"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-panel disabled:opacity-50"
              >
                Previous
              </button>
              <button
                @click="changePage(entries.current_page + 1)"
                :disabled="entries.current_page >= entries.last_page"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-panel disabled:opacity-50"
              >
                Next
              </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Showing {{ entries.from }} to {{ entries.to }} of {{ entries.total }} results
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                  <button
                    v-for="page in getPageNumbers()"
                    :key="page"
                    @click="changePage(page)"
                    :class="[
                      'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                      page === entries.current_page
                        ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                        : 'bg-white border-gray-300 text-gray-500 hover:bg-panel'
                    ]"
                  >
                    {{ page }}
                  </button>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cleanup Modal -->
    <div v-if="showCleanupModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-lg max-w-md w-full">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-medium text-gray-900">Cache Cleanup</h3>
        </div>
        <div class="p-6">
          <div class="space-y-4">
            <p class="text-sm text-gray-500">
              Choose cleanup mode. Aggressive cleanup removes low-efficiency entries.
            </p>
            <div class="space-y-2">
              <label class="flex items-center">
                <input v-model="cleanupMode" value="normal" type="radio" class="mr-2" />
                <span class="text-sm">Normal (expired entries only)</span>
              </label>
              <label class="flex items-center">
                <input v-model="cleanupMode" value="aggressive" type="radio" class="mr-2" />
                <span class="text-sm">Aggressive (expired + low-efficiency entries)</span>
              </label>
            </div>
          </div>
        </div>
        <div class="px-6 py-4 bg-panel flex justify-end space-x-3">
          <button
            @click="showCleanupModal = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-panel"
          >
            Cancel
          </button>
          <button
            @click="performCleanup"
            :disabled="cleanupLoading"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 disabled:opacity-50"
          >
            {{ cleanupLoading ? 'Cleaning...' : 'Cleanup' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Warm Cache Modal -->
    <div v-if="showWarmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-lg max-w-md w-full">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-medium text-gray-900">Warm Cache</h3>
        </div>
        <div class="p-6">
          <div class="space-y-4">
            <p class="text-sm text-gray-500">
              Pre-load popular data to improve cache hit ratios.
            </p>
            <div class="space-y-2">
              <label class="flex items-center">
                <input v-model="warmType" value="coingecko" type="radio" class="mr-2" />
                <span class="text-sm">CoinGecko Popular Coins</span>
              </label>
              <label class="flex items-center">
                <input v-model="warmType" value="contracts" type="radio" class="mr-2" />
                <span class="text-sm">Popular Smart Contracts</span>
              </label>
              <label class="flex items-center">
                <input v-model="warmType" value="popular" type="radio" class="mr-2" />
                <span class="text-sm">Frequently Accessed Data</span>
              </label>
            </div>
          </div>
        </div>
        <div class="px-6 py-4 bg-panel flex justify-end space-x-3">
          <button
            @click="showWarmModal = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-panel"
          >
            Cancel
          </button>
          <button
            @click="performWarmCache"
            :disabled="warmLoading"
            class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 disabled:opacity-50"
          >
            {{ warmLoading ? 'Warming...' : 'Warm Cache' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue'
import {
  ArrowPathIcon,
  TrashIcon,
  FireIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ServerIcon,
  ChartBarIcon,
  CurrencyDollarIcon,
  CircleStackIcon,
} from '@heroicons/vue/24/outline'

export default {
  name: 'CacheManagement',
  components: {
    ArrowPathIcon,
    TrashIcon,
    FireIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ServerIcon,
    ChartBarIcon,
    CurrencyDollarIcon,
    CircleStackIcon,
  },
  props: {
    stats: Object,
    health: Object,
    coinGeckoStats: Object,
    blockchainStats: Object,
    recentEntries: Array,
  },
  setup(props) {
    const loading = ref(false)
    const entries = ref({ data: props.recentEntries || [], current_page: 1, last_page: 1 })
    const showCleanupModal = ref(false)
    const showWarmModal = ref(false)
    const cleanupMode = ref('normal')
    const warmType = ref('coingecko')
    const cleanupLoading = ref(false)
    const warmLoading = ref(false)
    
    const filters = reactive({
      api_source: '',
      status: '',
      search: '',
    })

    const stats = ref(props.stats || {})
    const health = ref(props.health || {})
    const coinGeckoStats = ref(props.coinGeckoStats || {})
    const blockchainStats = ref(props.blockchainStats || {})

    let searchTimeout = null

    const refreshStats = async () => {
      loading.value = true
      try {
        const response = await fetch('/admin/cache/statistics')
        const data = await response.json()
        stats.value = data.overall
        health.value = data.health
        coinGeckoStats.value = data.coingecko
        blockchainStats.value = data.blockchain
      } catch (error) {
        console.error('Failed to refresh stats:', error)
      }
      loading.value = false
    }

    const loadEntries = async (page = 1) => {
      try {
        const params = new URLSearchParams({
          page: page.toString(),
          per_page: '20',
          ...filters,
        })
        
        const response = await fetch(`/admin/cache/entries?${params}`)
        const data = await response.json()
        entries.value = data
      } catch (error) {
        console.error('Failed to load entries:', error)
      }
    }

    const debounceSearch = () => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        loadEntries(1)
      }, 500)
    }

    const invalidateEntry = async (entryId) => {
      try {
        const response = await fetch(`/admin/cache/entries/${entryId}/invalidate`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
        })
        
        if (response.ok) {
          loadEntries(entries.value.current_page)
          refreshStats()
        }
      } catch (error) {
        console.error('Failed to invalidate entry:', error)
      }
    }

    const performCleanup = async () => {
      cleanupLoading.value = true
      try {
        const response = await fetch('/admin/cache/cleanup', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({
            aggressive: cleanupMode.value === 'aggressive',
          }),
        })
        
        if (response.ok) {
          showCleanupModal.value = false
          loadEntries(entries.value.current_page)
          refreshStats()
        }
      } catch (error) {
        console.error('Failed to cleanup cache:', error)
      }
      cleanupLoading.value = false
    }

    const performWarmCache = async () => {
      warmLoading.value = true
      try {
        const response = await fetch('/admin/cache/warm', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({
            type: warmType.value,
          }),
        })
        
        if (response.ok) {
          showWarmModal.value = false
          loadEntries(entries.value.current_page)
          refreshStats()
        }
      } catch (error) {
        console.error('Failed to warm cache:', error)
      }
      warmLoading.value = false
    }

    const changePage = (page) => {
      if (page >= 1 && page <= entries.value.last_page) {
        loadEntries(page)
      }
    }

    const getPageNumbers = () => {
      const current = entries.value.current_page
      const last = entries.value.last_page
      const delta = 2
      const pages = []
      
      for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
        pages.push(i)
      }
      
      return pages
    }

    const formatNumber = (num) => {
      return new Intl.NumberFormat().format(num || 0)
    }

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleString()
    }

    const getSourceColor = (source) => {
      const colors = {
        coingecko: 'bg-green-100 text-green-800',
        etherscan: 'bg-blue-100 text-blue-800',
        moralis: 'bg-purple-100 text-purple-800',
      }
      return colors[source] || 'bg-ink text-gray-800'
    }

    const getStatusColor = (status) => {
      const colors = {
        active: 'bg-green-100 text-green-800',
        expired: 'bg-red-100 text-red-800',
        invalidated: 'bg-ink text-gray-800',
      }
      return colors[status] || 'bg-ink text-gray-800'
    }

    const getEntryStatus = (entry) => {
      const now = new Date()
      const expires = new Date(entry.expires_at)
      
      if (entry.status === 'invalidated') return 'invalidated'
      if (expires < now) return 'expired'
      return 'active'
    }

    onMounted(() => {
      loadEntries()
    })

    return {
      loading,
      entries,
      showCleanupModal,
      showWarmModal,
      cleanupMode,
      warmType,
      cleanupLoading,
      warmLoading,
      filters,
      stats,
      health,
      coinGeckoStats,
      blockchainStats,
      refreshStats,
      loadEntries,
      debounceSearch,
      invalidateEntry,
      performCleanup,
      performWarmCache,
      changePage,
      getPageNumbers,
      formatNumber,
      formatDate,
      getSourceColor,
      getStatusColor,
      getEntryStatus,
    }
  },
}
</script>
