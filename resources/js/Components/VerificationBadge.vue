<template>
    <div class="verification-badge" :class="badgeClasses">
        <!-- Badge Display -->
        <div v-if="!loading && !error" class="badge-container">
            <!-- Main Badge -->
            <div class="badge-main" :class="badgeTypeClasses">
                <div class="badge-icon">
                    <component :is="badgeIcon" class="w-5 h-5" />
                </div>
                <div class="badge-content">
                    <div class="badge-title">{{ badgeTitle }}</div>
                    <div v-if="showDetails" class="badge-subtitle">{{ badgeSubtitle }}</div>
                </div>
                <div v-if="showVerificationLevel" class="verification-level">
                    <span class="level-indicator" :class="levelClasses">{{ verificationLevel }}</span>
                </div>
            </div>

            <!-- Verification Details (expandable) -->
            <div v-if="showDetails && expanded" class="badge-details">
                <div class="detail-item">
                    <span class="detail-label">Entity:</span>
                    <span class="detail-value">{{ entityType }} - {{ entityId }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Issued:</span>
                    <span class="detail-value">{{ formatDate(issuedAt) }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Expires:</span>
                    <span class="detail-value">{{ formatDate(expiresAt) }}</span>
                </div>
                <div v-if="metadata && Object.keys(metadata).length > 0" class="detail-item">
                    <span class="detail-label">Metadata:</span>
                    <div class="metadata-container">
                        <div v-for="(value, key) in displayMetadata" :key="key" class="metadata-item">
                            <span class="metadata-key">{{ formatKey(key) }}:</span>
                            <span class="metadata-value">{{ formatValue(value) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div v-if="showActions" class="badge-actions">
                <button 
                    v-if="showDetails"
                    @click="toggleExpanded"
                    class="action-button"
                    :class="{ 'active': expanded }"
                >
                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                    {{ expanded ? 'Less' : 'More' }}
                </button>
                
                <button @click="copyBadgeUrl" class="action-button">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    Copy URL
                </button>
                
                <button @click="verifyBadge" class="action-button" :disabled="verifying">
                    <svg v-if="!verifying" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ verifying ? 'Verifying...' : 'Verify' }}
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="badge-loading">
            <div class="loading-spinner"></div>
            <span>Loading verification...</span>
        </div>

        <!-- Error State -->
        <div v-if="error" class="badge-error">
            <div class="error-icon">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.966-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            <div class="error-content">
                <div class="error-title">Verification Failed</div>
                <div class="error-message">{{ error }}</div>
            </div>
        </div>

        <!-- Success Toast -->
        <div v-if="showSuccessToast" class="success-toast">
            ✅ Badge URL copied to clipboard!
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'

// Props
const props = defineProps({
    token: {
        type: String,
        required: true
    },
    badgeData: {
        type: Object,
        default: null
    },
    size: {
        type: String,
        default: 'medium', // small, medium, large
        validator: value => ['small', 'medium', 'large'].includes(value)
    },
    showDetails: {
        type: Boolean,
        default: true
    },
    showActions: {
        type: Boolean,
        default: true
    },
    showVerificationLevel: {
        type: Boolean,
        default: true
    },
    autoVerify: {
        type: Boolean,
        default: true
    },
    theme: {
        type: String,
        default: 'light', // light, dark, auto
        validator: value => ['light', 'dark', 'auto'].includes(value)
    }
})

// Emits
const emit = defineEmits(['verified', 'error', 'copied'])

// Reactive state
const loading = ref(false)
const error = ref(null)
const verifying = ref(false)
const expanded = ref(false)
const showSuccessToast = ref(false)
const verificationData = ref(props.badgeData || null)

// Computed properties
const badgeClasses = computed(() => [
    'verification-badge',
    `size-${props.size}`,
    `theme-${props.theme}`,
    {
        'expanded': expanded.value,
        'loading': loading.value,
        'error': error.value
    }
])

const badgeTypeClasses = computed(() => {
    if (!verificationData.value) return ['badge-unknown']
    
    const badgeType = verificationData.value.badge_type || 'verified'
    const level = verificationLevel.value
    
    return [
        `badge-${badgeType}`,
        `level-${level}`
    ]
})

const badgeTitle = computed(() => {
    if (!verificationData.value) return 'Unknown'
    
    const badgeType = verificationData.value.badge_type || 'verified'
    const entityType = verificationData.value.entity_type || 'entity'
    
    return {
        'security_verified': 'Security Verified',
        'developer_verified': 'Developer Verified',
        'analysis_verified': 'Analysis Verified',
        'verified': 'Verified'
    }[badgeType] || 'Verified'
})

const badgeSubtitle = computed(() => {
    if (!verificationData.value) return ''
    
    const entityType = verificationData.value.entity_type || 'entity'
    const entityId = verificationData.value.entity_id || ''
    
    return `${entityType.charAt(0).toUpperCase() + entityType.slice(1)}: ${entityId.substring(0, 12)}...`
})

const badgeIcon = computed(() => {
    if (!verificationData.value) return 'QuestionMarkCircleIcon'
    
    const badgeType = verificationData.value.badge_type || 'verified'
    
    return {
        'security_verified': 'ShieldCheckIcon',
        'developer_verified': 'UserCheckIcon',
        'analysis_verified': 'DocumentCheckIcon',
        'verified': 'CheckBadgeIcon'
    }[badgeType] || 'CheckBadgeIcon'
})

const verificationLevel = computed(() => {
    if (!verificationData.value || !verificationData.value.metadata) return 'unknown'
    return verificationData.value.metadata.verification_level || 'unknown'
})

const levelClasses = computed(() => {
    const level = verificationLevel.value
    return {
        'high': 'level-high',
        'medium': 'level-medium',
        'low': 'level-low',
        'unknown': 'level-unknown'
    }[level] || 'level-unknown'
})

const entityType = computed(() => verificationData.value?.entity_type || '')
const entityId = computed(() => verificationData.value?.entity_id || '')
const issuedAt = computed(() => verificationData.value?.issued_at || '')
const expiresAt = computed(() => verificationData.value?.expires_at || '')
const metadata = computed(() => verificationData.value?.metadata || {})

const displayMetadata = computed(() => {
    const meta = metadata.value
    const display = {}
    
    // Filter and format metadata for display
    Object.keys(meta).forEach(key => {
        if (key === 'verification_level') return // Already shown separately
        if (typeof meta[key] === 'object' && meta[key] !== null) {
            display[key] = JSON.stringify(meta[key])
        } else {
            display[key] = meta[key]
        }
    })
    
    return display
})

// Methods
const loadBadgeData = async () => {
    if (verificationData.value && !props.autoVerify) return

    loading.value = true
    error.value = null

    try {
        const response = await fetch(`/api/verification/verify`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                token: props.token
            })
        })

        const result = await response.json()

        if (result.success && result.data.valid) {
            verificationData.value = result.data
            emit('verified', result.data)
        } else {
            error.value = result.data?.error || result.message || 'Verification failed'
            emit('error', error.value)
        }
    } catch (err) {
        console.error('Badge verification error:', err)
        error.value = 'Failed to verify badge'
        emit('error', error.value)
    } finally {
        loading.value = false
    }
}

const verifyBadge = async () => {
    verifying.value = true
    await loadBadgeData()
    verifying.value = false
}

const toggleExpanded = () => {
    expanded.value = !expanded.value
}

const copyBadgeUrl = async () => {
    try {
        const badgeUrl = `${window.location.origin}/verification/badge/${encodeURIComponent(props.token)}`
        await navigator.clipboard.writeText(badgeUrl)
        
        showSuccessToast.value = true
        setTimeout(() => {
            showSuccessToast.value = false
        }, 3000)
        
        emit('copied', badgeUrl)
    } catch (err) {
        console.error('Failed to copy URL:', err)
    }
}

const formatDate = (dateString) => {
    if (!dateString) return 'Unknown'
    try {
        return new Date(dateString).toLocaleString()
    } catch {
        return 'Invalid date'
    }
}

const formatKey = (key) => {
    return key.split('_').map(word => 
        word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ')
}

const formatValue = (value) => {
    if (typeof value === 'boolean') return value ? 'Yes' : 'No'
    if (typeof value === 'number') return value.toLocaleString()
    if (typeof value === 'string') {
        if (value.length > 50) return value.substring(0, 47) + '...'
        return value
    }
    return String(value)
}

// Watchers
watch(() => props.token, () => {
    if (props.autoVerify) {
        loadBadgeData()
    }
})

watch(() => props.badgeData, (newData) => {
    if (newData) {
        verificationData.value = newData
    }
})

// Lifecycle
onMounted(() => {
    if (props.autoVerify && !verificationData.value) {
        loadBadgeData()
    }
})

// Icon components (simplified - you'd import these from @heroicons/vue or similar)
const ShieldCheckIcon = 'svg'
const UserCheckIcon = 'svg'
const DocumentCheckIcon = 'svg'
const CheckBadgeIcon = 'svg'
const QuestionMarkCircleIcon = 'svg'
</script>

<style scoped>
.verification-badge {
    @apply relative max-w-md mx-auto;
}

/* Size variants */
.size-small .badge-main {
    @apply p-3 text-sm;
}

.size-medium .badge-main {
    @apply p-4 text-base;
}

.size-large .badge-main {
    @apply p-6 text-lg;
}

/* Badge container */
.badge-container {
    @apply bg-white dark:bg-panel rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden;
}

/* Main badge */
.badge-main {
    @apply flex items-center space-x-3 transition-all duration-300;
}

.badge-icon {
    @apply flex-shrink-0;
}

.badge-content {
    @apply flex-1 min-w-0;
}

.badge-title {
    @apply font-semibold text-gray-900 dark:text-white;
}

.badge-subtitle {
    @apply text-sm text-gray-600 dark:text-gray-400 truncate;
}

.verification-level {
    @apply flex-shrink-0;
}

.level-indicator {
    @apply px-2 py-1 rounded-full text-xs font-medium;
}

/* Verification levels */
.level-high {
    @apply bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200;
}

.level-medium {
    @apply bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200;
}

.level-low {
    @apply bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200;
}

.level-unknown {
    @apply bg-ink text-gray-800 dark:bg-gray-900 dark:text-gray-200;
}

/* Badge types */
.badge-security_verified {
    @apply border-l-4 border-green-500;
}

.badge-developer_verified {
    @apply border-l-4 border-blue-500;
}

.badge-analysis_verified {
    @apply border-l-4 border-purple-500;
}

.badge-verified {
    @apply border-l-4 border-gray-500;
}

.badge-unknown {
    @apply border-l-4 border-gray-300;
}

/* Badge details */
.badge-details {
    @apply border-t border-gray-200 dark:border-gray-700 bg-panel dark:bg-panel p-4 space-y-3;
}

.detail-item {
    @apply flex flex-col sm:flex-row sm:justify-between text-sm;
}

.detail-label {
    @apply font-medium text-gray-700 dark:text-gray-300 sm:w-24 flex-shrink-0;
}

.detail-value {
    @apply text-gray-600 dark:text-gray-400 break-all;
}

.metadata-container {
    @apply space-y-1 mt-1;
}

.metadata-item {
    @apply flex flex-col sm:flex-row sm:justify-between text-xs;
}

.metadata-key {
    @apply font-medium text-gray-600 dark:text-gray-400;
}

.metadata-value {
    @apply text-gray-500 dark:text-gray-500 break-all;
}

/* Actions */
.badge-actions {
    @apply flex items-center justify-between bg-panel dark:bg-panel px-4 py-3 border-t border-gray-200 dark:border-gray-700;
}

.action-button {
    @apply flex items-center space-x-1 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-ink dark:hover:bg-gray-600 rounded-md transition-colors;
}

.action-button:disabled {
    @apply opacity-50 cursor-not-allowed;
}

.action-button.active {
    @apply text-blue-600 dark:text-blue-400;
}

/* Loading state */
.badge-loading {
    @apply flex items-center justify-center space-x-3 p-8 bg-panel dark:bg-panel rounded-lg border border-gray-200 dark:border-gray-700;
}

.loading-spinner {
    @apply w-6 h-6 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin;
}

/* Error state */
.badge-error {
    @apply flex items-center space-x-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg;
}

.error-icon {
    @apply flex-shrink-0 text-red-500 dark:text-red-400;
}

.error-content {
    @apply flex-1;
}

.error-title {
    @apply font-semibold text-red-800 dark:text-red-200;
}

.error-message {
    @apply text-sm text-red-600 dark:text-red-400 mt-1;
}

/* Success toast */
.success-toast {
    @apply absolute top-0 right-0 transform translate-x-2 -translate-y-2 bg-green-500 text-white px-3 py-2 rounded-lg text-sm font-medium shadow-lg z-10 animate-bounce;
}

/* Theme variants */
.theme-dark {
    @apply bg-gray-900 text-white;
}

/* Responsive */
@media (max-width: 640px) {
    .verification-badge {
        @apply mx-4;
    }
    
    .badge-main {
        @apply flex-col items-start space-x-0 space-y-3;
    }
    
    .badge-actions {
        @apply flex-col space-y-2;
    }
}

/* Animations */
.badge-container {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Print styles */
@media print {
    .badge-actions {
        @apply hidden;
    }
    
    .badge-container {
        @apply shadow-none border-2 border-gray-400;
    }
}
</style>