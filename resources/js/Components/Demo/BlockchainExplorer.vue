<template>
  <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h3 class="text-lg font-semibold text-gray-900">Interactive Blockchain Explorer</h3>
        <p class="text-sm text-gray-600">Search and analyze contracts across multiple networks</p>
      </div>
      <div class="flex items-center space-x-2">
        <select 
          v-model="selectedNetwork" 
          @change="onNetworkChange"
          class="text-sm border border-gray-300 rounded-md px-3 py-1 focus:ring-2 focus:ring-brand-500 focus:border-transparent"
        >
          <option v-for="network in networks" :key="network.id" :value="network.id">
            {{ network.name }}
          </option>
        </select>
      </div>
    </div>

    <!-- Search Interface -->
    <div class="mb-6">
      <div class="flex space-x-3">
        <div class="flex-1">
          <input
            v-model="searchQuery"
            @keyup.enter="performSearch"
            type="text"
            placeholder="Enter contract address, transaction hash, or ENS name..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent"
          />
        </div>
        <button
          @click="performSearch"
          :disabled="searching || !searchQuery.trim()"
          class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {{ searching ? 'Searching...' : 'Analyze' }}
        </button>
      </div>
      
      <!-- Quick Examples -->
      <div class="mt-3">
        <div class="text-xs text-gray-500 mb-2">Quick examples:</div>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="example in quickExamples"
            :key="example.address"
            @click="loadExample(example)"
            class="text-xs bg-ink hover:bg-gray-200 text-gray-700 px-2 py-1 rounded transition-colors"
          >
            {{ example.name }}
          </button>
        </div>
      </div>
    </div>

    <!-- Search Results -->
    <div v-if="searchResults" class="space-y-6">
      <!-- Contract Overview -->
      <div class="border border-gray-200 rounded-lg p-4">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-semibold text-gray-900">{{ searchResults.name || 'Contract' }}</h4>
          <div class="flex items-center space-x-2">
            <span :class="['inline-flex items-center px-2 py-1 rounded-full text-xs font-medium', getVerificationBadge(searchResults.verified)]">
              {{ searchResults.verified ? 'Verified' : 'Unverified' }}
            </span>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              {{ getNetworkName(selectedNetwork) }}
            </span>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
          <div>
            <div class="text-xs text-gray-500">Address</div>
            <div class="text-sm font-mono text-gray-900 break-all">{{ searchResults.address }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Balance</div>
            <div class="text-sm font-medium text-gray-900">{{ searchResults.balance }} ETH</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Transactions</div>
            <div class="text-sm font-medium text-gray-900">{{ searchResults.transactionCount?.toLocaleString() || 'N/A' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Creation Date</div>
            <div class="text-sm font-medium text-gray-900">{{ searchResults.creationDate || 'Unknown' }}</div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="flex flex-wrap gap-2">
          <button
            @click="startSecurityAnalysis"
            :disabled="analyzing"
            class="flex items-center space-x-2 px-3 py-2 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 disabled:opacity-50 transition-colors text-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>{{ analyzing ? 'Analyzing...' : 'Security Audit' }}</span>
          </button>
          
          <button
            @click="startSentimentAnalysis"
            class="flex items-center space-x-2 px-3 py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors text-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
            <span>Sentiment Analysis</span>
          </button>
          
          <button
            @click="viewSourceCode"
            :disabled="!searchResults.verified"
            class="flex items-center space-x-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 disabled:opacity-50 transition-colors text-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
            </svg>
            <span>View Source</span>
          </button>
        </div>
      </div>

      <!-- Analysis Results -->
      <div v-if="analysisResults" class="border border-gray-200 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-4">Analysis Results</h4>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="text-center p-4 bg-red-50 rounded-lg">
            <div class="text-2xl font-bold text-red-600">{{ analysisResults.criticalFindings }}</div>
            <div class="text-sm text-red-700">Critical Issues</div>
          </div>
          <div class="text-center p-4 bg-yellow-50 rounded-lg">
            <div class="text-2xl font-bold text-yellow-600">{{ analysisResults.warningFindings }}</div>
            <div class="text-sm text-yellow-700">Warnings</div>
          </div>
          <div class="text-center p-4 bg-green-50 rounded-lg">
            <div class="text-2xl font-bold text-green-600">{{ analysisResults.securityScore }}%</div>
            <div class="text-sm text-green-700">Security Score</div>
          </div>
        </div>

        <!-- Key Findings -->
        <div class="space-y-3">
          <h5 class="font-medium text-gray-900">Key Findings:</h5>
          <div 
            v-for="finding in analysisResults.keyFindings" 
            :key="finding.id"
            class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg"
          >
            <div :class="['w-3 h-3 rounded-full mt-1.5 flex-shrink-0', getSeverityColor(finding.severity)]"></div>
            <div class="flex-1">
              <div class="text-sm font-medium text-gray-900">{{ finding.title }}</div>
              <div class="text-xs text-gray-600 mt-1">{{ finding.description }}</div>
              <div class="text-xs text-gray-500 mt-1">
                Function: <code class="bg-ink px-1 rounded">{{ finding.function }}</code>
                • Line: {{ finding.line }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-else-if="searching" class="text-center py-12">
      <div class="inline-flex items-center space-x-3">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
        <span class="text-gray-600">Fetching contract data from {{ getNetworkName(selectedNetwork) }}...</span>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900">Search blockchain contracts</h3>
      <p class="mt-1 text-sm text-gray-500">Enter a contract address to begin analysis</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const selectedNetwork = ref('ethereum')
const searchQuery = ref('')
const searching = ref(false)
const analyzing = ref(false)
const searchResults = ref(null)
const analysisResults = ref(null)
const loading = ref(false)
const error = ref(null)

// These will be fetched from API
const networks = ref([])
const quickExamples = ref([])

// Fetch networks from API
const fetchNetworks = async () => {
  try {
    const response = await api.get('/blockchain/networks')
    networks.value = response.data.networks || response.data || []
    
    // Set default network if available
    if (networks.value.length > 0 && !selectedNetwork.value) {
      selectedNetwork.value = networks.value[0].id
    }
  } catch (err) {
    console.error('Error fetching networks:', err)
    networks.value = []
  }
}

// Fetch quick examples from API
const fetchQuickExamples = async () => {
  try {
    const response = await api.get('/blockchain/examples')
    quickExamples.value = response.data.examples || response.data || []
  } catch (err) {
    console.error('Error fetching quick examples:', err)
    // Keep empty array as fallback
    quickExamples.value = []
  }
}

const getNetworkName = (networkId) => {
  return networks.value.find(n => n.id === networkId)?.name || networkId
}

const getVerificationBadge = (verified) => {
  return verified 
    ? 'bg-green-100 text-green-800'
    : 'bg-red-100 text-red-800'
}

const getSeverityColor = (severity) => {
  switch (severity) {
    case 'critical': return 'bg-red-500'
    case 'high': return 'bg-orange-500'
    case 'medium': return 'bg-yellow-500'
    case 'low': return 'bg-blue-500'
    default: return 'bg-panel'
  }
}

const onNetworkChange = () => {
  // Reset search when network changes
  searchResults.value = null
  analysisResults.value = null
}

const loadExample = (example) => {
  searchQuery.value = example.address
  performSearch()
}

const performSearch = async () => {
  if (!searchQuery.value.trim()) return
  
  searching.value = true
  searchResults.value = null
  analysisResults.value = null
  error.value = null
  
  try {
    const response = await api.get('/blockchain/contract-info', {
      params: {
        address: searchQuery.value.trim(),
        network: selectedNetwork.value
      }
    })
    
    const data = response.data
    searchResults.value = {
      address: data.address || searchQuery.value,
      name: data.name || data.contractName || 'Unknown Contract',
      verified: data.verified || false,
      balance: data.balance || '0.0000',
      transactionCount: data.transactionCount || data.txCount || 0,
      creationDate: data.creationDate || data.deployedAt || 'Unknown',
      network: selectedNetwork.value
    }
  } catch (err) {
    error.value = 'Failed to fetch contract information'
    console.error('Error searching contract:', err)
    searchResults.value = null
  } finally {
    searching.value = false
  }
}

const startSecurityAnalysis = async () => {
  if (!searchResults.value?.address) return
  
  analyzing.value = true
  error.value = null
  
  try {
    const response = await api.post('/blockchain/security-analysis', {
      address: searchResults.value.address,
      network: selectedNetwork.value
    })
    
    const data = response.data
    analysisResults.value = {
      criticalFindings: data.criticalFindings || data.critical || 0,
      warningFindings: data.warningFindings || data.warnings || data.high || 0,
      securityScore: data.securityScore || data.score || 0,
      keyFindings: data.keyFindings || data.findings || []
    }
  } catch (err) {
    error.value = 'Failed to perform security analysis'
    console.error('Error analyzing contract:', err)
    analysisResults.value = null
  } finally {
    analyzing.value = false
  }
}

const startSentimentAnalysis = async () => {
  if (!searchResults.value?.address) return
  
  try {
    const response = await api.post('/blockchain/sentiment-analysis', {
      address: searchResults.value.address,
      network: selectedNetwork.value
    })
    
    console.log('Sentiment analysis results:', response.data)
    // Handle sentiment analysis results as needed
  } catch (err) {
    console.error('Error starting sentiment analysis:', err)
  }
}

const viewSourceCode = () => {
  if (!searchResults.value?.address) return
  
  // Navigate to source code viewer or open in new tab
  const url = `/blockchain/source/${searchResults.value.address}?network=${selectedNetwork.value}`
  window.open(url, '_blank')
}

// Initialize data when component mounts
onMounted(async () => {
  loading.value = true
  
  try {
    await Promise.all([
      fetchNetworks(),
      fetchQuickExamples()
    ])
  } catch (err) {
    error.value = 'Failed to initialize blockchain explorer'
    console.error('Error initializing component:', err)
  } finally {
    loading.value = false
  }
})
</script>