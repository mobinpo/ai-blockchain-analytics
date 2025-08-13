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
import { ref, computed } from 'vue'

const refreshing = ref(false)

const networks = ref([
  {
    id: 'ethereum',
    name: 'Ethereum',
    explorer: 'Etherscan.io',
    logo: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjNjI3RUVBIi8+PHBhdGggZD0iTTguNzEyIDkuMTA2IDEyLjgzNyA5LjQ5M2EuNS41IDAgMCAxIC4zOTguNzI0bC0yLjA2NSA0LjEzIDIuMDY1IDQuMTNhLjUuNSAwIDAgMS0uMzk4LjcyNGwtNC4xMjUuMzg3YS41LjUgMCAwIDEtLjU0LS40OTdsLS4zODctNC4xMjVhLjUuNSAwIDAgMSAwLS4wOTlsLjM4Ny00LjEyNWEuNS41IDAgMCAxIC41NC0uNDk3eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=',
    status: 'active',
    responseTime: 120,
    requestsToday: 1247,
    successRate: 99.2
  },
  {
    id: 'polygon',
    name: 'Polygon',
    explorer: 'PolygonScan.com',
    logo: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjODI0N0U1Ii8+PHBhdGggZD0iTTEyLjggOC4yNGE0IDQgMCAwIDEgNi40IDBsNi40IDguOGE0IDQgMCAwIDEtMy4yIDYuNGgtMTIuOGE0IDQgMCAwIDEtMy4yLTYuNGw2LjQtOC44eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=',
    status: 'active',
    responseTime: 95,
    requestsToday: 856,
    successRate: 98.7
  },
  {
    id: 'bsc',
    name: 'BSC',
    explorer: 'BscScan.com',
    logo: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjRjNCQjAwIi8+PHBhdGggZD0iTTE2IDZsNi4xODQgNi4xODQtMi4yNTcgMi4yNTdMMTYgMTAuNTE0bC0zLjkyNyAzLjkyNy0yLjI1Ny0yLjI1N0wxNiA2em02LjE4NCAxMC4xODRMMjQgMTQuNDM3di0yLjI1N2wtMi4yNTcgMi4yNTctMi4yNTctMi4yNTdWMTZsMS44MTYtMS44MTZ6bS0xMi4zNjggMGwyLjI1Ny0yLjI1N1YxNkwxMC4yNTcgMTcuODE2IDggMTZWMTZsMS44MTYtMS44MTZMMTIgMTYuMTg0eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=',
    status: 'active',
    responseTime: 180,
    requestsToday: 634,
    successRate: 97.8
  },
  {
    id: 'arbitrum',
    name: 'Arbitrum',
    explorer: 'Arbiscan.io',
    logo: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjMkQ3NEJCIi8+PHBhdGggZD0iTTE2IDZhMTAgMTAgMCAxIDEgMCAyMEExMCAxMCAwIDAgMSAxNiA2eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=',
    status: 'slow',
    responseTime: 340,
    requestsToday: 423,
    successRate: 96.1
  },
  {
    id: 'optimism',
    name: 'Optimism',
    explorer: 'Optimistic.etherscan.io',
    logo: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxjaXJjbGUgY3g9IjE2IiBjeT0iMTYiIHI9IjE2IiBmaWxsPSIjRkYwNDIwIi8+PHBhdGggZD0iTTE2IDZhMTAgMTAgMCAxIDEgMCAyMEExMCAxMCAwIDAgMSAxNiA2eiIgZmlsbD0iI0ZGRiIvPjwvZz48L3N2Zz4=',
    status: 'maintenance',
    responseTime: 0,
    requestsToday: 0,
    successRate: 0
  }
])

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
  
  // Simulate API call delay
  await new Promise(resolve => setTimeout(resolve, 1000))
  
  // Simulate status updates
  networks.value.forEach(network => {
    if (network.status === 'maintenance' && Math.random() > 0.7) {
      network.status = 'active'
      network.responseTime = Math.floor(Math.random() * 200) + 80
      network.successRate = Math.floor(Math.random() * 3) + 97
    } else if (network.status === 'active' && Math.random() > 0.9) {
      network.status = 'slow'
      network.responseTime += Math.floor(Math.random() * 100) + 50
    }
    
    // Update request counts
    network.requestsToday += Math.floor(Math.random() * 10) + 1
  })
  
  refreshing.value = false
}
</script>