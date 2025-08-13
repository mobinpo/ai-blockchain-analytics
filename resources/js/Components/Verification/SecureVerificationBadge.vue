<template>
    <div class="secure-verification-badge-wrapper">
        <!-- Main Badge -->
        <div
            :class="[
                'secure-verification-badge',
                `badge-${variant}`,
                `badge-${size}`,
                {
                    'verified': isVerified,
                    'loading': isLoading,
                    'error': hasError,
                    'clickable': clickable && !disableClick,
                    'with-animation': enableAnimation
                }
            ]"
            :style="customStyles"
            @click="handleClick"
            @mouseenter="handleMouseEnter"
            @mouseleave="handleMouseLeave"
        >
            <!-- Loading State -->
            <div v-if="isLoading" class="badge-loading">
                <svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle>
                    <path fill="currentColor" class="opacity-75" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span v-if="variant !== 'icon'">{{ loadingText }}</span>
            </div>

            <!-- Error State -->
            <div v-else-if="hasError" class="badge-error">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span v-if="variant !== 'icon'">{{ errorText }}</span>
            </div>

            <!-- Verified State -->
            <div v-else-if="isVerified" class="badge-verified">
                <!-- Enhanced Security Icon with SHA-256 + HMAC Indicator -->
                <div class="verification-icon-container">
                    <svg class="verification-icon" :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none">
                        <!-- Shield Background -->
                        <path d="M12 2L3 7V12C3 16.55 6.84 20.74 9.91 21.74C11.04 22.13 12.96 22.13 14.09 21.74C17.16 20.74 21 16.55 21 12V7L12 2Z" fill="currentColor" class="shield-bg"/>
                        <!-- Checkmark -->
                        <path d="M9 12L11 14L15 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <!-- Security Level Indicator -->
                        <circle v-if="showSecurityLevel" cx="18" cy="6" r="3" fill="#10B981" class="security-indicator"/>
                        <text v-if="showSecurityLevel" x="18" y="8" text-anchor="middle" fill="white" font-size="8" font-weight="bold">✓</text>
                    </svg>
                    
                    <!-- Cryptographic Signature Indicator -->
                    <div v-if="showCryptoIndicator" class="crypto-indicator" title="SHA-256 + HMAC Protected">
                        <svg width="8" height="8" viewBox="0 0 12 12" fill="none">
                            <path d="M6 1L2 3V6C2 8.21 3.79 10.21 4.95 10.71C5.62 11.03 6.38 11.03 7.05 10.71C8.21 10.21 10 8.21 10 6V3L6 1Z" fill="#059669"/>
                        </svg>
                    </div>
                </div>

                <!-- Badge Text -->
                <span v-if="variant !== 'icon'" class="badge-text">
                    {{ verifiedText || customText || 'Verified' }}
                    <span v-if="showMethod && verificationData?.verification_method" class="verification-method">
                        ({{ formatVerificationMethod(verificationData.verification_method) }})
                    </span>
                </span>

                <!-- Security Level Badge -->
                <div v-if="showSecurityBadge && securityLevel" class="security-level-badge" :class="`security-${securityLevel}`">
                    {{ securityLevel.toUpperCase() }}
                </div>
            </div>

            <!-- Unverified State -->
            <div v-else class="badge-unverified">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" class="text-gray-400">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
                <span v-if="variant !== 'icon'" class="badge-text">{{ unverifiedText }}</span>
            </div>
        </div>

        <!-- Enhanced Tooltip with Security Details -->
        <div
            v-if="showTooltip && (isVerified || tooltipAlwaysShow)"
            :class="[
                'verification-tooltip',
                `tooltip-${tooltipPosition}`,
                { 'tooltip-visible': showTooltipContent }
            ]"
        >
            <div class="tooltip-content">
                <!-- Header -->
                <div class="tooltip-header">
                    <div class="flex items-center gap-2">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" class="text-green-500">
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-semibold text-gray-900">Contract Verified</span>
                    </div>
                    <div v-if="verificationData?.verified_at" class="text-xs text-gray-500">
                        {{ formatDate(verificationData.verified_at) }}
                    </div>
                </div>

                <!-- Contract Information -->
                <div class="tooltip-section">
                    <div class="tooltip-label">Contract Address</div>
                    <div class="tooltip-value font-mono text-xs">{{ contractAddress }}</div>
                </div>

                <!-- Project Information -->
                <div v-if="verificationData?.metadata?.project_name" class="tooltip-section">
                    <div class="tooltip-label">Project</div>
                    <div class="tooltip-value">{{ verificationData.metadata.project_name }}</div>
                </div>

                <!-- Security Features -->
                <div class="tooltip-section">
                    <div class="tooltip-label">Security Features</div>
                    <div class="security-features">
                        <div class="security-feature">
                            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" class="text-green-500">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>SHA-256 + HMAC Signature</span>
                        </div>
                        <div class="security-feature">
                            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" class="text-green-500">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>Anti-Spoofing Protection</span>
                        </div>
                        <div class="security-feature">
                            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" class="text-green-500">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>Time-based Validation</span>
                        </div>
                        <div class="security-feature">
                            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" class="text-green-500">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>Replay Attack Prevention</span>
                        </div>
                    </div>
                </div>

                <!-- Verification Details -->
                <div v-if="verificationData" class="tooltip-section">
                    <div class="tooltip-label">Verification Method</div>
                    <div class="tooltip-value">{{ formatVerificationMethod(verificationData.verification_method) }}</div>
                </div>

                <!-- Website Link -->
                <div v-if="verificationData?.metadata?.website" class="tooltip-section">
                    <a 
                        :href="verificationData.metadata.website" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1"
                        @click.stop
                    >
                        Visit Website
                        <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                            <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Tooltip Arrow -->
            <div class="tooltip-arrow"></div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'

// Props
const props = defineProps({
    contractAddress: {
        type: String,
        required: true,
        validator: (value) => /^0x[a-fA-F0-9]{40}$/.test(value)
    },
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'compact', 'icon', 'detailed'].includes(value)
    },
    size: {
        type: String,
        default: 'medium',
        validator: (value) => ['small', 'medium', 'large'].includes(value)
    },
    autoVerify: {
        type: Boolean,
        default: true
    },
    showTooltip: {
        type: Boolean,
        default: true
    },
    tooltipPosition: {
        type: String,
        default: 'top',
        validator: (value) => ['top', 'bottom', 'left', 'right'].includes(value)
    },
    tooltipAlwaysShow: {
        type: Boolean,
        default: false
    },
    clickable: {
        type: Boolean,
        default: false
    },
    disableClick: {
        type: Boolean,
        default: false
    },
    customText: {
        type: String,
        default: ''
    },
    verifiedText: {
        type: String,
        default: 'Verified'
    },
    unverifiedText: {
        type: String,
        default: 'Unverified'
    },
    loadingText: {
        type: String,
        default: 'Verifying...'
    },
    errorText: {
        type: String,
        default: 'Error'
    },
    showSecurityLevel: {
        type: Boolean,
        default: true
    },
    showSecurityBadge: {
        type: Boolean,
        default: false
    },
    showCryptoIndicator: {
        type: Boolean,
        default: true
    },
    showMethod: {
        type: Boolean,
        default: false
    },
    enableAnimation: {
        type: Boolean,
        default: true
    },
    customStyles: {
        type: Object,
        default: () => ({})
    },
    refreshInterval: {
        type: Number,
        default: 0 // 0 = no auto-refresh
    }
})

// Emits
const emit = defineEmits([
    'click',
    'verified',
    'unverified',
    'error',
    'loading-start',
    'loading-end'
])

// Reactive state
const isLoading = ref(false)
const hasError = ref(false)
const verificationData = ref(null)
const showTooltipContent = ref(false)
const refreshTimer = ref(null)

// Computed
const isVerified = computed(() => {
    return !isLoading.value && !hasError.value && verificationData.value?.is_verified === true
})

const securityLevel = computed(() => {
    if (!isVerified.value) return null
    
    const method = verificationData.value?.verification_method
    if (method === 'signed_url' || method === 'enhanced_signed_url') {
        return 'high'
    } else if (method === 'basic') {
        return 'medium'
    }
    return 'standard'
})

const iconSize = computed(() => {
    const sizes = {
        small: 14,
        medium: 16,
        large: 20
    }
    return sizes[props.size] || 16
})

// Methods
const checkVerificationStatus = async () => {
    if (!props.contractAddress) return

    isLoading.value = true
    hasError.value = false
    emit('loading-start')

    try {
        const response = await axios.get(`/enhanced-verification/status/${props.contractAddress}`)
        
        if (response.data.success) {
            verificationData.value = response.data.data
            
            if (verificationData.value.is_verified) {
                emit('verified', verificationData.value)
            } else {
                emit('unverified', verificationData.value)
            }
        } else {
            throw new Error(response.data.error || 'Failed to check verification status')
        }
    } catch (error) {
        console.error('Failed to check verification status:', error)
        hasError.value = true
        emit('error', error.message || error)
    } finally {
        isLoading.value = false
        emit('loading-end')
    }
}

const handleClick = () => {
    if (props.disableClick) return
    
    emit('click', {
        contractAddress: props.contractAddress,
        isVerified: isVerified.value,
        verificationData: verificationData.value
    })
}

const handleMouseEnter = () => {
    if (props.showTooltip) {
        showTooltipContent.value = true
    }
}

const handleMouseLeave = () => {
    showTooltipContent.value = false
}

const formatVerificationMethod = (method) => {
    const methods = {
        'signed_url': 'Signed URL',
        'enhanced_signed_url': 'Enhanced Signed URL',
        'basic': 'Basic Verification',
        'manual': 'Manual Verification'
    }
    return methods[method] || method
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const startRefreshTimer = () => {
    if (props.refreshInterval > 0) {
        refreshTimer.value = setInterval(() => {
            checkVerificationStatus()
        }, props.refreshInterval * 1000)
    }
}

const stopRefreshTimer = () => {
    if (refreshTimer.value) {
        clearInterval(refreshTimer.value)
        refreshTimer.value = null
    }
}

// Watchers
watch(() => props.contractAddress, () => {
    if (props.autoVerify) {
        checkVerificationStatus()
    }
}, { immediate: true })

watch(() => props.refreshInterval, () => {
    stopRefreshTimer()
    startRefreshTimer()
})

// Lifecycle
onMounted(() => {
    if (props.autoVerify) {
        checkVerificationStatus()
    }
    startRefreshTimer()
})

onUnmounted(() => {
    stopRefreshTimer()
})

// Expose methods for manual control
defineExpose({
    checkVerificationStatus,
    refresh: checkVerificationStatus
})
</script>

<style scoped>
/* Base Badge Styles */
.secure-verification-badge {
    @apply inline-flex items-center gap-2 transition-all duration-200 ease-in-out;
    position: relative;
}

/* Badge Variants */
.badge-default {
    @apply px-3 py-1.5 rounded-full border;
}

.badge-compact {
    @apply px-2 py-1 rounded-md border text-sm;
}

.badge-icon {
    @apply p-1 rounded-full;
}

.badge-detailed {
    @apply px-4 py-2 rounded-lg border;
}

/* Badge Sizes */
.badge-small {
    @apply text-xs;
}

.badge-medium {
    @apply text-sm;
}

.badge-large {
    @apply text-base;
}

/* Badge States */
.verified {
    @apply bg-green-50 border-green-200 text-green-800;
}

.verified.clickable:hover {
    @apply bg-green-100 border-green-300 transform scale-105 shadow-md;
}

.loading {
    @apply bg-blue-50 border-blue-200 text-blue-700;
}

.error {
    @apply bg-red-50 border-red-200 text-red-700;
}

.badge-unverified {
    @apply bg-panel border-gray-200 text-gray-600;
}

/* Animation */
.with-animation {
    @apply transition-all duration-300 ease-in-out;
}

.with-animation.verified {
    animation: verifiedPulse 0.6s ease-in-out;
}

@keyframes verifiedPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2); }
    100% { transform: scale(1); }
}

/* Verification Icon Container */
.verification-icon-container {
    @apply relative inline-flex items-center;
}

.verification-icon {
    @apply text-green-600;
}

.crypto-indicator {
    @apply absolute -top-1 -right-1 bg-white rounded-full p-0.5 shadow-sm;
}

.security-level-badge {
    @apply text-xs px-1.5 py-0.5 rounded font-medium ml-1;
}

.security-high {
    @apply bg-green-100 text-green-800;
}

.security-medium {
    @apply bg-yellow-100 text-yellow-800;
}

.security-standard {
    @apply bg-blue-100 text-blue-800;
}

/* Tooltip Styles */
.verification-tooltip {
    @apply absolute z-50 bg-white border border-gray-200 rounded-lg shadow-lg p-4 min-w-64 max-w-80;
    @apply opacity-0 invisible transition-all duration-200 ease-in-out;
    @apply pointer-events-none;
}

.tooltip-visible {
    @apply opacity-100 visible pointer-events-auto;
}

.tooltip-top {
    @apply bottom-full left-1/2 transform -translate-x-1/2 mb-2;
}

.tooltip-bottom {
    @apply top-full left-1/2 transform -translate-x-1/2 mt-2;
}

.tooltip-left {
    @apply right-full top-1/2 transform -translate-y-1/2 mr-2;
}

.tooltip-right {
    @apply left-full top-1/2 transform -translate-y-1/2 ml-2;
}

.tooltip-content {
    @apply space-y-3;
}

.tooltip-header {
    @apply flex items-center justify-between border-b border-gray-100 pb-2;
}

.tooltip-section {
    @apply space-y-1;
}

.tooltip-label {
    @apply text-xs font-medium text-gray-500 uppercase tracking-wide;
}

.tooltip-value {
    @apply text-sm text-gray-900;
}

.security-features {
    @apply space-y-1;
}

.security-feature {
    @apply flex items-center gap-2 text-xs text-gray-700;
}

/* Tooltip Arrow */
.tooltip-arrow {
    @apply absolute w-2 h-2 bg-white border transform rotate-45;
}

.tooltip-top .tooltip-arrow {
    @apply top-full left-1/2 -translate-x-1/2 -mt-1 border-r border-b border-gray-200;
}

.tooltip-bottom .tooltip-arrow {
    @apply bottom-full left-1/2 -translate-x-1/2 -mb-1 border-l border-t border-gray-200;
}

.tooltip-left .tooltip-arrow {
    @apply left-full top-1/2 -translate-y-1/2 -ml-1 border-t border-r border-gray-200;
}

.tooltip-right .tooltip-arrow {
    @apply right-full top-1/2 -translate-y-1/2 -mr-1 border-b border-l border-gray-200;
}

/* Loading Animation */
.badge-loading {
    @apply flex items-center gap-2;
}

/* Error State */
.badge-error {
    @apply flex items-center gap-2;
}

/* Verified State */
.badge-verified {
    @apply flex items-center gap-2;
}

/* Badge Text */
.badge-text {
    @apply font-medium;
}

.verification-method {
    @apply text-xs opacity-75 ml-1;
}

/* Responsive Design */
@media (max-width: 640px) {
    .verification-tooltip {
        @apply min-w-56 max-w-72 text-sm;
    }
    
    .badge-detailed {
        @apply px-3 py-1.5;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .verification-tooltip {
        @apply bg-panel border-gray-600 text-white;
    }
    
    .tooltip-header {
        @apply border-gray-600;
    }
    
    .tooltip-label {
        @apply text-gray-400;
    }
    
    .tooltip-value {
        @apply text-gray-200;
    }
    
    .security-feature {
        @apply text-gray-300;
    }
    
    .tooltip-arrow {
        @apply bg-panel;
    }
}

/* High Contrast Mode */
@media (prefers-contrast: high) {
    .verified {
        @apply border-2 border-green-600;
    }
    
    .loading {
        @apply border-2 border-blue-600;
    }
    
    .error {
        @apply border-2 border-red-600;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .secure-verification-badge {
        @apply transition-none;
    }
    
    .verification-tooltip {
        @apply transition-none;
    }
    
    .with-animation.verified {
        animation: none;
    }
}

/* Print Styles */
@media print {
    .verification-tooltip {
        @apply hidden;
    }
    
    .crypto-indicator {
        @apply hidden;
    }
}
</style>
