<template>
    <div class="enhanced-verification-badge-container">
        <!-- Verification Badge Display -->
        <div 
            v-if="badgeData?.is_verified" 
            class="enhanced-verification-badge verified"
            :class="{ 'enhanced-security': badgeData?.security_level === 'enhanced' }"
            @mouseenter="showTooltip = true"
            @mouseleave="showTooltip = false"
        >
            <div class="badge-container">
                <div class="badge-icon-container">
                    <!-- Main verification icon -->
                    <svg class="badge-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 0L12.5 4L17.5 3L16.5 8L20 10L16.5 12L17.5 17L12.5 16L10 20L7.5 16L2.5 17L3.5 12L0 10L3.5 8L2.5 3L7.5 4L10 0Z" 
                              :fill="badgeData?.security_level === 'enhanced' ? '#10B981' : '#059669'"/>
                        <path d="M6 10L8.5 12.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        
                        <!-- Enhanced security indicator -->
                        <circle v-if="badgeData?.security_level === 'enhanced'" cx="16" cy="4" r="3" fill="#3B82F6"/>
                        <path v-if="badgeData?.security_level === 'enhanced'" d="M14.5 4L15.5 4.5L17.5 2.5" stroke="white" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    
                    <!-- Security level indicator -->
                    <div 
                        v-if="badgeData?.security_level === 'enhanced'" 
                        class="security-indicator"
                        :title="`Enhanced Security: ${badgeData?.security_features?.signature_algorithm}`"
                    ></div>
                </div>
                
                <span class="badge-text">
                    {{ badgeData?.security_level === 'enhanced' ? 'Enhanced Verified' : 'Verified' }}
                </span>
            </div>

            <!-- Tooltip -->
            <Transition name="tooltip">
                <div v-if="showTooltip" class="badge-tooltip" :class="tooltipPosition">
                    <div class="tooltip-header">
                        <strong>{{ badgeData?.project_name || 'Smart Contract' }}</strong>
                        <span v-if="badgeData?.security_level === 'enhanced'" class="security-level">
                            🛡️ Enhanced Security
                        </span>
                    </div>
                    
                    <div class="tooltip-content">
                        <div class="tooltip-row">
                            <span class="label">Contract:</span>
                            <span class="value">{{ truncatedAddress }}</span>
                        </div>
                        <div class="tooltip-row">
                            <span class="label">Verified:</span>
                            <span class="value">{{ formattedVerificationDate }}</span>
                        </div>
                        <div class="tooltip-row">
                            <span class="label">Method:</span>
                            <span class="value">{{ verificationMethod }}</span>
                        </div>
                    </div>
                    
                    <!-- Security features for enhanced badges -->
                    <div v-if="badgeData?.security_level === 'enhanced' && badgeData?.security_features" class="security-features">
                        <div class="features-title">Security Features:</div>
                        <div class="feature" v-if="badgeData.security_features.multi_layer_protection">
                            ✓ Multi-layer Signature
                        </div>
                        <div class="feature" v-if="badgeData.security_features.anti_spoofing">
                            ✓ Anti-spoofing Protection
                        </div>
                        <div class="feature" v-if="badgeData.security_features.replay_protection">
                            ✓ Replay Attack Prevention
                        </div>
                        <div class="feature" v-if="badgeData.security_features.ip_binding">
                            ✓ IP Address Binding
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Not Verified State -->
        <div v-else-if="showUnverified" class="verification-badge not-verified">
            <div class="badge-container">
                <svg class="badge-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="7" stroke="#9CA3AF" stroke-width="2" fill="none"/>
                    <path d="M5 8L7 10L11 6" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.5"/>
                </svg>
                <span class="badge-text">Not Verified</span>
            </div>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="verification-badge loading">
            <div class="badge-container">
                <div class="loading-spinner"></div>
                <span class="badge-text">Checking...</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

// Props
const props = defineProps({
    contractAddress: {
        type: String,
        required: true,
        validator: (value) => /^0x[a-fA-F0-9]{40}$/.test(value)
    },
    showUnverified: {
        type: Boolean,
        default: false
    },
    tooltipPosition: {
        type: String,
        default: 'top',
        validator: (value) => ['top', 'bottom', 'left', 'right'].includes(value)
    },
    autoLoad: {
        type: Boolean,
        default: true
    }
})

// Emits
const emit = defineEmits(['verification-loaded', 'error'])

// Reactive state
const badgeData = ref(null)
const loading = ref(false)
const showTooltip = ref(false)
const error = ref('')

// Computed properties
const truncatedAddress = computed(() => {
    if (!props.contractAddress) return ''
    const addr = props.contractAddress
    return addr.length > 10 ? `${addr.substring(0, 6)}...${addr.substring(addr.length - 4)}` : addr
})

const formattedVerificationDate = computed(() => {
    if (!badgeData.value?.verified_at) return 'Unknown'
    
    try {
        const date = new Date(badgeData.value.verified_at)
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        })
    } catch (e) {
        return 'Invalid Date'
    }
})

const verificationMethod = computed(() => {
    if (!badgeData.value?.verification_method) return 'Unknown'
    
    const methods = {
        'enhanced_signed_url': 'SHA-256 + HMAC',
        'signed_url': 'Digital Signature',
        'manual': 'Manual Review',
        'api': 'API Verification'
    }
    
    return methods[badgeData.value.verification_method] || 'Custom Method'
})

// Methods
const loadVerificationStatus = async () => {
    if (!props.contractAddress) return

    loading.value = true
    error.value = ''

    try {
        const response = await axios.get(`/enhanced-verification/status/${props.contractAddress}`)
        
        if (response.data.success) {
            badgeData.value = response.data.data
            emit('verification-loaded', response.data.data)
        } else {
            throw new Error(response.data.error || 'Failed to load verification status')
        }
    } catch (err) {
        console.error('Error loading verification status:', err)
        error.value = err.response?.data?.error || err.message || 'Failed to load verification data'
        emit('error', error.value)
        
        // Set empty badge data for not verified state
        badgeData.value = {
            contract_address: props.contractAddress,
            is_verified: false,
            verification_method: null,
            security_level: null
        }
    } finally {
        loading.value = false
    }
}

const refreshStatus = () => {
    loadVerificationStatus()
}

// Lifecycle
onMounted(() => {
    if (props.autoLoad) {
        loadVerificationStatus()
    }
})

// Watch for contract address changes
watch(() => props.contractAddress, (newAddress) => {
    if (newAddress && props.autoLoad) {
        loadVerificationStatus()
    }
})

// Expose methods for parent components
defineExpose({
    loadVerificationStatus,
    refreshStatus,
    badgeData: computed(() => badgeData.value),
    loading: computed(() => loading.value),
    error: computed(() => error.value)
})
</script>

<style scoped>
.enhanced-verification-badge-container {
    display: inline-block;
    position: relative;
}

.enhanced-verification-badge {
    display: inline-flex;
    align-items: center;
    position: relative;
    cursor: pointer;
    user-select: none;
}

.badge-container {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.verified .badge-container {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.enhanced-security .badge-container {
    background: linear-gradient(135deg, #10B981 0%, #3B82F6 100%);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.verified:hover .badge-container {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.not-verified .badge-container {
    background: #F3F4F6;
    color: #6B7280;
    border: 1px solid #E5E7EB;
}

.loading .badge-container {
    background: #F3F4F6;
    color: #6B7280;
}

.badge-icon-container {
    position: relative;
}

.badge-icon {
    flex-shrink: 0;
}

.security-indicator {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 6px;
    height: 6px;
    background: #3B82F6;
    border: 1px solid white;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.badge-text {
    white-space: nowrap;
}

.loading-spinner {
    width: 14px;
    height: 14px;
    border: 2px solid #E5E7EB;
    border-top: 2px solid #6B7280;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Tooltip Styles */
.badge-tooltip {
    position: absolute;
    z-index: 1000;
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 12px;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.4;
    min-width: 250px;
    max-width: 320px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.badge-tooltip.top {
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    margin-bottom: 8px;
}

.badge-tooltip.bottom {
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    margin-top: 8px;
}

.badge-tooltip.left {
    right: 100%;
    top: 50%;
    transform: translateY(-50%);
    margin-right: 8px;
}

.badge-tooltip.right {
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    margin-left: 8px;
}

.tooltip-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.security-level {
    font-size: 10px;
    background: rgba(59, 130, 246, 0.2);
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.tooltip-content {
    margin-bottom: 8px;
}

.tooltip-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.tooltip-row .label {
    color: rgba(255, 255, 255, 0.7);
    font-weight: 500;
}

.tooltip-row .value {
    color: white;
    font-weight: 600;
}

.security-features {
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 8px;
}

.features-title {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 4px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.feature {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 2px;
    display: flex;
    align-items: center;
}

/* Animations */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.7;
        transform: scale(1.1);
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Tooltip transitions */
.tooltip-enter-active, .tooltip-leave-active {
    transition: all 0.2s ease;
}

.tooltip-enter-from, .tooltip-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(-5px);
}

.tooltip-enter-to, .tooltip-leave-from {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .not-verified .badge-container {
        background: #374151;
        color: #9CA3AF;
        border-color: #4B5563;
    }
    
    .loading .badge-container {
        background: #374151;
        color: #9CA3AF;
    }
}

/* Responsive design */
@media (max-width: 640px) {
    .badge-tooltip {
        min-width: 200px;
        max-width: 280px;
        font-size: 11px;
    }
    
    .badge-container {
        padding: 3px 6px;
        font-size: 11px;
    }
    
    .badge-icon {
        width: 16px;
        height: 16px;
    }
}
</style>