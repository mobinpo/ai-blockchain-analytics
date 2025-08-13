<template>
  <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900">Security Findings Trend</h3>
      <div class="flex space-x-2">
        <button 
          v-for="period in periods" 
          :key="period"
          @click="selectedPeriod = period"
          :class="[
            'px-3 py-1 text-xs font-medium rounded-md transition-colors',
            selectedPeriod === period 
              ? 'bg-indigo-100 text-indigo-700' 
              : 'text-gray-500 hover:text-gray-700'
          ]"
        >
          {{ period }}
        </button>
      </div>
    </div>
    
    <div class="relative h-64">
      <!-- Chart placeholder with demo visualization -->
      <svg class="w-full h-full" viewBox="0 0 400 200">
        <!-- Background grid -->
        <defs>
          <pattern id="grid" width="40" height="20" patternUnits="userSpaceOnUse">
            <path d="M 40 0 L 0 0 0 20" fill="none" stroke="#f3f4f6" stroke-width="1"/>
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#grid)" />
        
        <!-- Critical findings line -->
        <polyline
          :points="criticalPoints"
          fill="none"
          stroke="#dc2626"
          stroke-width="3"
          class="drop-shadow-sm"
        />
        
        <!-- High findings line -->
        <polyline
          :points="highPoints"
          fill="none"
          stroke="#ea580c"
          stroke-width="2"
          class="drop-shadow-sm"
        />
        
        <!-- Medium findings line -->
        <polyline
          :points="mediumPoints"
          fill="none"
          stroke="#d97706"
          stroke-width="2"
          class="drop-shadow-sm"
        />
        
        <!-- Data points -->
        <g v-for="(point, index) in criticalData" :key="`critical-${index}`">
          <circle 
            :cx="point.x" 
            :cy="point.y" 
            r="4" 
            fill="#dc2626"
            class="hover:r-6 transition-all cursor-pointer"
            @mouseover="showTooltip = { x: point.x, y: point.y, value: point.value, type: 'Critical' }"
            @mouseleave="showTooltip = null"
          />
        </g>
      </svg>
      
      <!-- Tooltip -->
      <div 
        v-if="showTooltip"
        :style="{ left: showTooltip.x + 'px', top: (showTooltip.y - 40) + 'px' }"
        class="absolute bg-gray-900 text-white text-xs px-2 py-1 rounded shadow-lg pointer-events-none z-10"
      >
        {{ showTooltip.type }}: {{ showTooltip.value }}
      </div>
    </div>
    
    <!-- Legend -->
    <div class="flex items-center justify-center space-x-6 mt-4">
      <div class="flex items-center">
        <div class="w-3 h-3 bg-red-600 rounded-full mr-2"></div>
        <span class="text-sm text-gray-600">Critical</span>
      </div>
      <div class="flex items-center">
        <div class="w-3 h-3 bg-orange-600 rounded-full mr-2"></div>
        <span class="text-sm text-gray-600">High</span>
      </div>
      <div class="flex items-center">
        <div class="w-3 h-3 bg-yellow-600 rounded-full mr-2"></div>
        <span class="text-sm text-gray-600">Medium</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const selectedPeriod = ref('7D')
const periods = ['24H', '7D', '30D', '90D']
const showTooltip = ref(null)

// Demo data for different severity levels
const criticalData = [
  { x: 50, y: 150, value: 2 },
  { x: 100, y: 120, value: 4 },
  { x: 150, y: 100, value: 6 },
  { x: 200, y: 80, value: 3 },
  { x: 250, y: 90, value: 1 },
  { x: 300, y: 110, value: 2 },
  { x: 350, y: 95, value: 0 }
]

const highData = [
  { x: 50, y: 140, value: 5 },
  { x: 100, y: 110, value: 8 },
  { x: 150, y: 90, value: 12 },
  { x: 200, y: 70, value: 7 },
  { x: 250, y: 80, value: 4 },
  { x: 300, y: 100, value: 6 },
  { x: 350, y: 85, value: 3 }
]

const mediumData = [
  { x: 50, y: 130, value: 8 },
  { x: 100, y: 100, value: 15 },
  { x: 150, y: 80, value: 20 },
  { x: 200, y: 60, value: 12 },
  { x: 250, y: 70, value: 9 },
  { x: 300, y: 90, value: 11 },
  { x: 350, y: 75, value: 7 }
]

const criticalPoints = computed(() => 
  criticalData.map(point => `${point.x},${point.y}`).join(' ')
)

const highPoints = computed(() => 
  highData.map(point => `${point.x},${point.y}`).join(' ')
)

const mediumPoints = computed(() => 
  mediumData.map(point => `${point.x},${point.y}`).join(' ')
)
</script>