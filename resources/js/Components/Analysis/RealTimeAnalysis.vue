<template>
  <div class="real-time-analysis">
    <!-- Analysis Header -->
    <div class="analysis-header bg-white rounded-lg shadow-sm p-6 mb-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">Contract Security Analysis</h2>
          <p class="text-gray-600 mt-1">{{ contractAddress }} on {{ network }}</p>
        </div>
        <div class="flex items-center space-x-4">
          <div class="flex items-center space-x-2">
            <div 
              :class="statusColors[analysis.status]" 
              class="w-3 h-3 rounded-full"
            ></div>
            <span class="text-sm font-medium capitalize">{{ analysis.status }}</span>
          </div>
          <div v-if="analysis.progress > 0" class="text-sm text-gray-500">
            {{ analysis.progress }}% complete
          </div>
        </div>
      </div>
      
      <!-- Progress Bar -->
      <div v-if="analysis.status === 'processing'" class="mt-4">
        <div class="bg-gray-200 rounded-full h-2">
          <div 
            class="bg-blue-600 h-2 rounded-full transition-all duration-300"
            :style="{ width: analysis.progress + '%' }"
          ></div>
        </div>
        <p v-if="analysis.current_step" class="text-sm text-gray-600 mt-2">
          {{ analysis.current_step }}
        </p>
      </div>
    </div>

    <!-- Token Stream Display -->
    <div 
      v-if="analysis.status === 'processing' && showTokenStream" 
      class="token-stream bg-gray-900 rounded-lg p-6 mb-6"
    >
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-white">AI Analysis Stream</h3>
        <div class="flex items-center space-x-4">
          <span class="text-sm text-gray-400">{{ tokenCount }} tokens</span>
          <button 
            @click="showTokenStream = false"
            class="text-gray-400 hover:text-white"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
      
      <div 
        ref="tokenDisplay"
        class="token-content bg-panel rounded p-4 h-64 overflow-y-auto font-mono text-sm text-green-400"
      >
        <span class="streaming-content">{{ streamedContent }}</span>
        <span 
          v-if="isStreaming" 
          class="animate-pulse text-green-300"
        >▊</span>
      </div>
      
      <div class="mt-4 flex items-center justify-between text-sm text-gray-400">
        <span>Model: {{ streamMetadata.model || 'gpt-4' }}</span>
        <span>Processing time: {{ formatTime(streamMetadata.processing_time_ms || 0) }}</span>
      </div>
    </div>

    <!-- Analysis Results -->
    <div v-if="analysis.status === 'completed'" class="analysis-results">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="flex items-center">
            <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3">
              <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ analysis.findings_count || 0 }}</p>
              <p class="text-sm text-gray-600">Total Findings</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="flex items-center">
            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
              <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ getCriticalCount() }}</p>
              <p class="text-sm text-gray-600">Critical Issues</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="flex items-center">
            <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
              <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
              </svg>
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ getRiskScore() }}</p>
              <p class="text-sm text-gray-600">Risk Score</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="flex items-center">
            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ formatTime(analysis.processing_time_ms) }}</p>
              <p class="text-sm text-gray-600">Analysis Time</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Findings List -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Security Findings</h3>
        
        <div v-if="analysis.findings && analysis.findings.length > 0" class="space-y-4">
          <div 
            v-for="(finding, index) in analysis.findings" 
            :key="index"
            class="border rounded-lg p-4 hover:bg-panel transition-colors"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center space-x-2 mb-2">
                  <span 
                    :class="severityColors[finding.severity]"
                    class="px-2 py-1 rounded text-xs font-medium"
                  >
                    {{ finding.severity }}
                  </span>
                  <span class="text-sm text-gray-500">{{ finding.category }}</span>
                  <span v-if="finding.location && finding.location.line" class="text-sm text-gray-500">
                    Line {{ finding.location.line }}
                  </span>
                </div>
                
                <h4 class="font-medium text-gray-900 mb-2">{{ finding.title }}</h4>
                <p class="text-gray-600 text-sm mb-3">{{ finding.description }}</p>
                
                <div v-if="finding.recommendation && finding.recommendation.summary" class="bg-blue-50 rounded p-3">
                  <p class="text-sm text-blue-800">
                    <strong>Recommendation:</strong> {{ finding.recommendation.summary }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div v-else class="text-center py-8 text-gray-500">
          No security findings detected. This contract appears to be secure!
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="analysis.status === 'failed'" class="bg-red-50 border border-red-200 rounded-lg p-6">
      <div class="flex items-center mb-4">
        <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-red-800">Analysis Failed</h3>
      </div>
      <p class="text-red-700">{{ analysis.error_message || 'An error occurred during analysis.' }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
  analysisId: {
    type: String,
    required: true
  },
  contractAddress: {
    type: String,
    required: true
  },
  network: {
    type: String,
    required: true
  }
})

// Reactive state
const analysis = reactive({
  status: 'pending',
  progress: 0,
  current_step: '',
  findings_count: 0,
  findings: [],
  error_message: null,
  processing_time_ms: 0
})

const streamedContent = ref('')
const tokenCount = ref(0)
const isStreaming = ref(false)
const showTokenStream = ref(true)
const streamMetadata = reactive({
  model: '',
  processing_time_ms: 0
})

const tokenDisplay = ref(null)

// Style mappings
const statusColors = {
  pending: 'bg-gray-400',
  processing: 'bg-blue-500 animate-pulse',
  completed: 'bg-green-500',
  failed: 'bg-red-500'
}

const severityColors = {
  CRITICAL: 'bg-red-100 text-red-800',
  HIGH: 'bg-orange-100 text-orange-800',
  MEDIUM: 'bg-yellow-100 text-yellow-800',
  LOW: 'bg-blue-100 text-blue-800',
  INFO: 'bg-ink text-gray-800'
}

// Computed properties
const getCriticalCount = () => {
  if (!analysis.findings) return 0
  return analysis.findings.filter(f => ['CRITICAL', 'HIGH'].includes(f.severity)).length
}

const getRiskScore = () => {
  if (!analysis.findings) return 0
  let score = 0
  analysis.findings.forEach(f => {
    switch (f.severity) {
      case 'CRITICAL': score += 10; break
      case 'HIGH': score += 5; break
      case 'MEDIUM': score += 2; break
      case 'LOW': score += 1; break
    }
  })
  return Math.min(100, score)
}

const formatTime = (ms) => {
  if (!ms) return '0ms'
  if (ms < 1000) return `${Math.round(ms)}ms`
  return `${(ms / 1000).toFixed(1)}s`
}

// WebSocket connection for real-time updates
let echo = null

onMounted(() => {
  // Initialize Echo for real-time updates
  if (window.Echo) {
    echo = window.Echo
    
    // Listen for analysis updates
    echo.channel(`analysis.${props.analysisId}`)
      .listen('analysis.started', (data) => {
        Object.assign(analysis, data)
        isStreaming.value = true
      })
      .listen('analysis.progress', (data) => {
        analysis.progress = data.progress
        analysis.current_step = data.message
      })
      .listen('analysis.completed', (data) => {
        Object.assign(analysis, data)
        isStreaming.value = false
      })
      .listen('analysis.failed', (data) => {
        Object.assign(analysis, data)
        isStreaming.value = false
      })
      .listen('token.streamed', (data) => {
        streamedContent.value = data.content
        tokenCount.value = data.token_count
        Object.assign(streamMetadata, data.metadata)
        
        // Auto-scroll to bottom
        nextTick(() => {
          if (tokenDisplay.value) {
            tokenDisplay.value.scrollTop = tokenDisplay.value.scrollHeight
          }
        })
      })
  }
  
  // Load initial analysis data
  loadAnalysisData()
})

onUnmounted(() => {
  if (echo) {
    echo.leaveChannel(`analysis.${props.analysisId}`)
  }
})

const loadAnalysisData = async () => {
  try {
    const response = await fetch(`/api/analyses/${props.analysisId}`)
    const data = await response.json()
    Object.assign(analysis, data)
  } catch (error) {
    console.error('Failed to load analysis data:', error)
  }
}
</script>

<style scoped>
.streaming-content {
  white-space: pre-wrap;
  word-break: break-word;
}

.token-content {
  scrollbar-width: thin;
  scrollbar-color: #4B5563 #1F2937;
}

.token-content::-webkit-scrollbar {
  width: 6px;
}

.token-content::-webkit-scrollbar-track {
  background: #1F2937;
}

.token-content::-webkit-scrollbar-thumb {
  background: #4B5563;
  border-radius: 3px;
}

.token-content::-webkit-scrollbar-thumb:hover {
  background: #6B7280;
}
</style>