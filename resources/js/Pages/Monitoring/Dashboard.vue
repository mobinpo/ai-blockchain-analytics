<template>
  <div class="monitoring-dashboard">
    <Head title="Monitoring Dashboard - AI Blockchain Analytics" />
    
    <AuthenticatedLayout>
      <template #header>
        <div class="flex justify-between items-center">
          <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              📊 Monitoring Dashboard
            </h2>
            <p class="mt-1 text-sm text-gray-600">
              System health, errors, and performance monitoring
            </p>
          </div>
          
          <!-- Status Indicators -->
          <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-2">
              <div :class="systemStatus.color" class="w-3 h-3 rounded-full"></div>
              <span class="text-sm font-medium">{{ systemStatus.text }}</span>
            </div>
            <div class="text-sm text-gray-500">
              Last updated: {{ lastUpdated }}
            </div>
          </div>
        </div>
      </template>

      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          
          <!-- System Overview Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Application Health -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
              <div class="p-6">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div :class="healthMetrics.app.color" class="w-8 h-8 rounded-full flex items-center justify-center">
                      <component :is="healthMetrics.app.icon" class="w-4 h-4 text-white" />
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">Application Health</div>
                    <div class="text-2xl font-bold" :class="healthMetrics.app.textColor">
                      {{ healthMetrics.app.value }}%
                    </div>
                  </div>
                </div>
                <div class="mt-4">
                  <div class="text-xs text-gray-500">
                    {{ healthMetrics.app.description }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Error Rate -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
              <div class="p-6">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div :class="healthMetrics.errors.color" class="w-8 h-8 rounded-full flex items-center justify-center">
                      <component :is="healthMetrics.errors.icon" class="w-4 h-4 text-white" />
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">Error Rate</div>
                    <div class="text-2xl font-bold" :class="healthMetrics.errors.textColor">
                      {{ healthMetrics.errors.value }}%
                    </div>
                  </div>
                </div>
                <div class="mt-4">
                  <div class="text-xs text-gray-500">
                    {{ healthMetrics.errors.description }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Response Time -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
              <div class="p-6">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div :class="healthMetrics.response.color" class="w-8 h-8 rounded-full flex items-center justify-center">
                      <component :is="healthMetrics.response.icon" class="w-4 h-4 text-white" />
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">Avg Response Time</div>
                    <div class="text-2xl font-bold" :class="healthMetrics.response.textColor">
                      {{ healthMetrics.response.value }}ms
                    </div>
                  </div>
                </div>
                <div class="mt-4">
                  <div class="text-xs text-gray-500">
                    {{ healthMetrics.response.description }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Active Users -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
              <div class="p-6">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div :class="healthMetrics.users.color" class="w-8 h-8 rounded-full flex items-center justify-center">
                      <component :is="healthMetrics.users.icon" class="w-4 h-4 text-white" />
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">Active Users</div>
                    <div class="text-2xl font-bold" :class="healthMetrics.users.textColor">
                      {{ healthMetrics.users.value }}
                    </div>
                  </div>
                </div>
                <div class="mt-4">
                  <div class="text-xs text-gray-500">
                    {{ healthMetrics.users.description }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Monitoring Tools Section -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Sentry Error Tracking -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
              <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                  <h3 class="text-lg font-medium text-gray-900 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    Sentry Error Tracking
                  </h3>
                  <span :class="sentryStatus.badgeClass" class="px-2 py-1 text-xs font-semibold rounded-full">
                    {{ sentryStatus.text }}
                  </span>
                </div>
              </div>
              <div class="p-6">
                <div class="space-y-4">
                  <!-- Recent Errors -->
                  <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Recent Errors (24h)</h4>
                    <div class="space-y-2">
                      <div v-for="error in recentErrors" :key="error.id" 
                           class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                        <div class="flex-1">
                          <div class="text-sm font-medium text-red-900">{{ error.title }}</div>
                          <div class="text-xs text-red-700">{{ error.message }}</div>
                          <div class="text-xs text-red-600 mt-1">{{ error.timestamp }}</div>
                        </div>
                        <div class="text-sm font-bold text-red-900">{{ error.count }}</div>
                      </div>
                    </div>
                  </div>

                  <!-- Sentry Links -->
                  <div class="flex space-x-4">
                    <a href="#" @click="openSentryDashboard" 
                       class="inline-flex items-center px-3 py-2 border border-red-300 shadow-sm text-sm leading-4 font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                      <ArrowTopRightOnSquareIcon class="w-4 h-4 mr-1" />
                      Open Sentry
                    </a>
                    <button @click="refreshSentryData"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                      <ArrowPathIcon class="w-4 h-4 mr-1" />
                      Refresh
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Laravel Telescope -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
              <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                  <h3 class="text-lg font-medium text-gray-900 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Laravel Telescope
                  </h3>
                  <span :class="telescopeStatus.badgeClass" class="px-2 py-1 text-xs font-semibold rounded-full">
                    {{ telescopeStatus.text }}
                  </span>
                </div>
              </div>
              <div class="p-6">
                <div class="space-y-4">
                  <!-- Performance Metrics -->
                  <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Performance Overview</h4>
                    <div class="grid grid-cols-2 gap-4">
                      <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-900">{{ performanceMetrics.avgResponseTime }}ms</div>
                        <div class="text-xs text-blue-700">Avg Response</div>
                      </div>
                      <div class="text-center p-3 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-900">{{ performanceMetrics.slowQueries }}</div>
                        <div class="text-xs text-green-700">Slow Queries</div>
                      </div>
                    </div>
                  </div>

                  <!-- Recent Requests -->
                  <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Recent Requests</h4>
                    <div class="space-y-1">
                      <div v-for="request in recentRequests" :key="request.id" 
                           class="flex items-center justify-between text-xs p-2 bg-panel rounded">
                        <span class="font-mono">{{ request.method }} {{ request.path }}</span>
                        <span :class="getStatusColor(request.status)">{{ request.status }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Telescope Access -->
                  <div class="flex space-x-4">
                    <a href="/telescope" target="_blank" 
                       class="inline-flex items-center px-3 py-2 border border-blue-300 shadow-sm text-sm leading-4 font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                      <ArrowTopRightOnSquareIcon class="w-4 h-4 mr-1" />
                      Open Telescope
                    </a>
                    <button @click="refreshTelescopeData"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                      <ArrowPathIcon class="w-4 h-4 mr-1" />
                      Refresh
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- System Resources -->
          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">System Resources</h3>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- CPU Usage -->
                <div class="text-center">
                  <div class="relative inline-flex items-center justify-center w-24 h-24">
                    <svg class="w-24 h-24 transform -rotate-90">
                      <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" class="text-gray-200"/>
                      <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" 
                              :stroke-dasharray="`${2 * Math.PI * 40}`"
                              :stroke-dashoffset="`${2 * Math.PI * 40 * (1 - systemResources.cpu / 100)}`"
                              :class="getCpuColor(systemResources.cpu)" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                      <span class="text-lg font-bold">{{ systemResources.cpu }}%</span>
                    </div>
                  </div>
                  <div class="mt-2 text-sm font-medium text-gray-700">CPU Usage</div>
                </div>

                <!-- Memory Usage -->
                <div class="text-center">
                  <div class="relative inline-flex items-center justify-center w-24 h-24">
                    <svg class="w-24 h-24 transform -rotate-90">
                      <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" class="text-gray-200"/>
                      <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" 
                              :stroke-dasharray="`${2 * Math.PI * 40}`"
                              :stroke-dashoffset="`${2 * Math.PI * 40 * (1 - systemResources.memory / 100)}`"
                              :class="getMemoryColor(systemResources.memory)" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                      <span class="text-lg font-bold">{{ systemResources.memory }}%</span>
                    </div>
                  </div>
                  <div class="mt-2 text-sm font-medium text-gray-700">Memory Usage</div>
                </div>

                <!-- Disk Usage -->
                <div class="text-center">
                  <div class="relative inline-flex items-center justify-center w-24 h-24">
                    <svg class="w-24 h-24 transform -rotate-90">
                      <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" class="text-gray-200"/>
                      <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" 
                              :stroke-dasharray="`${2 * Math.PI * 40}`"
                              :stroke-dashoffset="`${2 * Math.PI * 40 * (1 - systemResources.disk / 100)}`"
                              :class="getDiskColor(systemResources.disk)" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                      <span class="text-lg font-bold">{{ systemResources.disk }}%</span>
                    </div>
                  </div>
                  <div class="mt-2 text-sm font-medium text-gray-700">Disk Usage</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <button @click="clearApplicationCache"
                        class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                  <TrashIcon class="w-4 h-4 mr-2" />
                  Clear Cache
                </button>
                
                <button @click="clearTelescopeData"
                        class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                  <DocumentIcon class="w-4 h-4 mr-2" />
                  Clear Telescope
                </button>
                
                <button @click="downloadSystemLogs"
                        class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-panel focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                  <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
                  Download Logs
                </button>
                
                <button @click="refreshAllData"
                        class="inline-flex items-center justify-center px-4 py-2 border border-indigo-300 shadow-sm text-sm font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                  <ArrowPathIcon class="w-4 h-4 mr-2" />
                  Refresh All
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { 
  CheckCircleIcon, 
  ExclamationTriangleIcon, 
  ClockIcon, 
  UsersIcon,
  ArrowTopRightOnSquareIcon,
  ArrowPathIcon,
  TrashIcon,
  DocumentIcon,
  ArrowDownTrayIcon
} from '@heroicons/vue/24/outline'

// Props
defineProps({
  initialMetrics: {
    type: Object,
    default: () => ({})
  }
})

// Reactive data
const lastUpdated = ref(new Date().toLocaleTimeString())
const refreshInterval = ref(null)

// Health metrics
const healthMetrics = ref({
  app: {
    value: 99.2,
    color: 'bg-green-500',
    textColor: 'text-green-600',
    icon: CheckCircleIcon,
    description: 'All systems operational'
  },
  errors: {
    value: 0.3,
    color: 'bg-yellow-500',
    textColor: 'text-yellow-600',
    icon: ExclamationTriangleIcon,
    description: 'Low error rate'
  },
  response: {
    value: 245,
    color: 'bg-blue-500',
    textColor: 'text-blue-600',
    icon: ClockIcon,
    description: 'Good performance'
  },
  users: {
    value: 1247,
    color: 'bg-purple-500',
    textColor: 'text-purple-600',
    icon: UsersIcon,
    description: 'Active in last 24h'
  }
})

// System status
const systemStatus = computed(() => {
  const overallHealth = healthMetrics.value.app.value
  if (overallHealth >= 95) return { color: 'bg-green-500', text: 'Healthy' }
  if (overallHealth >= 85) return { color: 'bg-yellow-500', text: 'Warning' }
  return { color: 'bg-red-500', text: 'Critical' }
})

// Sentry data
const sentryStatus = ref({
  text: 'Connected',
  badgeClass: 'bg-green-100 text-green-800'
})

const recentErrors = ref([
  {
    id: 1,
    title: 'Database Connection Timeout',
    message: 'Connection timeout in AnalysisService',
    timestamp: '2 min ago',
    count: 3
  },
  {
    id: 2,
    title: 'Rate Limit Exceeded',
    message: 'OpenAI API rate limit reached',
    timestamp: '15 min ago',
    count: 8
  }
])

// Telescope data
const telescopeStatus = ref({
  text: 'Monitoring',
  badgeClass: 'bg-blue-100 text-blue-800'
})

const performanceMetrics = ref({
  avgResponseTime: 245,
  slowQueries: 12
})

const recentRequests = ref([
  { id: 1, method: 'GET', path: '/api/analysis', status: 200 },
  { id: 2, method: 'POST', path: '/api/contracts/analyze', status: 422 },
  { id: 3, method: 'GET', path: '/dashboard', status: 200 },
  { id: 4, method: 'POST', path: '/api/verification', status: 201 }
])

// System resources
const systemResources = ref({
  cpu: 65,
  memory: 72,
  disk: 45
})

// Methods
const getStatusColor = (status) => {
  if (status >= 200 && status < 300) return 'text-green-600'
  if (status >= 300 && status < 400) return 'text-blue-600'
  if (status >= 400 && status < 500) return 'text-yellow-600'
  return 'text-red-600'
}

const getCpuColor = (usage) => {
  if (usage < 70) return 'text-green-500'
  if (usage < 85) return 'text-yellow-500'
  return 'text-red-500'
}

const getMemoryColor = (usage) => {
  if (usage < 80) return 'text-blue-500'
  if (usage < 90) return 'text-yellow-500'
  return 'text-red-500'
}

const getDiskColor = (usage) => {
  if (usage < 70) return 'text-green-500'
  if (usage < 85) return 'text-yellow-500'
  return 'text-red-500'
}

const openSentryDashboard = () => {
  window.open('https://sentry.io/organizations/your-org/projects/', '_blank')
}

const refreshSentryData = async () => {
  try {
    const response = await fetch('/api/monitoring/sentry/status')
    const data = await response.json()
    // Update sentry data
    console.log('Sentry data refreshed:', data)
  } catch (error) {
    console.error('Failed to refresh Sentry data:', error)
  }
}

const refreshTelescopeData = async () => {
  try {
    const response = await fetch('/api/monitoring/telescope/metrics')
    const data = await response.json()
    // Update telescope data
    console.log('Telescope data refreshed:', data)
  } catch (error) {
    console.error('Failed to refresh Telescope data:', error)
  }
}

const clearApplicationCache = async () => {
  try {
    await fetch('/api/admin/cache/clear', { method: 'POST' })
    alert('Application cache cleared successfully')
  } catch (error) {
    alert('Failed to clear cache')
  }
}

const clearTelescopeData = async () => {
  if (confirm('Are you sure you want to clear all Telescope data?')) {
    try {
      await fetch('/api/admin/telescope/clear', { method: 'POST' })
      alert('Telescope data cleared successfully')
    } catch (error) {
      alert('Failed to clear Telescope data')
    }
  }
}

const downloadSystemLogs = () => {
  window.location.href = '/api/admin/logs/download'
}

const refreshAllData = async () => {
  lastUpdated.value = new Date().toLocaleTimeString()
  await Promise.all([
    refreshSentryData(),
    refreshTelescopeData()
  ])
}

// Lifecycle
onMounted(() => {
  // Set up auto-refresh every 30 seconds
  refreshInterval.value = setInterval(() => {
    refreshAllData()
  }, 30000)
})

onUnmounted(() => {
  if (refreshInterval.value) {
    clearInterval(refreshInterval.value)
  }
})
</script>

<style scoped>
.monitoring-dashboard {
  @apply min-h-screen bg-panel;
}

/* Custom progress ring animations */
circle {
  transition: stroke-dashoffset 0.6s ease-in-out;
}
</style>
