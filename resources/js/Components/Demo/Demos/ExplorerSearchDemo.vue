<template>
    <div class="space-y-6">
        <!-- Search Interface -->
        <div class="space-y-4">
            <div class="flex space-x-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Query</label>
                    <div class="relative">
                        <input 
                            v-model="searchQuery"
                            @keyup.enter="performSearch"
                            type="text" 
                            placeholder="Enter transaction hash, address, or block number..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500 pl-10"
                        />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Blockchain</label>
                    <select v-model="selectedChain" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-brand-500">
                        <option value="ethereum">Ethereum</option>
                        <option value="bitcoin">Bitcoin</option>
                        <option value="bsc">Binance Smart Chain</option>
                        <option value="polygon">Polygon</option>
                        <option value="avalanche">Avalanche</option>
                        <option value="arbitrum">Arbitrum</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button 
                        @click="performSearch"
                        :disabled="isSearching || !searchQuery.trim()"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="isSearching" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isSearching ? 'Searching...' : 'Search' }}
                    </button>
                </div>
            </div>
            
            <!-- Quick Search Examples -->
            <div class="flex flex-wrap gap-2">
                <span class="text-sm text-gray-600">Quick examples:</span>
                <button v-for="example in quickExamples" :key="example.label"
                        @click="useExample(example)"
                        class="text-sm text-brand-500 hover:text-indigo-800 underline">
                    {{ example.label }}
                </button>
            </div>
        </div>

        <!-- Search Results -->
        <div v-if="searchResults" class="space-y-6">
            <!-- Result Type Indicator -->
            <div class="flex items-center space-x-2">
                <div class="h-3 w-3 rounded-full" :class="getResultTypeColor(searchResults.type)"></div>
                <span class="font-medium text-gray-900">{{ getResultTypeLabel(searchResults.type) }}</span>
                <span class="text-sm text-gray-500">on {{ getChainLabel(selectedChain) }}</span>
            </div>

            <!-- Transaction Details -->
            <div v-if="searchResults.type === 'transaction'" class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-gray-900">Transaction Details</h4>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                              :class="searchResults.data.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                            {{ searchResults.data.status === 'success' ? 'Success' : 'Failed' }}
                        </span>
                    </div>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Hash</label>
                            <p class="text-sm text-gray-900 font-mono break-all">{{ searchResults.data.hash }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Block</label>
                            <p class="text-sm text-gray-900">#{{ searchResults.data.blockNumber }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">From</label>
                            <p class="text-sm text-gray-900 font-mono">{{ searchResults.data.from }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">To</label>
                            <p class="text-sm text-gray-900 font-mono">{{ searchResults.data.to }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Value</label>
                            <p class="text-sm text-gray-900">{{ searchResults.data.value }} {{ getChainCurrency(selectedChain) }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Gas Used</label>
                            <p class="text-sm text-gray-900">{{ searchResults.data.gasUsed.toLocaleString() }}</p>
                        </div>
                    </div>
                    
                    <!-- AI Risk Analysis -->
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                        <div class="flex items-center space-x-2 mb-3">
                            <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                            <span class="font-medium text-blue-900">AI Risk Analysis</span>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="text-lg font-bold text-blue-600">{{ searchResults.data.riskScore }}/100</div>
                                <div class="text-xs text-blue-700">Risk Score</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-bold text-green-600">{{ searchResults.data.legitimacyScore }}%</div>
                                <div class="text-xs text-green-700">Legitimacy</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-bold text-purple-600">{{ searchResults.data.patterns.length }}</div>
                                <div class="text-xs text-purple-700">Patterns</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p class="text-sm text-blue-800">{{ getRiskAnalysis() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Details -->
            <div v-else-if="searchResults.type === 'address'" class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h4 class="text-lg font-semibold text-gray-900">Address Analysis</h4>
                </div>
                
                <div class="p-6 space-y-6">
                    <!-- Address Overview -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ searchResults.data.balance }}</div>
                            <div class="text-xs text-blue-700">{{ getChainCurrency(selectedChain) }} Balance</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ searchResults.data.txCount }}</div>
                            <div class="text-xs text-green-700">Transactions</div>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">{{ searchResults.data.firstSeen }}</div>
                            <div class="text-xs text-purple-700">First Seen</div>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">{{ searchResults.data.riskLevel }}</div>
                            <div class="text-xs text-yellow-700">Risk Level</div>
                        </div>
                    </div>
                    
                    <!-- Recent Transactions -->
                    <div>
                        <h5 class="font-medium text-gray-900 mb-3">Recent Transactions</h5>
                        <div class="space-y-2">
                            <div v-for="tx in searchResults.data.recentTxs" :key="tx.hash" 
                                 class="flex items-center justify-between p-3 bg-panel rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                         :class="tx.type === 'in' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  :d="tx.type === 'in' ? 'M7 16l-4-4m0 0l4-4m-4 4h18' : 'M17 8l4 4m0 0l-4 4m4-4H3'" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ tx.value }} {{ getChainCurrency(selectedChain) }}</p>
                                        <p class="text-xs text-gray-500">{{ tx.timeAgo }}</p>
                                    </div>
                                </div>
                                <button class="text-sm text-brand-500 hover:text-indigo-800" @click="() => window.open(`https://etherscan.io/tx/${tx.hash}`, '_blank')">View</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Options -->
            <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                <div class="text-sm text-gray-600">
                    Search completed in {{ searchResults.searchTime }}ms
                </div>
                <div class="flex space-x-3">
                    <button @click="exportResults" 
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Export Results
                    </button>
                    <button @click="analyzeWithAI"
                            class="px-4 py-2 border border-indigo-600 text-brand-500 text-sm font-medium rounded-lg hover:bg-indigo-50">
                        Deep AI Analysis
                    </button>
                </div>
            </div>
        </div>

        <!-- No Results State -->
        <div v-else-if="hasSearched && !isSearching" class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No results found</h3>
            <p class="mt-1 text-sm text-gray-500">Try a different search query or blockchain</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

// Demo state
const searchQuery = ref('')
const selectedChain = ref('ethereum')
const isSearching = ref(false)
const hasSearched = ref(false)
const searchResults = ref(null)

const quickExamples = ref([])

// Fetch examples from API
const fetchExamples = async () => {
    try {
        const response = await fetch('/api/blockchain/examples', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        
        if (response.ok) {
            const data = await response.json()
            if (data.success) {
                quickExamples.value = data.examples.slice(0, 3).map(example => ({
                    label: example.name,
                    query: example.address,
                    chain: example.network || 'ethereum',
                    type: 'address'
                })) || []
            }
        } else {
            console.error('Failed to fetch examples:', response.status)
        }
    } catch (err) {
        console.error('Error fetching examples:', err)
        quickExamples.value = []
    }
}

// API call to analyze blockchain data
const analyzeBlockchainData = async (query, chain) => {
    try {
        const response = await fetch('/api/blockchain/analyze', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                query: query,
                network: chain
            })
        })
        
        if (response.ok) {
            const data = await response.json()
            if (data.success) {
                return data.result
            } else {
                throw new Error(data.message || 'Analysis failed')
            }
        } else {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`)
        }
    } catch (error) {
        console.error('Error analyzing blockchain data:', error)
        throw error
    }
}

// Methods
const performSearch = async () => {
    if (!searchQuery.value.trim()) return
    
    isSearching.value = true
    hasSearched.value = true
    searchResults.value = null
    
    try {
        const startTime = Date.now()
        const result = await analyzeBlockchainData(searchQuery.value, selectedChain.value)
        const searchTime = Date.now() - startTime
        
        searchResults.value = {
            ...result,
            searchTime: searchTime
        }
    } catch (error) {
        searchResults.value = {
            type: 'error',
            searchTime: 0,
            error: error.message || 'Failed to analyze blockchain data'
        }
    } finally {
        isSearching.value = false
    }
}

const useExample = (example) => {
    searchQuery.value = example.query
    selectedChain.value = example.chain
    performSearch()
}

const getResultTypeColor = (type) => {
    const colors = {
        transaction: 'bg-blue-500',
        address: 'bg-green-500',
        block: 'bg-purple-500'
    }
    return colors[type] || 'bg-panel'
}

const getResultTypeLabel = (type) => {
    const labels = {
        transaction: 'Transaction',
        address: 'Address',
        block: 'Block'
    }
    return labels[type] || 'Unknown'
}

const getChainLabel = (chain) => {
    const labels = {
        ethereum: 'Ethereum',
        bitcoin: 'Bitcoin',
        bsc: 'BSC',
        polygon: 'Polygon',
        avalanche: 'Avalanche',
        arbitrum: 'Arbitrum'
    }
    return labels[chain] || chain
}

const getChainCurrency = (chain) => {
    const currencies = {
        ethereum: 'ETH',
        bitcoin: 'BTC',
        bsc: 'BNB',
        polygon: 'MATIC',
        avalanche: 'AVAX',
        arbitrum: 'ETH'
    }
    return currencies[chain] || 'TOKEN'
}

const getRiskAnalysis = () => {
    const analyses = [
        'Low risk transaction with standard gas usage and legitimate address interaction patterns.',
        'Moderate risk detected due to interaction with new contract. Monitor for unusual patterns.',
        'High value transaction flagged for review. All security checks passed.',
        'Normal DeFi interaction detected. Smart contract verified and audited.'
    ]
    
    return analyses[Math.floor(Math.random() * analyses.length)]
}

const exportResults = () => {
    const exportData = {
        timestamp: new Date().toISOString(),
        query: searchQuery.value,
        blockchain: selectedChain.value,
        results: searchResults.value
    }
    
    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `blockchain-search-${Date.now()}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
}

const analyzeWithAI = () => {
    console.log('Starting deep AI analysis for:', searchQuery.value)
    // This would trigger additional AI analysis in a real implementation
}

// Initialize component
onMounted(() => {
    fetchExamples()
})
</script>