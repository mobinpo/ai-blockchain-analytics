<template>
  <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <div class="text-center">
      <h3 class="text-lg font-semibold text-gray-900 mb-2">Community Sentiment</h3>
      <p class="text-sm text-gray-600 mb-4">Real-time sentiment analysis across all projects</p>
      
      <!-- Gauge Chart -->
      <div class="relative w-48 h-24 mx-auto mb-4">
        <svg class="w-full h-full" viewBox="0 0 200 100">
          <!-- Background arc -->
          <path
            d="M 20 80 A 80 80 0 0 1 180 80"
            fill="none"
            stroke="#f3f4f6"
            stroke-width="8"
            stroke-linecap="round"
          />
          
          <!-- Sentiment arc -->
          <path
            :d="sentimentArc"
            fill="none"
            :stroke="sentimentColor"
            stroke-width="8"
            stroke-linecap="round"
            class="transition-all duration-1000 ease-out"
          />
          
          <!-- Needle -->
          <g :transform="`rotate(${needleAngle} 100 80)`">
            <line
              x1="100"
              y1="80"
              x2="100"
              y2="25"
              stroke="#374151"
              stroke-width="2"
              stroke-linecap="round"
            />
            <circle cx="100" cy="80" r="4" fill="#374151" />
          </g>
          
          <!-- Score display -->
          <text x="100" y="95" text-anchor="middle" class="text-xl font-bold fill-gray-900">
            {{ Math.round(sentiment * 100) }}%
          </text>
        </svg>
        
        <!-- Labels -->
        <div class="absolute bottom-0 left-0 text-xs text-gray-500">Negative</div>
        <div class="absolute bottom-0 right-0 text-xs text-gray-500">Positive</div>
      </div>
      
      <!-- Status indicator -->
      <div class="flex items-center justify-center space-x-2 mb-4">
        <div :class="['w-2 h-2 rounded-full', sentimentIndicatorColor]"></div>
        <span :class="['text-sm font-medium', sentimentTextColor]">
          {{ sentimentLabel }}
        </span>
      </div>
      
      <!-- Recent changes -->
      <div class="grid grid-cols-3 gap-4 text-center">
        <div>
          <div :class="['text-lg font-semibold', sentimentChangeColor]">{{ sentimentChange }}</div>
          <div class="text-xs text-gray-500">24h Change</div>
        </div>
        <div>
          <div class="text-lg font-semibold text-blue-600">{{ projectCount }}</div>
          <div class="text-xs text-gray-500">Projects</div>
        </div>
        <div>
          <div class="text-lg font-semibold text-purple-600">{{ analysisCount }}</div>
          <div class="text-xs text-gray-500">Analyses</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  sentiment: {
    type: Number,
    default: 0.72
  },
  projectCount: {
    type: Number,
    default: 47
  },
  analysisCount: {
    type: Number,
    default: 156
  },
  sentimentChange24h: {
    type: Number,
    default: 0.12
  }
})

const needleAngle = computed(() => {
  // Convert sentiment (0-1) to angle (-90 to 90 degrees)
  return (props.sentiment - 0.5) * 180
})

const sentimentArc = computed(() => {
  const startAngle = -90
  const endAngle = needleAngle.value
  const radius = 80
  const centerX = 100
  const centerY = 80
  
  const startRadians = (startAngle * Math.PI) / 180
  const endRadians = (endAngle * Math.PI) / 180
  
  const startX = centerX + radius * Math.cos(startRadians)
  const startY = centerY + radius * Math.sin(startRadians)
  const endX = centerX + radius * Math.cos(endRadians)
  const endY = centerY + radius * Math.sin(endRadians)
  
  const largeArcFlag = endAngle - startAngle > 180 ? 1 : 0
  
  return `M ${startX} ${startY} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`
})

const sentimentColor = computed(() => {
  if (props.sentiment >= 0.7) return '#10b981'  // green
  if (props.sentiment >= 0.5) return '#f59e0b'  // yellow
  return '#ef4444'  // red
})

const sentimentIndicatorColor = computed(() => {
  if (props.sentiment >= 0.7) return 'bg-green-500'
  if (props.sentiment >= 0.5) return 'bg-yellow-500'
  return 'bg-red-500'
})

const sentimentTextColor = computed(() => {
  if (props.sentiment >= 0.7) return 'text-green-600'
  if (props.sentiment >= 0.5) return 'text-yellow-600'
  return 'text-red-600'
})

const sentimentLabel = computed(() => {
  if (props.sentiment >= 0.8) return 'Very Positive'
  if (props.sentiment >= 0.6) return 'Positive'
  if (props.sentiment >= 0.4) return 'Neutral'
  if (props.sentiment >= 0.2) return 'Negative'
  return 'Very Negative'
})

const sentimentChange = computed(() => {
  const change = props.sentimentChange24h * 100
  const sign = change >= 0 ? '+' : ''
  return `${sign}${change.toFixed(0)}%`
})

const sentimentChangeColor = computed(() => {
  if (props.sentimentChange24h > 0) return 'text-green-600'
  if (props.sentimentChange24h < 0) return 'text-red-600'
  return 'text-gray-600'
})
</script>