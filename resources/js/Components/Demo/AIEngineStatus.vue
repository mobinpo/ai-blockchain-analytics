<template>
    <div class="space-y-6">
        <!-- Processing Queue Status -->
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-900">Active Jobs</p>
                        <p class="text-2xl font-bold text-blue-600">{{ processingQueue.active_jobs }}</p>
                    </div>
                    <div class="p-2 bg-blue-100 rounded-full">
                        <svg class="h-5 w-5 text-blue-600" :class="{ 'animate-spin': processingQueue.active_jobs > 0 }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-panel rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Queue</p>
                        <p class="text-2xl font-bold text-gray-900">{{ processingQueue.pending_jobs }}</p>
                    </div>
                    <div class="p-2 bg-ink rounded-full">
                        <svg class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="space-y-4">
            <h4 class="text-sm font-medium text-gray-900">Performance Metrics</h4>
            
            <!-- Accuracy -->
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Detection Accuracy</span>
                    <span class="font-medium text-gray-900">{{ performanceMetrics.accuracy }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full transition-all duration-1000" :style="{ width: performanceMetrics.accuracy + '%' }"></div>
                </div>
            </div>
            
            <!-- Uptime -->
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">System Uptime</span>
                    <span class="font-medium text-gray-900">{{ performanceMetrics.uptime }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-500 h-2 rounded-full transition-all duration-1000" :style="{ width: performanceMetrics.uptime + '%' }"></div>
                </div>
            </div>
            
            <!-- Response Time -->
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">Avg Response Time</span>
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium text-gray-900">{{ performanceMetrics.response_time }}s</span>
                    <div class="h-2 w-2 rounded-full" :class="{ 
                        'bg-green-400 animate-pulse': performanceMetrics.response_time < 2 && processingQueue.active_jobs > 0,
                        'bg-green-400': performanceMetrics.response_time < 2 && processingQueue.active_jobs === 0,
                        'bg-yellow-400': performanceMetrics.response_time >= 2 && performanceMetrics.response_time < 5,
                        'bg-red-400': performanceMetrics.response_time >= 5
                    }"></div>
                </div>
            </div>
            
            <!-- Throughput -->
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">Throughput</span>
                <span class="text-sm font-medium text-gray-900">{{ performanceMetrics.throughput }} req/min</span>
            </div>
        </div>

        <!-- AI Engine Components -->
        <div class="space-y-3">
            <h4 class="text-sm font-medium text-gray-900">AI Components Status</h4>
            
            <div class="space-y-2">
                <div v-for="component in aiComponents" :key="component.name" class="flex items-center justify-between p-3 bg-panel rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="h-3 w-3 rounded-full" :class="getStatusColor(component.status)"></div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ component.name }}</p>
                            <p class="text-xs text-gray-500">{{ component.description }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-medium" :class="getStatusTextColor(component.status)">
                            {{ component.status.toUpperCase() }}
                        </div>
                        <div class="text-xs text-gray-500">{{ component.load }}% load</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Performance -->
        <div class="border-t pt-4">
            <h4 class="text-sm font-medium text-gray-900 mb-3">Today's Performance</h4>
            <div class="grid grid-cols-2 gap-4 text-center">
                <div>
                    <p class="text-lg font-bold text-gray-900">{{ processingQueue.completed_today }}</p>
                    <p class="text-xs text-gray-500">Analyses Completed</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900">{{ performanceMetrics.average_processing_time }}s</p>
                    <p class="text-xs text-gray-500">Avg Processing Time</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const props = defineProps({
    processingQueue: {
        type: Object,
        required: true
    },
    performanceMetrics: {
        type: Object,
        required: true
    },
    initialComponents: {
        type: Array,
        default: () => []
    }
})

// AI Engine Components Status - will be fetched from API
const aiComponents = ref(props.initialComponents)
const loading = ref(false)
const error = ref(null)

// Fetch AI components status from API
const fetchAIComponents = async () => {
  if (props.initialComponents.length > 0) return
  
  loading.value = true
  error.value = null
  
  try {
    const response = await api.get('/ai/components/status')
    const data = response.data
    
    aiComponents.value = data.components || data.aiComponents || []
  } catch (err) {
    error.value = 'Failed to load AI components status'
    console.error('Error fetching AI components:', err)
    
    // Fallback to basic components list
    aiComponents.value = [
      {
        name: 'Vulnerability Scanner',
        description: 'OWASP security analysis',
        status: 'unknown',
        load: 0
      },
      {
        name: 'Sentiment Analyzer', 
        description: 'Social media processing',
        status: 'unknown',
        load: 0
      }
    ]
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchAIComponents()
})

// Methods
const getStatusColor = (status) => {
    const colors = {
        healthy: 'bg-green-400',
        warning: 'bg-yellow-400',
        error: 'bg-red-400',
        offline: 'bg-gray-400'
    }
    return colors[status] || colors.offline
}

const getStatusTextColor = (status) => {
    const colors = {
        healthy: 'text-green-700',
        warning: 'text-yellow-700',
        error: 'text-red-700',
        offline: 'text-gray-700'
    }
    return colors[status] || colors.offline
}
</script>