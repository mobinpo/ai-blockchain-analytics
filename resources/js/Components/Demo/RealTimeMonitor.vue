<template>
  <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h3 class="text-lg font-semibold text-gray-900">Real-Time Analysis Monitor</h3>
        <p class="text-sm text-gray-600">Live monitoring of ongoing blockchain analyses</p>
      </div>
      <div class="flex items-center space-x-3">
        <div class="flex items-center">
          <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse mr-2"></div>
          <span class="text-sm text-gray-600">{{ activeAnalyses.length }} active</span>
        </div>
        <button 
          @click="toggleMonitoring"
          :class="[
            'px-3 py-1 text-xs font-medium rounded-md transition-colors',
            isMonitoring 
              ? 'bg-red-100 text-red-700 hover:bg-red-200' 
              : 'bg-green-100 text-green-700 hover:bg-green-200'
          ]"
        >
          {{ isMonitoring ? 'Stop' : 'Start' }} Monitoring
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
      <span class="ml-2 text-sm text-gray-600">Loading real-time data...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <span class="text-red-700 text-sm">{{ error }}</span>
        <button @click="fetchAllData" class="ml-4 text-red-600 hover:text-red-800 underline text-sm">
          Retry
        </button>
      </div>
    </div>

    <!-- Active Analyses -->
    <div v-else class="space-y-3 mb-6">
      <div 
        v-for="analysis in activeAnalyses" 
        :key="analysis.id"
        class="border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow"
      >
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <div class="flex items-center space-x-3">
              <div class="flex items-center">
                <div :class="['w-3 h-3 rounded-full animate-pulse mr-2', getStatusColor(analysis.status)]"></div>
                <h4 class="text-sm font-medium text-gray-900">{{ analysis.contractName }}</h4>
              </div>
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                {{ analysis.network }}
              </span>
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                {{ analysis.type }}
              </span>
            </div>
            
            <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
              <span>{{ analysis.progress }}% complete</span>
              <span>•</span>
              <span>{{ analysis.findingsCount }} findings</span>
              <span>•</span>
              <span>{{ formatDuration(analysis.duration) }}</span>
              <span>•</span>
              <span>{{ analysis.gasAnalyzed.toLocaleString() }} gas units</span>
            </div>
          </div>
          
          <div class="flex items-center space-x-2">
            <div class="text-right">
              <div class="text-sm font-medium text-gray-900">{{ analysis.currentStep }}</div>
              <div class="text-xs text-gray-500">{{ analysis.eta || 'Calculating...' }}</div>
            </div>
          </div>
        </div>
        
        <!-- Progress Bar -->
        <div class="mt-3">
          <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
            <span>Analysis Progress</span>
            <span>{{ analysis.progress }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div 
              :class="[
                'h-2 rounded-full transition-all duration-500',
                analysis.progress < 30 ? 'bg-blue-500' :
                analysis.progress < 70 ? 'bg-yellow-500' : 'bg-green-500'
              ]"
              :style="{ width: analysis.progress + '%' }"
            ></div>
          </div>
        </div>
        
        <!-- Recent Findings -->
        <div v-if="analysis.recentFindings.length > 0" class="mt-3 pt-3 border-t border-gray-100">
          <div class="text-xs text-gray-600 mb-2">Recent Findings:</div>
          <div class="space-y-1">
            <div 
              v-for="finding in analysis.recentFindings.slice(0, 2)" 
              :key="finding.id"
              class="flex items-center space-x-2 text-xs"
            >
              <span :class="['inline-flex w-2 h-2 rounded-full', getSeverityColor(finding.severity)]"></span>
              <span class="text-gray-700 truncate">{{ finding.title }}</span>
              <span class="text-gray-500">{{ finding.timestamp }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Analysis Queue -->
    <div v-if="queuedAnalyses.length > 0" class="border-t border-gray-200 pt-4">
      <h4 class="text-sm font-semibold text-gray-900 mb-3">Analysis Queue ({{ queuedAnalyses.length }})</h4>
      <div class="space-y-2">
        <div 
          v-for="(queued, index) in queuedAnalyses.slice(0, 3)" 
          :key="queued.id"
          class="flex items-center justify-between p-3 bg-panel rounded-lg"
        >
          <div class="flex items-center space-x-3">
            <div class="text-sm font-medium text-gray-600">#{{ index + 1 }}</div>
            <div>
              <div class="text-sm font-medium text-gray-900">{{ queued.contractName }}</div>
              <div class="text-xs text-gray-500">{{ queued.network }} • {{ queued.type }}</div>
            </div>
          </div>
          <div class="text-xs text-gray-500">
            ETA: {{ queued.estimatedStart }}
          </div>
        </div>
        <div v-if="queuedAnalyses.length > 3" class="text-center">
          <span class="text-xs text-gray-500">+{{ queuedAnalyses.length - 3 }} more in queue</span>
        </div>
      </div>
    </div>

    <!-- Performance Metrics -->
    <div class="mt-6 pt-4 border-t border-gray-200">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center">
          <div class="text-lg font-semibold text-blue-600">{{ totalAnalysesToday }}</div>
          <div class="text-xs text-gray-500">Analyses Today</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-semibold text-green-600">{{ averageCompletionTime }}s</div>
          <div class="text-xs text-gray-500">Avg Time</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-semibold text-purple-600">{{ totalFindingsToday }}</div>
          <div class="text-xs text-gray-500">Findings Today</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-semibold text-orange-600">{{ systemLoad }}%</div>
          <div class="text-xs text-gray-500">System Load</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import api from '@/services/api'

const isMonitoring = ref(true)
const monitoringInterval = ref(null)
const loading = ref(false)
const error = ref(null)

const activeAnalyses = ref([])

const queuedAnalyses = ref([])

// Performance metrics - will be fetched from API
const totalAnalysesToday = ref(0)
const averageCompletionTime = ref(0)
const totalFindingsToday = ref(0)
const systemLoad = ref(0)

const getStatusColor = (status) => {
  switch (status) {
    case 'analyzing': return 'bg-blue-500'
    case 'finalizing': return 'bg-green-500'
    case 'queued': return 'bg-yellow-500'
    default: return 'bg-panel'
  }
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

const formatDuration = (seconds) => {
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60
  return minutes > 0 ? `${minutes}m ${remainingSeconds}s` : `${remainingSeconds}s`
}

// API Functions
const fetchActiveAnalyses = async () => {
  try {
    const response = await api.get('/analyses/active')
    activeAnalyses.value = response.data.analyses || response.data || []
  } catch (err) {
    console.error('Error fetching active analyses:', err)
    activeAnalyses.value = []
  }
}

const fetchQueuedAnalyses = async () => {
  try {
    const response = await api.get('/analyses/queue')
    queuedAnalyses.value = response.data.queue || response.data || []
  } catch (err) {
    console.error('Error fetching queued analyses:', err)
    queuedAnalyses.value = []
  }
}

const fetchPerformanceMetrics = async () => {
  try {
    const response = await api.get('/analyses/metrics')
    const metrics = response.data.metrics || response.data || {}
    
    totalAnalysesToday.value = metrics.totalAnalysesToday || 0
    averageCompletionTime.value = metrics.averageCompletionTime || 0
    totalFindingsToday.value = metrics.totalFindingsToday || 0
    systemLoad.value = metrics.systemLoad || 0
  } catch (err) {
    console.error('Error fetching performance metrics:', err)
  }
}

const fetchAllData = async () => {
  loading.value = true
  error.value = null
  
  try {
    await Promise.all([
      fetchActiveAnalyses(),
      fetchQueuedAnalyses(), 
      fetchPerformanceMetrics()
    ])
  } catch (err) {
    error.value = 'Failed to load real-time data'
    console.error('Error fetching real-time data:', err)
  } finally {
    loading.value = false
  }
}

// Removed simulation code - now using real API data only

const toggleMonitoring = () => {
  isMonitoring.value = !isMonitoring.value
  
  if (isMonitoring.value) {
    startMonitoring()
  } else {
    stopMonitoring()
  }
}

const startMonitoring = () => {
  if (monitoringInterval.value) return
  
  monitoringInterval.value = setInterval(async () => {
    // Refresh real data from API every few seconds for real-time updates
    await fetchActiveAnalyses()
    await fetchQueuedAnalyses()
    await fetchPerformanceMetrics()
  }, 5000) // Increased interval to 5 seconds to reduce API load
}

const stopMonitoring = () => {
  if (monitoringInterval.value) {
    clearInterval(monitoringInterval.value)
    monitoringInterval.value = null
  }
}

onMounted(async () => {
  await fetchAllData()
  if (isMonitoring.value) {
    startMonitoring()
  }
})

onUnmounted(() => {
  stopMonitoring()
})
</script>