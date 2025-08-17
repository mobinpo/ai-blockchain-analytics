<template>
  <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900">Network Status</h3>
      <button 
        @click="refreshStatus"
        :disabled="refreshing"
        class="text-sm text-brand-500 hover:text-indigo-700 font-medium disabled:opacity-50"
      >
        {{ refreshing ? 'Refreshing...' : 'Refresh' }}
      </button>
    </div>
    
    <div class="space-y-4">
      <div 
        v-for="network in networks" 
        :key="network.id"
        class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-panel transition-colors"
      >
        <div class="flex items-center space-x-3">
          <div class="relative">
            <img :src="network.logo" :alt="network.name" class="w-8 h-8 rounded-full" />
            <div 
              :class="[
                'absolute -bottom-1 -right-1 w-3 h-3 rounded-full border-2 border-white',
                getStatusColor(network.status)
              ]"
            ></div>
          </div>
          <div>
            <h4 class="font-medium text-gray-900">{{ network.name }}</h4>
            <p class="text-sm text-gray-500">{{ network.explorer }}</p>
          </div>
        </div>
        
        <div class="text-right">
          <div class="flex items-center space-x-4">
            <div class="text-center">
              <div class="text-sm font-medium text-gray-900">{{ network.responseTime }}ms</div>
              <div class="text-xs text-gray-500">Response</div>
            </div>
            <div class="text-center">
              <div class="text-sm font-medium text-gray-900">{{ network.requestsToday.toLocaleString() }}</div>
              <div class="text-xs text-gray-500">Requests</div>
            </div>
            <div class="text-center">
              <div :class="['text-sm font-medium', getStatusTextColor(network.status)]">
                {{ getStatusText(network.status) }}
              </div>
              <div class="text-xs text-gray-500">Status</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- API Usage Summary -->
    <div class="mt-6 pt-6 border-t border-gray-200">
      <h4 class="text-sm font-semibold text-gray-900 mb-3">API Usage Summary</h4>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center">
          <div class="text-lg font-semibold text-blue-600">{{ totalRequests.toLocaleString() }}</div>
          <div class="text-xs text-gray-500">Total Requests</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-semibold text-green-600">{{ successRate }}%</div>
          <div class="text-xs text-gray-500">Success Rate</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-semibold text-yellow-600">{{ avgResponseTime }}ms</div>
          <div class="text-xs text-gray-500">Avg Response</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-semibold text-purple-600">{{ rateLimitStatus }}%</div>
          <div class="text-xs text-gray-500">Rate Limit</div>
        </div>
      </div>
    </div>
    
    <!-- Rate Limit Visualization -->
    <div class="mt-4">
      <div class="flex items-center justify-between text-sm mb-2">
        <span class="text-gray-600">Rate Limit Usage</span>
        <span class="text-gray-900 font-medium">{{ rateLimitStatus }}% of daily limit</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div 
          :class="[
            'h-2 rounded-full transition-all duration-500',
            rateLimitStatus < 70 ? 'bg-green-500' : 
            rateLimitStatus < 90 ? 'bg-yellow-500' : 'bg-red-500'
          ]"
          :style="{ width: rateLimitStatus + '%' }"
        ></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  initialNetworks: {
    type: Array,
    default: () => []
  }
});

const refreshing = ref(false)
const networks = ref(props.initialNetworks)

// Fetch network status from API if not provided via props
const fetchNetworkStatus = async () => {
  if (props.initialNetworks.length > 0) return;
  
  try {
    const response = await fetch('/api/network/status', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        networks.value = data.networks || [];
      } else {
        console.error('Failed to fetch network status:', data.error);
      }
    } else {
      console.error('HTTP error:', response.status, response.statusText);
    }
  } catch (error) {
    console.error('Error fetching network status:', error);
    networks.value = [];
  }
};

const getStatusColor = (status) => {
  switch (status) {
    case 'active': return 'bg-green-400'
    case 'slow': return 'bg-yellow-400'
    case 'maintenance': return 'bg-red-400'
    case 'offline': return 'bg-gray-400'
    default: return 'bg-gray-400'
  }
}

const getStatusTextColor = (status) => {
  switch (status) {
    case 'active': return 'text-green-600'
    case 'slow': return 'text-yellow-600'
    case 'maintenance': return 'text-red-600'
    case 'offline': return 'text-gray-600'
    default: return 'text-gray-600'
  }
}

const getStatusText = (status) => {
  switch (status) {
    case 'active': return 'Active'
    case 'slow': return 'Slow'
    case 'maintenance': return 'Maintenance'
    case 'offline': return 'Offline'
    default: return 'Unknown'
  }
}

const totalRequests = computed(() => {
  return networks.value.reduce((total, network) => total + network.requestsToday, 0)
})

const successRate = computed(() => {
  const activeNetworks = networks.value.filter(n => n.status === 'active' || n.status === 'slow')
  if (activeNetworks.length === 0) return 0
  
  const avgRate = activeNetworks.reduce((sum, network) => sum + network.successRate, 0) / activeNetworks.length
  return Math.round(avgRate * 10) / 10
})

const avgResponseTime = computed(() => {
  const activeNetworks = networks.value.filter(n => n.status === 'active' || n.status === 'slow')
  if (activeNetworks.length === 0) return 0
  
  const avgTime = activeNetworks.reduce((sum, network) => sum + network.responseTime, 0) / activeNetworks.length
  return Math.round(avgTime)
})

const rateLimitStatus = computed(() => {
  // Simulate rate limit calculation based on requests
  const dailyLimit = 5000
  const usagePercentage = (totalRequests.value / dailyLimit) * 100
  return Math.min(Math.round(usagePercentage), 100)
})

const refreshStatus = async () => {
  refreshing.value = true
  
  try {
    const response = await fetch('/api/network/status?refresh=true', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        networks.value = data.networks || [];
      } else {
        console.error('Failed to refresh network status:', data.error);
      }
    } else {
      console.error('HTTP error:', response.status, response.statusText);
    }
  } catch (error) {
    console.error('Error refreshing network status:', error);
  }
  
  refreshing.value = false
}

// Initialize data on component mount
onMounted(() => {
  fetchNetworkStatus();
});
</script>