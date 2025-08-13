<template>
  <div class="streaming-analysis">
    <!-- Analysis Header -->
    <div class="card bg-base-100 shadow-xl mb-4">
      <div class="card-body">
        <div class="flex justify-between items-center">
          <h2 class="card-title">
            <span class="loading loading-dots loading-sm" v-if="isStreaming"></span>
            OpenAI Analysis
          </h2>
          <div class="badge" :class="statusBadgeClass">{{ analysis.status }}</div>
        </div>
        
        <!-- Progress Bar -->
        <div v-if="isStreaming" class="w-full">
          <div class="flex justify-between text-sm mb-1">
            <span>Streaming Progress</span>
            <span>{{ analysis.tokens_streamed || 0 }} / {{ analysis.token_limit || 0 }} tokens</span>
          </div>
          <progress 
            class="progress progress-primary w-full" 
            :value="analysis.progress || 0" 
            max="100"
          ></progress>
          <div class="text-xs text-gray-500 mt-1">
            Duration: {{ formatDuration(analysis.duration) }} | 
            Model: {{ analysis.model }}
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="analysis.error_message" class="alert alert-error mt-3">
          <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ analysis.error_message }}</span>
        </div>
      </div>
    </div>

    <!-- Real-time Token Display -->
    <div v-if="isStreaming" class="card bg-base-100 shadow-xl mb-4">
      <div class="card-body">
        <h3 class="card-title text-sm">Live Token Stream</h3>
        <div 
          ref="tokenDisplay" 
          class="bg-base-200 p-4 rounded-lg h-40 overflow-y-auto font-mono text-sm"
          style="white-space: pre-wrap;"
        >
          {{ streamedContent }}
          <span v-if="isStreaming" class="animate-pulse">|</span>
        </div>
        <div class="text-xs text-gray-500 mt-2">
          <span class="badge badge-outline badge-sm">{{ tokenCount }} tokens</span>
          <span class="ml-2">{{ tokensPerSecond }} tokens/sec</span>
        </div>
      </div>
    </div>

    <!-- Analysis Results -->
    <div v-if="hasResults" class="space-y-4">
      <!-- Summary -->
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h3 class="card-title">Analysis Summary</h3>
          <p class="text-gray-700">{{ results.summary }}</p>
          
          <div v-if="results.risk_score !== null" class="mt-3">
            <div class="flex items-center gap-2">
              <span class="text-sm">Risk Score:</span>
              <div class="radial-progress text-primary" :style="`--value:${results.risk_score}`" :class="riskScoreClass">
                {{ results.risk_score }}%
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Security Findings -->
      <div v-if="results.findings && results.findings.length > 0" class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h3 class="card-title">Security Findings</h3>
          <div class="space-y-2">
            <div 
              v-for="(finding, index) in results.findings" 
              :key="index"
              class="alert" 
              :class="findingAlertClass(finding.severity)"
            >
              <div>
                <h4 class="font-semibold">{{ finding.title || 'Security Issue' }}</h4>
                <p class="text-sm">{{ finding.description }}</p>
                <div class="badge badge-sm mt-1" :class="severityBadgeClass(finding.severity)">
                  {{ finding.severity.toUpperCase() }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recommendations -->
      <div v-if="results.recommendations && results.recommendations.length > 0" class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h3 class="card-title">Recommendations</h3>
          <ul class="list-disc list-inside space-y-1">
            <li v-for="(rec, index) in results.recommendations" :key="index" class="text-sm">
              {{ rec }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Performance Metrics -->
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h3 class="card-title">Performance Metrics</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="stat">
              <div class="stat-title">Tokens Used</div>
              <div class="stat-value text-sm">{{ results.performance?.tokens_used || 0 }}</div>
            </div>
            <div class="stat">
              <div class="stat-title">Duration</div>
              <div class="stat-value text-sm">{{ formatDuration(results.performance?.duration) }}</div>
            </div>
            <div class="stat">
              <div class="stat-title">Speed</div>
              <div class="stat-value text-sm">{{ results.performance?.tokens_per_second || 0 }}/s</div>
            </div>
            <div class="stat">
              <div class="stat-title">Model</div>
              <div class="stat-value text-sm">{{ results.performance?.model || 'Unknown' }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Control Buttons -->
    <div class="flex gap-2 mt-4">
      <button 
        v-if="canCancel" 
        @click="cancelAnalysis" 
        class="btn btn-error btn-sm"
        :disabled="cancelling"
      >
        <span v-if="cancelling" class="loading loading-spinner loading-xs"></span>
        Cancel Analysis
      </button>
      
      <button 
        v-if="isCompleted" 
        @click="downloadResults" 
        class="btn btn-primary btn-sm"
      >
        Download Results
      </button>
      
      <button 
        @click="refreshStatus" 
        class="btn btn-ghost btn-sm"
        :disabled="refreshing"
      >
        <span v-if="refreshing" class="loading loading-spinner loading-xs"></span>
        Refresh
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

const props = defineProps({
  analysisId: {
    type: Number,
    required: true
  },
  autoRefresh: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['completed', 'error', 'cancelled'])

// Reactive data
const analysis = ref({})
const results = ref({})
const streamedContent = ref('')
const tokenCount = ref(0)
const tokensPerSecond = ref(0)
const refreshing = ref(false)
const cancelling = ref(false)
const tokenDisplay = ref(null)

// WebSocket/Echo connection for real-time updates
let echoChannel = null
let refreshInterval = null

// Computed properties
const isStreaming = computed(() => 
  analysis.value.status === 'processing' || analysis.value.status === 'streaming'
)

const isCompleted = computed(() => analysis.value.status === 'completed')
const hasResults = computed(() => isCompleted.value && Object.keys(results.value).length > 0)
const canCancel = computed(() => isStreaming.value)

const statusBadgeClass = computed(() => {
  switch (analysis.value.status) {
    case 'pending': return 'badge-warning'
    case 'processing': return 'badge-info'
    case 'streaming': return 'badge-info'
    case 'completed': return 'badge-success'
    case 'failed': return 'badge-error'
    case 'cancelled': return 'badge-neutral'
    default: return 'badge-ghost'
  }
})

const riskScoreClass = computed(() => {
  const score = results.value.risk_score || 0
  if (score >= 80) return 'text-error'
  if (score >= 60) return 'text-warning'
  if (score >= 40) return 'text-info'
  return 'text-success'
})

// Methods
const refreshStatus = async () => {
  refreshing.value = true
  try {
    const response = await axios.get(`/api/streaming/analysis/${props.analysisId}/status`)
    if (response.data.success) {
      analysis.value = response.data.analysis
      
      if (isCompleted.value) {
        await fetchResults()
        emit('completed', results.value)
      }
    }
  } catch (error) {
    console.error('Failed to refresh status:', error)
    emit('error', error)
  } finally {
    refreshing.value = false
  }
}

const fetchResults = async () => {
  try {
    const response = await axios.get(`/api/streaming/analysis/${props.analysisId}/results`)
    if (response.data.success) {
      results.value = response.data
    }
  } catch (error) {
    console.error('Failed to fetch results:', error)
  }
}

const cancelAnalysis = async () => {
  cancelling.value = true
  try {
    const response = await axios.post(`/api/streaming/analysis/${props.analysisId}/cancel`)
    if (response.data.success) {
      analysis.value.status = 'cancelled'
      emit('cancelled')
    }
  } catch (error) {
    console.error('Failed to cancel analysis:', error)
    emit('error', error)
  } finally {
    cancelling.value = false
  }
}

const downloadResults = () => {
  const data = JSON.stringify(results.value, null, 2)
  const blob = new Blob([data], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `analysis-${props.analysisId}-results.json`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

const setupRealTimeConnection = () => {
  // Assuming Laravel Echo is configured
  if (window.Echo && analysis.value.job_id) {
    echoChannel = window.Echo.private(`openai-stream.${analysis.value.job_id}`)
      .listen('token.received', (e) => {
        tokenCount.value = e.token_count
        streamedContent.value += e.token
        
        // Calculate tokens per second
        const duration = (Date.now() - Date.parse(analysis.value.started_at)) / 1000
        tokensPerSecond.value = Math.round(e.token_count / duration * 10) / 10
        
        // Auto-scroll to bottom
        nextTick(() => {
          if (tokenDisplay.value) {
            tokenDisplay.value.scrollTop = tokenDisplay.value.scrollHeight
          }
        })
      })
  }
}

const formatDuration = (seconds) => {
  if (!seconds) return '0s'
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`
}

const findingAlertClass = (severity) => {
  switch (severity) {
    case 'critical': return 'alert-error'
    case 'high': return 'alert-warning'
    case 'medium': return 'alert-info'
    case 'low': return 'alert-success'
    default: return 'alert-info'
  }
}

const severityBadgeClass = (severity) => {
  switch (severity) {
    case 'critical': return 'badge-error'
    case 'high': return 'badge-warning'
    case 'medium': return 'badge-info'
    case 'low': return 'badge-success'
    default: return 'badge-neutral'
  }
}

// Lifecycle
onMounted(async () => {
  await refreshStatus()
  
  if (isStreaming.value) {
    setupRealTimeConnection()
  }
  
  if (props.autoRefresh && !isCompleted.value) {
    refreshInterval = setInterval(refreshStatus, 2000)
  }
})

onUnmounted(() => {
  if (echoChannel) {
    echoChannel.stopListening('token.received')
    window.Echo.leave(`openai-stream.${analysis.value.job_id}`)
  }
  
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})
</script>

<style scoped>
.streaming-analysis {
  @apply max-w-4xl mx-auto p-4;
}

.token-display {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  line-height: 1.4;
}

.animate-pulse {
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0; }
}
</style>