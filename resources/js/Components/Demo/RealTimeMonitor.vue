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

    <!-- Active Analyses -->
    <div class="space-y-3 mb-6">
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

const isMonitoring = ref(true)
const monitoringInterval = ref(null)

const activeAnalyses = ref([
  {
    id: 1,
    contractName: 'UniswapV4Router',
    network: 'Ethereum',
    type: 'Security Audit',
    status: 'analyzing',
    progress: 67,
    duration: 145, // seconds
    findingsCount: 8,
    gasAnalyzed: 2450000,
    currentStep: 'Reentrancy Analysis',
    eta: '2 min remaining',
    recentFindings: [
      { id: 1, title: 'Potential front-running in swap function', severity: 'medium', timestamp: '30s ago' },
      { id: 2, title: 'Gas optimization opportunity found', severity: 'low', timestamp: '1m ago' }
    ]
  },
  {
    id: 2,
    contractName: 'AAVE LendingPool',
    network: 'Polygon',
    type: 'Full Analysis',
    status: 'analyzing',
    progress: 23,
    duration: 67,
    findingsCount: 3,
    gasAnalyzed: 890000,
    currentStep: 'Function Mapping',
    eta: '8 min remaining',
    recentFindings: [
      { id: 3, title: 'Access control validation required', severity: 'high', timestamp: '15s ago' }
    ]
  },
  {
    id: 3,
    contractName: 'CompoundGovernor',
    network: 'Ethereum',
    type: 'Quick Scan',
    status: 'finalizing',
    progress: 94,
    duration: 89,
    findingsCount: 12,
    gasAnalyzed: 1750000,
    currentStep: 'Report Generation',
    eta: '30s remaining',
    recentFindings: [
      { id: 4, title: 'Integer overflow protection needed', severity: 'critical', timestamp: '5s ago' },
      { id: 5, title: 'Event emission optimization', severity: 'low', timestamp: '45s ago' }
    ]
  }
])

const queuedAnalyses = ref([
  {
    id: 4,
    contractName: 'SushiSwapV3Factory',
    network: 'Ethereum',
    type: 'Security Audit',
    estimatedStart: '3 min'
  },
  {
    id: 5,
    contractName: 'PancakeSwapRouter',
    network: 'BSC',
    type: 'Full Analysis',
    estimatedStart: '7 min'
  },
  {
    id: 6,
    contractName: 'YearnVaultV2',
    network: 'Ethereum',
    type: 'Quick Scan',
    estimatedStart: '12 min'
  },
  {
    id: 7,
    contractName: 'CurveStableSwap',
    network: 'Polygon',
    type: 'Security Audit',
    estimatedStart: '18 min'
  }
])

// Performance metrics
const totalAnalysesToday = ref(47)
const averageCompletionTime = ref(156)
const totalFindingsToday = ref(203)
const systemLoad = ref(73)

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

const simulateProgress = () => {
  activeAnalyses.value.forEach(analysis => {
    if (analysis.progress < 100) {
      // Simulate realistic progress increments
      const increment = Math.random() * 3 + 0.5
      analysis.progress = Math.min(100, analysis.progress + increment)
      analysis.duration += 2
      
      // Simulate occasional new findings
      if (Math.random() > 0.9) {
        analysis.findingsCount += 1
        const severities = ['low', 'medium', 'high', 'critical']
        const titles = [
          'Gas optimization opportunity',
          'Access control check needed',
          'State variable visibility issue',
          'Potential reentrancy vulnerability',
          'Integer overflow protection required',
          'Event emission missing',
          'Function modifier validation'
        ]
        
        analysis.recentFindings.unshift({
          id: Date.now(),
          title: titles[Math.floor(Math.random() * titles.length)],
          severity: severities[Math.floor(Math.random() * severities.length)],
          timestamp: 'just now'
        })
        
        // Keep only recent findings
        analysis.recentFindings = analysis.recentFindings.slice(0, 5)
      }
      
      // Update current step based on progress
      if (analysis.progress > 90) {
        analysis.currentStep = 'Report Generation'
        analysis.eta = '30s remaining'
      } else if (analysis.progress > 70) {
        analysis.currentStep = 'Vulnerability Assessment'
        analysis.eta = '2 min remaining'
      } else if (analysis.progress > 40) {
        analysis.currentStep = 'Code Pattern Analysis'
        analysis.eta = '5 min remaining'
      } else if (analysis.progress > 20) {
        analysis.currentStep = 'Function Mapping'
        analysis.eta = '8 min remaining'
      } else {
        analysis.currentStep = 'Contract Parsing'
        analysis.eta = 'Calculating...'
      }
    }
  })
  
  // Update performance metrics
  if (Math.random() > 0.8) {
    totalFindingsToday.value += Math.floor(Math.random() * 3)
    systemLoad.value = Math.max(45, Math.min(95, systemLoad.value + (Math.random() - 0.5) * 10))
  }
}

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
  
  monitoringInterval.value = setInterval(simulateProgress, 2000)
}

const stopMonitoring = () => {
  if (monitoringInterval.value) {
    clearInterval(monitoringInterval.value)
    monitoringInterval.value = null
  }
}

onMounted(() => {
  if (isMonitoring.value) {
    startMonitoring()
  }
})

onUnmounted(() => {
  stopMonitoring()
})
</script>