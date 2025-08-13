<template>
  <div 
    v-if="isVerified" 
    class="verification-badge verified"
    :class="{ 
      'show-tooltip': showTooltip,
      'compact': variant === 'compact',
      'inline': variant === 'inline'
    }"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <!-- Badge Icon -->
    <svg 
      class="badge-icon" 
      width="16" 
      height="16" 
      viewBox="0 0 16 16" 
      fill="none"
    >
      <path 
        d="M8 0L10.1 3.1L14 2.1L13 6L16 8L13 10L14 13.9L10.1 12.9L8 16L5.9 12.9L2 13.9L3 10L0 8L3 6L2 2.1L5.9 3.1L8 0Z" 
        fill="#10B981"
      />
      <path 
        d="M5 8L7 10L11 6" 
        stroke="white" 
        stroke-width="1.5" 
        stroke-linecap="round" 
        stroke-linejoin="round"
      />
    </svg>
    
    <!-- Badge Text -->
    <span 
      v-if="variant !== 'icon'" 
      class="badge-text"
    >
      {{ badgeText }}
    </span>
    
    <!-- Tooltip -->
    <div 
      v-if="showTooltip && tooltipVisible" 
      class="badge-tooltip"
      :class="tooltipPosition"
    >
      <div class="tooltip-content">
        <strong>{{ tooltipTitle }}</strong>
        <div class="tooltip-details">
          <div v-if="contractAddress">
            <span class="label">Address:</span>
            <span class="value">{{ truncatedAddress }}</span>
          </div>
          <div v-if="verifiedAt">
            <span class="label">Verified:</span>
            <span class="value">{{ formattedDate }}</span>
          </div>
          <div v-if="verificationMethod">
            <span class="label">Method:</span>
            <span class="value">{{ verificationMethod }}</span>
          </div>
        </div>
      </div>
      <div class="tooltip-arrow"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'

interface Props {
  contractAddress: string
  variant?: 'default' | 'compact' | 'inline' | 'icon'
  showTooltip?: boolean
  customText?: string
  tooltipPosition?: 'top' | 'bottom' | 'left' | 'right'
  autoVerify?: boolean
}

interface VerificationStatus {
  is_verified: boolean
  contract_address: string
  verified_at?: string
  verification_method?: string
  metadata?: Record<string, any>
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  showTooltip: true,
  tooltipPosition: 'top',
  autoVerify: true
})

const emit = defineEmits<{
  verified: [status: VerificationStatus]
  error: [error: string]
}>()

// Reactive data
const isVerified = ref(false)
const verificationStatus = ref<VerificationStatus | null>(null)
const tooltipVisible = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)

// Computed properties
const badgeText = computed(() => {
  if (props.customText) return props.customText
  return 'Verified'
})

const tooltipTitle = computed(() => {
  return verificationStatus.value?.metadata?.project_name || 'Contract Verified'
})

const truncatedAddress = computed(() => {
  if (!props.contractAddress) return ''
  const addr = props.contractAddress
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
})

const formattedDate = computed(() => {
  if (!verificationStatus.value?.verified_at) return ''
  return new Date(verificationStatus.value.verified_at).toLocaleDateString()
})

const verificationMethod = computed(() => {
  return verificationStatus.value?.verification_method || 'Signed URL'
})

// Methods
async function checkVerificationStatus() {
  if (!props.contractAddress || loading.value) return

  loading.value = true
  error.value = null

  try {
    const response = await fetch(`/api/verification/status?contract_address=${encodeURIComponent(props.contractAddress)}`)
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }

    const data: VerificationStatus = await response.json()
    
    verificationStatus.value = data
    isVerified.value = data.is_verified

    if (data.is_verified) {
      emit('verified', data)
    }

  } catch (err) {
    const errorMessage = err instanceof Error ? err.message : 'Failed to check verification status'
    error.value = errorMessage
    emit('error', errorMessage)
    console.error('Verification check failed:', err)
  } finally {
    loading.value = false
  }
}

function handleMouseEnter() {
  if (props.showTooltip) {
    tooltipVisible.value = true
  }
}

function handleMouseLeave() {
  tooltipVisible.value = false
}

function copyAddress() {
  if (props.contractAddress && navigator.clipboard) {
    navigator.clipboard.writeText(props.contractAddress).then(() => {
      // Could emit a 'copied' event here
    }).catch(console.error)
  }
}

// Lifecycle
onMounted(() => {
  if (props.autoVerify && props.contractAddress) {
    checkVerificationStatus()
  }
})

// Expose methods for parent component
defineExpose({
  checkVerificationStatus,
  copyAddress,
  verificationStatus: computed(() => verificationStatus.value),
  isVerified: computed(() => isVerified.value),
  loading: computed(() => loading.value),
  error: computed(() => error.value)
})
</script>

<style scoped>
.verification-badge {
  @apply inline-flex items-center gap-1 px-2 py-1 rounded-xl text-xs font-medium relative cursor-help;
  transition: all 0.2s ease;
}

.verification-badge.verified {
  @apply bg-green-50 text-green-700 border border-green-200;
}

.verification-badge.verified:hover {
  @apply bg-green-100 border-green-300;
}

.verification-badge.compact {
  @apply px-1.5 py-0.5 text-xs;
}

.verification-badge.inline {
  @apply px-1 py-0.5 rounded-md;
}

.badge-icon {
  @apply flex-shrink-0;
}

.badge-text {
  @apply select-none;
}

.badge-tooltip {
  @apply absolute z-50 px-3 py-2 bg-panel text-white text-xs rounded-lg shadow-lg;
  @apply opacity-0 pointer-events-none transition-opacity duration-200;
  min-width: 200px;
}

.verification-badge.show-tooltip:hover .badge-tooltip {
  @apply opacity-100;
}

.badge-tooltip.top {
  @apply bottom-full left-1/2 transform -translate-x-1/2 mb-2;
}

.badge-tooltip.bottom {
  @apply top-full left-1/2 transform -translate-x-1/2 mt-2;
}

.badge-tooltip.left {
  @apply right-full top-1/2 transform -translate-y-1/2 mr-2;
}

.badge-tooltip.right {
  @apply left-full top-1/2 transform -translate-y-1/2 ml-2;
}

.tooltip-content {
  @apply text-left;
}

.tooltip-content strong {
  @apply block text-white font-medium mb-1;
}

.tooltip-details {
  @apply space-y-1;
}

.tooltip-details > div {
  @apply flex justify-between items-center;
}

.tooltip-details .label {
  @apply text-gray-300 mr-2;
}

.tooltip-details .value {
  @apply text-white font-mono text-xs;
}

.tooltip-arrow {
  @apply absolute w-2 h-2 bg-panel transform rotate-45;
}

.badge-tooltip.top .tooltip-arrow {
  @apply top-full left-1/2 transform -translate-x-1/2 -translate-y-1/2;
}

.badge-tooltip.bottom .tooltip-arrow {
  @apply bottom-full left-1/2 transform -translate-x-1/2 translate-y-1/2;
}

.badge-tooltip.left .tooltip-arrow {
  @apply left-full top-1/2 transform -translate-y-1/2 -translate-x-1/2;
}

.badge-tooltip.right .tooltip-arrow {
  @apply right-full top-1/2 transform -translate-y-1/2 translate-x-1/2;
}

/* Animation variants */
@keyframes pulse-green {
  0%, 100% { @apply bg-green-50; }
  50% { @apply bg-green-100; }
}

.verification-badge.verified.pulse {
  animation: pulse-green 2s infinite;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .verification-badge.verified {
    @apply bg-green-900/20 text-green-400 border-green-800;
  }
  
  .verification-badge.verified:hover {
    @apply bg-green-900/30 border-green-700;
  }
  
  .badge-tooltip {
    @apply bg-gray-700 border border-gray-600;
  }
  
  .tooltip-arrow {
    @apply bg-gray-700;
  }
}
</style>