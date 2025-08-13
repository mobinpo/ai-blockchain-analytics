<template>
  <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900">Risk Assessment Matrix</h3>
      <div class="flex items-center space-x-2">
        <span class="text-sm text-gray-500">Last updated:</span>
        <span class="text-sm font-medium text-gray-900">{{ lastUpdated }}</span>
      </div>
    </div>
    
    <!-- Matrix Grid -->
    <div class="grid grid-cols-6 gap-1 mb-4">
      <!-- Headers -->
      <div class="text-center text-xs font-medium text-gray-600 p-2">Impact</div>
      <div class="text-center text-xs font-medium text-gray-600 p-2">Very Low</div>
      <div class="text-center text-xs font-medium text-gray-600 p-2">Low</div>
      <div class="text-center text-xs font-medium text-gray-600 p-2">Medium</div>
      <div class="text-center text-xs font-medium text-gray-600 p-2">High</div>
      <div class="text-center text-xs font-medium text-gray-600 p-2">Very High</div>
      
      <!-- Very High row -->
      <div class="text-center text-xs font-medium text-gray-600 p-2">Very High</div>
      <div 
        v-for="(cell, index) in riskMatrix[4]" 
        :key="`vh-${index}`"
        :class="[
          'p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105',
          getRiskCellClass(4, index),
          cell.count > 0 ? 'shadow-sm' : ''
        ]"
        @click="showRiskDetails(4, index, cell)"
      >
        {{ cell.count }}
      </div>
      
      <!-- High row -->
      <div class="text-center text-xs font-medium text-gray-600 p-2">High</div>
      <div 
        v-for="(cell, index) in riskMatrix[3]" 
        :key="`h-${index}`"
        :class="[
          'p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105',
          getRiskCellClass(3, index),
          cell.count > 0 ? 'shadow-sm' : ''
        ]"
        @click="showRiskDetails(3, index, cell)"
      >
        {{ cell.count }}
      </div>
      
      <!-- Medium row -->
      <div class="text-center text-xs font-medium text-gray-600 p-2">Medium</div>
      <div 
        v-for="(cell, index) in riskMatrix[2]" 
        :key="`m-${index}`"
        :class="[
          'p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105',
          getRiskCellClass(2, index),
          cell.count > 0 ? 'shadow-sm' : ''
        ]"
        @click="showRiskDetails(2, index, cell)"
      >
        {{ cell.count }}
      </div>
      
      <!-- Low row -->
      <div class="text-center text-xs font-medium text-gray-600 p-2">Low</div>
      <div 
        v-for="(cell, index) in riskMatrix[1]" 
        :key="`l-${index}`"
        :class="[
          'p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105',
          getRiskCellClass(1, index),
          cell.count > 0 ? 'shadow-sm' : ''
        ]"
        @click="showRiskDetails(1, index, cell)"
      >
        {{ cell.count }}
      </div>
      
      <!-- Very Low row -->
      <div class="text-center text-xs font-medium text-gray-600 p-2">Very Low</div>
      <div 
        v-for="(cell, index) in riskMatrix[0]" 
        :key="`vl-${index}`"
        :class="[
          'p-2 rounded border text-center text-xs font-medium cursor-pointer transition-all hover:scale-105',
          getRiskCellClass(0, index),
          cell.count > 0 ? 'shadow-sm' : ''
        ]"
        @click="showRiskDetails(0, index, cell)"
      >
        {{ cell.count }}
      </div>
    </div>
    
    <!-- Probability labels (bottom) -->
    <div class="grid grid-cols-6 gap-1 mb-4">
      <div></div>
      <div class="text-center text-xs text-gray-600 font-medium">Probability</div>
    </div>
    
    <!-- Risk Summary -->
    <div class="grid grid-cols-4 gap-4 pt-4 border-t border-gray-200">
      <div class="text-center">
        <div class="text-lg font-semibold text-red-600">{{ criticalCount }}</div>
        <div class="text-xs text-gray-500">Critical</div>
      </div>
      <div class="text-center">
        <div class="text-lg font-semibold text-orange-600">{{ highCount }}</div>
        <div class="text-xs text-gray-500">High</div>
      </div>
      <div class="text-center">
        <div class="text-lg font-semibold text-yellow-600">{{ mediumCount }}</div>
        <div class="text-xs text-gray-500">Medium</div>
      </div>
      <div class="text-center">
        <div class="text-lg font-semibold text-green-600">{{ lowCount }}</div>
        <div class="text-xs text-gray-500">Low</div>
      </div>
    </div>
    
    <!-- Modal for risk details -->
    <div v-if="selectedRisk" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="selectedRisk = null">
      <div class="bg-white rounded-lg p-6 max-w-md mx-4" @click.stop>
        <h4 class="text-lg font-semibold mb-2">Risk Details</h4>
        <p class="text-sm text-gray-600 mb-4">
          <strong>Impact:</strong> {{ getImpactLabel(selectedRisk.impact) }}<br>
          <strong>Probability:</strong> {{ getProbabilityLabel(selectedRisk.probability) }}<br>
          <strong>Findings:</strong> {{ selectedRisk.count }}
        </p>
        <div class="space-y-2">
          <div v-for="finding in selectedRisk.examples" :key="finding" class="text-sm text-gray-700 bg-panel p-2 rounded">
            {{ finding }}
          </div>
        </div>
        <button @click="selectedRisk = null" class="mt-4 w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 transition-colors">
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const selectedRisk = ref(null)
const lastUpdated = ref('2 min ago')

// Demo risk matrix data (impact vs probability)
const riskMatrix = ref([
  // Very Low Impact
  [
    { count: 15, examples: ['Code style warnings', 'Documentation gaps'] },
    { count: 8, examples: ['Minor optimization opportunities'] },
    { count: 3, examples: ['Low-impact performance issues'] },
    { count: 1, examples: ['Rare edge case handling'] },
    { count: 0, examples: [] }
  ],
  // Low Impact
  [
    { count: 12, examples: ['Input validation suggestions'] },
    { count: 18, examples: ['Gas optimization hints'] },
    { count: 7, examples: ['Event emission improvements'] },
    { count: 2, examples: ['Minor access control issues'] },
    { count: 1, examples: ['Low-impact logic flaws'] }
  ],
  // Medium Impact
  [
    { count: 5, examples: ['Moderate gas inefficiencies'] },
    { count: 9, examples: ['State variable optimizations'] },
    { count: 12, examples: ['Function visibility issues'] },
    { count: 6, examples: ['Moderate security concerns'] },
    { count: 3, examples: ['Medium-risk vulnerabilities'] }
  ],
  // High Impact
  [
    { count: 2, examples: ['Potential front-running'] },
    { count: 4, examples: ['Access control weaknesses'] },
    { count: 8, examples: ['State manipulation risks'] },
    { count: 11, examples: ['High-value vulnerabilities'] },
    { count: 7, examples: ['Critical security flaws'] }
  ],
  // Very High Impact
  [
    { count: 0, examples: [] },
    { count: 1, examples: ['Potential fund locks'] },
    { count: 2, examples: ['Reentrancy vulnerabilities'] },
    { count: 5, examples: ['Critical exploit vectors'] },
    { count: 3, examples: ['Severe security breaches'] }
  ]
])

const getRiskCellClass = (impact, probability) => {
  const riskLevel = impact + probability
  
  if (riskLevel >= 7) return 'bg-red-100 border-red-300 text-red-800'
  if (riskLevel >= 5) return 'bg-orange-100 border-orange-300 text-orange-800'
  if (riskLevel >= 3) return 'bg-yellow-100 border-yellow-300 text-yellow-800'
  return 'bg-green-100 border-green-300 text-green-800'
}

const getImpactLabel = (level) => {
  const labels = ['Very Low', 'Low', 'Medium', 'High', 'Very High']
  return labels[level] || 'Unknown'
}

const getProbabilityLabel = (level) => {
  const labels = ['Very Low', 'Low', 'Medium', 'High', 'Very High']
  return labels[level] || 'Unknown'
}

const showRiskDetails = (impact, probability, cell) => {
  if (cell.count > 0) {
    selectedRisk.value = {
      impact,
      probability,
      count: cell.count,
      examples: cell.examples.slice(0, 3) // Show max 3 examples
    }
  }
}

const criticalCount = computed(() => {
  return riskMatrix.value.reduce((total, row, impact) => {
    return total + row.reduce((rowTotal, cell, prob) => {
      return (impact + prob >= 7) ? rowTotal + cell.count : rowTotal
    }, 0)
  }, 0)
})

const highCount = computed(() => {
  return riskMatrix.value.reduce((total, row, impact) => {
    return total + row.reduce((rowTotal, cell, prob) => {
      const riskLevel = impact + prob
      return (riskLevel >= 5 && riskLevel < 7) ? rowTotal + cell.count : rowTotal
    }, 0)
  }, 0)
})

const mediumCount = computed(() => {
  return riskMatrix.value.reduce((total, row, impact) => {
    return total + row.reduce((rowTotal, cell, prob) => {
      const riskLevel = impact + prob
      return (riskLevel >= 3 && riskLevel < 5) ? rowTotal + cell.count : rowTotal
    }, 0)
  }, 0)
})

const lowCount = computed(() => {
  return riskMatrix.value.reduce((total, row, impact) => {
    return total + row.reduce((rowTotal, cell, prob) => {
      const riskLevel = impact + prob
      return (riskLevel < 3) ? rowTotal + cell.count : rowTotal
    }, 0)
  }, 0)
})
</script>