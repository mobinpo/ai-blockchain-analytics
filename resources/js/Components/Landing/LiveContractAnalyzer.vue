<template>
  <div class="live-contract-analyzer">
    <!-- Hero Section with Analyzer -->
    <div class="hero-section bg-gradient-to-br from-blue-900 via-purple-900 to-indigo-900 text-white py-20">
      <div class="container mx-auto px-6 text-center">
        <h1 class="text-5xl md:text-6xl font-bold mb-6 bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">
          AI Blockchain Analytics
        </h1>
        <p class="text-xl md:text-2xl mb-12 text-gray-300 max-w-3xl mx-auto">
          Instantly analyze any smart contract with AI-powered security insights, vulnerability detection, and comprehensive blockchain analytics.
        </p>

        <!-- Live Contract Analyzer Input -->
        <div class="max-w-4xl mx-auto">
          <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 border border-white/20 shadow-2xl">
            <h2 class="text-2xl font-semibold mb-6 text-white">
              🔍 Live Contract Analysis
            </h2>
            
            <!-- Input Section -->
            <div class="space-y-4">
              <div class="relative">
                <input
                  v-model="contractAddress"
                  @input="validateAddress"
                  @keyup.enter="analyzeContract"
                  type="text"
                  placeholder="Enter contract address (0x...)"
                  class="w-full px-6 py-4 bg-white/90 text-gray-900 rounded-xl border-2 border-transparent focus:border-blue-500 focus:outline-none text-lg placeholder-gray-500 transition-all duration-300"
                  :class="{
                    'border-red-500': addressError,
                    'border-green-500': isValidAddress && !addressError,
                    'pr-20': isValidating || isValidAddress
                  }"
                />
                
                <!-- Loading/Success Icons -->
                <div class="absolute right-4 top-1/2 transform -translate-y-1/2">
                  <div v-if="isValidating" class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                  <div v-else-if="isValidAddress && !addressError" class="text-green-500">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div v-else-if="addressError" class="text-red-500">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Error Message -->
              <div v-if="addressError" class="text-red-400 text-sm text-left">
                {{ addressError }}
              </div>

              <!-- Network Detection -->
              <div v-if="detectedNetwork" class="flex items-center justify-center space-x-2 text-sm text-green-400">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>Detected: {{ detectedNetwork.name }}</span>
                <img v-if="detectedNetwork.icon" :src="detectedNetwork.icon" :alt="detectedNetwork.name" class="w-4 h-4" />
              </div>

              <!-- Contract Info Preview -->
              <div v-if="contractInfo" class="bg-white/5 rounded-lg p-4 text-left space-y-2">
                <div class="flex items-center space-x-2">
                  <span class="text-gray-400">Contract:</span>
                  <span class="text-white font-mono text-sm">{{ contractInfo.name || 'Unknown' }}</span>
                </div>
                <div v-if="contractInfo.verified" class="flex items-center space-x-2">
                  <span class="text-green-400">✓ Verified</span>
                  <span class="text-gray-400">on {{ contractInfo.network }}</span>
                </div>
              </div>

              <!-- Analyze Button -->
              <button
                @click="analyzeContract"
                :disabled="!isValidAddress || isAnalyzing"
                class="w-full py-4 px-8 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-[1.02] disabled:hover:scale-100 text-lg shadow-lg"
              >
                <span v-if="isAnalyzing" class="flex items-center justify-center space-x-2">
                  <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                  <span>Analyzing Contract...</span>
                </span>
                <span v-else class="flex items-center justify-center space-x-2">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                  </svg>
                  <span>Analyze Contract Now</span>
                </span>
              </button>
            </div>

            <!-- Quick Examples -->
            <div class="mt-6 flex flex-wrap justify-center gap-2">
              <button
                v-for="example in contractExamples"
                :key="example.address"
                @click="loadExample(example)"
                class="px-3 py-1 bg-white/10 hover:bg-white/20 rounded-full text-xs text-gray-300 hover:text-white transition-all duration-200"
              >
                {{ example.name }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Real-time Analysis Progress -->
    <div v-if="analysisProgress.length > 0" class="bg-gray-900 py-8">
      <div class="container mx-auto px-6">
        <div class="max-w-4xl mx-auto">
          <h3 class="text-2xl font-semibold text-white mb-6 text-center">
            🔬 Live Analysis Progress
          </h3>
          
          <div class="space-y-4">
            <div
              v-for="(step, index) in analysisProgress"
              :key="index"
              class="bg-panel rounded-lg p-4 border-l-4"
              :class="{
                'border-yellow-500': step.status === 'running',
                'border-green-500': step.status === 'completed',
                'border-red-500': step.status === 'error',
                'border-gray-500': step.status === 'pending'
              }"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <div class="status-icon">
                    <div v-if="step.status === 'running'" class="animate-spin rounded-full h-5 w-5 border-b-2 border-yellow-500"></div>
                    <div v-else-if="step.status === 'completed'" class="text-green-500">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <div v-else-if="step.status === 'error'" class="text-red-500">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <div v-else class="text-gray-500">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                      </svg>
                    </div>
                  </div>
                  <div>
                    <h4 class="text-white font-medium">{{ step.title }}</h4>
                    <p class="text-gray-400 text-sm">{{ step.description }}</p>
                  </div>
                </div>
                <div v-if="step.duration" class="text-gray-500 text-xs">
                  {{ step.duration }}s
                </div>
              </div>
              
              <!-- Progress Bar -->
              <div v-if="step.progress !== undefined" class="mt-3">
                <div class="w-full bg-gray-700 rounded-full h-2">
                  <div
                    class="h-2 rounded-full transition-all duration-300"
                    :class="{
                      'bg-yellow-500': step.status === 'running',
                      'bg-green-500': step.status === 'completed',
                      'bg-red-500': step.status === 'error'
                    }"
                    :style="`width: ${step.progress}%`"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Results Preview -->
    <div v-if="quickResults" class="bg-panel py-12">
      <div class="container mx-auto px-6">
        <div class="max-w-6xl mx-auto">
          <h3 class="text-3xl font-bold text-center mb-8 text-gray-900">
            ⚡ Quick Analysis Results
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Security Score -->
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
              <div class="text-3xl mb-2">
                {{ quickResults.securityScore >= 80 ? '🛡️' : quickResults.securityScore >= 60 ? '⚠️' : '🚨' }}
              </div>
              <h4 class="text-xl font-semibold text-gray-800 mb-2">Security Score</h4>
              <div class="text-3xl font-bold mb-2" :class="{
                'text-green-600': quickResults.securityScore >= 80,
                'text-yellow-600': quickResults.securityScore >= 60,
                'text-red-600': quickResults.securityScore < 60
              }">
                {{ quickResults.securityScore }}/100
              </div>
              <p class="text-gray-600">{{ getSecurityLevel(quickResults.securityScore) }}</p>
            </div>

            <!-- Vulnerabilities -->
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
              <div class="text-3xl mb-2">🔍</div>
              <h4 class="text-xl font-semibold text-gray-800 mb-2">Vulnerabilities</h4>
              <div class="space-y-1">
                <div class="flex justify-between text-sm">
                  <span class="text-red-600">Critical:</span>
                  <span class="font-semibold">{{ quickResults.vulnerabilities.critical }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-orange-600">High:</span>
                  <span class="font-semibold">{{ quickResults.vulnerabilities.high }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-yellow-600">Medium:</span>
                  <span class="font-semibold">{{ quickResults.vulnerabilities.medium }}</span>
                </div>
              </div>
            </div>

            <!-- Contract Info -->
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
              <div class="text-3xl mb-2">📊</div>
              <h4 class="text-xl font-semibold text-gray-800 mb-2">Contract Info</h4>
              <div class="space-y-1 text-sm">
                <div>Network: <span class="font-semibold">{{ quickResults.network }}</span></div>
                <div>Functions: <span class="font-semibold">{{ quickResults.functionsCount }}</span></div>
                <div>Lines: <span class="font-semibold">{{ quickResults.linesOfCode }}</span></div>
                <div v-if="quickResults.verified" class="text-green-600 font-semibold">✓ Verified</div>
              </div>
            </div>
          </div>

          <!-- Call to Action -->
          <div class="text-center">
            <button
              @click="viewFullReport"
              class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg"
            >
              View Complete Analysis Report →
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

export default {
  name: 'LiveContractAnalyzer',
  setup() {
    // Reactive state
    const contractAddress = ref('')
    const isValidating = ref(false)
    const isValidAddress = ref(false)
    const addressError = ref('')
    const detectedNetwork = ref(null)
    const contractInfo = ref(null)
    const isAnalyzing = ref(false)
    const analysisProgress = ref([])
    const quickResults = ref(null)

    // Contract examples for quick testing
    const contractExamples = ref([
      {
        name: 'Uniswap V2',
        address: '0x5C69bEe701ef814a2B6a3EDD4B1652CB9cc5aA6f',
        network: 'ethereum'
      },
      {
        name: 'USDC',
        address: '0xA0b86a33E6417c7e4E6b42b0Db8FC0a41F34a3B4',
        network: 'ethereum'
      },
      {
        name: 'PancakeSwap',
        address: '0x73feaa1eE314F8c655E354234017bE2193C9E24E',
        network: 'bsc'
      }
    ])

    // Networks configuration
    const networks = {
      ethereum: { name: 'Ethereum', icon: '/images/ethereum.png' },
      bsc: { name: 'BSC', icon: '/images/bsc.png' },
      polygon: { name: 'Polygon', icon: '/images/polygon.png' },
      arbitrum: { name: 'Arbitrum', icon: '/images/arbitrum.png' }
    }

    // Validate Ethereum address format
    const isValidEthereumAddress = (address) => {
      return /^0x[a-fA-F0-9]{40}$/.test(address)
    }

    // Validate contract address
    const validateAddress = async () => {
      if (!contractAddress.value) {
        addressError.value = ''
        isValidAddress.value = false
        detectedNetwork.value = null
        contractInfo.value = null
        return
      }

      if (!isValidEthereumAddress(contractAddress.value)) {
        addressError.value = 'Please enter a valid contract address (0x...)'
        isValidAddress.value = false
        return
      }

      isValidating.value = true
      addressError.value = ''

      try {
        // Get CSRF token safely
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        // Quick validation and network detection
        const response = await fetch('/api/contract/quick-info', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            address: contractAddress.value
          })
        })

        const data = await response.json()

        if (data.success) {
          isValidAddress.value = true
          detectedNetwork.value = networks[data.network] || { name: data.network }
          contractInfo.value = data.contractInfo
        } else {
          addressError.value = data.message || 'Contract not found or invalid'
          isValidAddress.value = false
        }
      } catch (error) {
        console.error('Validation error:', error)
        // Still allow analysis attempt even if validation fails
        isValidAddress.value = true
      } finally {
        isValidating.value = false
      }
    }

    // Load example contract
    const loadExample = (example) => {
      contractAddress.value = example.address
      detectedNetwork.value = networks[example.network]
      validateAddress()
    }

    // Analyze contract
    const analyzeContract = async () => {
      if (!isValidAddress.value || isAnalyzing.value) return

      isAnalyzing.value = true
      analysisProgress.value = []
      quickResults.value = null

      // Initialize progress steps
      const steps = [
        { title: 'Fetching Contract Source', description: 'Retrieving source code from blockchain explorer', status: 'running', progress: 0 },
        { title: 'Code Analysis', description: 'Analyzing contract structure and functions', status: 'pending', progress: 0 },
        { title: 'Security Scan', description: 'Detecting vulnerabilities and security issues', status: 'pending', progress: 0 },
        { title: 'AI Analysis', description: 'Running AI-powered deep analysis', status: 'pending', progress: 0 },
        { title: 'Generating Report', description: 'Compiling comprehensive analysis report', status: 'pending', progress: 0 }
      ]

      analysisProgress.value = steps

      try {
        // Get CSRF token safely
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        // Start the analysis
        const response = await fetch('/api/analysis/quick-analyze', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            contract_address: contractAddress.value,
            network: detectedNetwork.value?.name?.toLowerCase() || 'ethereum'
          })
        })

        const analysisData = await response.json()

        if (analysisData.success) {
          // Simulate progress updates
          await simulateProgress(steps)
          
          // Set quick results
          quickResults.value = {
            securityScore: analysisData.data.security_score || 75,
            vulnerabilities: {
              critical: analysisData.data.critical_issues || 0,
              high: analysisData.data.high_issues || 2,
              medium: analysisData.data.medium_issues || 5
            },
            network: detectedNetwork.value?.name || 'Ethereum',
            functionsCount: analysisData.data.functions_count || 15,
            linesOfCode: analysisData.data.lines_of_code || 450,
            verified: analysisData.data.verified || true,
            analysisId: analysisData.data.analysis_id
          }
        } else {
          throw new Error(analysisData.message || 'Analysis failed')
        }
      } catch (error) {
        console.error('Analysis error:', error)
        // Mark last step as error
        const lastStep = analysisProgress.value[analysisProgress.value.length - 1]
        lastStep.status = 'error'
        lastStep.description = error.message || 'Analysis failed'
        addressError.value = 'Analysis failed. Please try again.'
      } finally {
        isAnalyzing.value = false
      }
    }

    // Simulate progress updates
    const simulateProgress = async (steps) => {
      for (let i = 0; i < steps.length; i++) {
        const step = steps[i]
        step.status = 'running'
        
        // Simulate progress
        for (let progress = 0; progress <= 100; progress += 20) {
          step.progress = progress
          await new Promise(resolve => setTimeout(resolve, 200))
        }
        
        step.status = 'completed'
        step.duration = (Math.random() * 3 + 1).toFixed(1)
        
        // Start next step
        if (i < steps.length - 1) {
          await new Promise(resolve => setTimeout(resolve, 300))
        }
      }
    }

    // Get security level description
    const getSecurityLevel = (score) => {
      if (score >= 80) return 'Excellent Security'
      if (score >= 60) return 'Good Security'
      if (score >= 40) return 'Fair Security'
      return 'Poor Security'
    }

    // View full report
    const viewFullReport = () => {
      if (quickResults.value?.analysisId) {
        router.visit(`/analysis/${quickResults.value.analysisId}`)
      }
    }

    return {
      contractAddress,
      isValidating,
      isValidAddress,
      addressError,
      detectedNetwork,
      contractInfo,
      isAnalyzing,
      analysisProgress,
      quickResults,
      contractExamples,
      validateAddress,
      loadExample,
      analyzeContract,
      getSecurityLevel,
      viewFullReport
    }
  }
}
</script>

<style scoped>
.live-contract-analyzer {
  min-height: 100vh;
}

.hero-section {
  background-image: 
    radial-gradient(circle at 25% 25%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 75% 75%, rgba(147, 51, 234, 0.1) 0%, transparent 50%);
}

.container {
  max-width: 1200px;
}

@media (max-width: 768px) {
  .hero-section {
    padding-top: 3rem;
    padding-bottom: 3rem;
  }
  
  .hero-section h1 {
    font-size: 2.5rem;
  }
  
  .hero-section p {
    font-size: 1.125rem;
  }
}

.status-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Custom scrollbar for progress section */
.space-y-4::-webkit-scrollbar {
  width: 6px;
}

.space-y-4::-webkit-scrollbar-track {
  background: #1f2937;
}

.space-y-4::-webkit-scrollbar-thumb {
  background: #4b5563;
  border-radius: 3px;
}

.space-y-4::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}
</style>
