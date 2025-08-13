<template>
    <div class="space-y-4">
        <!-- Activity Items -->
        <div class="space-y-3 max-h-96 overflow-y-auto">
            <div v-for="activity in activities" :key="activity.id" class="flex items-start space-x-3 p-3 rounded-lg border border-gray-200 hover:bg-panel transition-colors">
                <!-- Activity Icon -->
                <div class="flex-shrink-0 mt-0.5">
                    <div class="h-8 w-8 rounded-full flex items-center justify-center" :class="getActivityBgColor(activity.type)">
                        <component :is="getActivityIcon(activity.type)" class="h-4 w-4" :class="getActivityIconColor(activity.type)" />
                    </div>
                </div>
                
                <!-- Activity Content -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="text-sm text-gray-900">{{ activity.message }}</p>
                            <div class="mt-1 flex items-center space-x-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="getSeverityBadgeClass(activity.severity)">
                                    {{ getActivityTypeLabel(activity.type) }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ formatTimestamp(activity.timestamp) }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Live indicator for recent activities -->
                        <div v-if="isRecent(activity.timestamp)" class="flex items-center ml-2">
                            <div class="h-2 w-2 bg-green-400 rounded-full animate-pulse"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Empty State -->
        <div v-if="activities.length === 0" class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No Recent Activity</h3>
            <p class="mt-1 text-xs text-gray-500">Activity will appear here as it happens</p>
        </div>
        
        <!-- View More Button -->
        <div v-if="activities.length > 0" class="text-center pt-4 border-t border-gray-200">
            <button class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                View All Activity ({{ activities.length }})
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import {
    ShieldExclamationIcon,
    ChatBubbleLeftEllipsisIcon,
    ChartBarIcon,
    BellIcon,
    CheckCircleIcon,
    XCircleIcon,
    InformationCircleIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    activities: {
        type: Array,
        default: () => []
    },
    isLive: {
        type: Boolean,
        default: false
    }
})

// Icon mapping for activity types
const activityIconMap = {
    security_scan: ShieldExclamationIcon,
    sentiment_alert: ChatBubbleLeftEllipsisIcon,
    api_milestone: ChartBarIcon,
    live_update: BellIcon,
    system_alert: ExclamationTriangleIcon,
    success: CheckCircleIcon,
    error: XCircleIcon,
    info: InformationCircleIcon
}

// Methods
const getActivityIcon = (type) => {
    return activityIconMap[type] || InformationCircleIcon
}

const getActivityBgColor = (type) => {
    const colors = {
        security_scan: 'bg-red-100',
        sentiment_alert: 'bg-blue-100',
        api_milestone: 'bg-green-100',
        live_update: 'bg-purple-100',
        system_alert: 'bg-yellow-100',
        success: 'bg-green-100',
        error: 'bg-red-100',
        info: 'bg-blue-100'
    }
    return colors[type] || 'bg-ink'
}

const getActivityIconColor = (type) => {
    const colors = {
        security_scan: 'text-red-600',
        sentiment_alert: 'text-blue-600',
        api_milestone: 'text-green-600',
        live_update: 'text-purple-600',
        system_alert: 'text-yellow-600',
        success: 'text-green-600',
        error: 'text-red-600',
        info: 'text-blue-600'
    }
    return colors[type] || 'text-gray-600'
}

const getSeverityBadgeClass = (severity) => {
    const classes = {
        high: 'bg-red-100 text-red-800',
        medium: 'bg-yellow-100 text-yellow-800',
        low: 'bg-blue-100 text-blue-800',
        info: 'bg-ink text-gray-800',
        success: 'bg-green-100 text-green-800'
    }
    return classes[severity] || classes.info
}

const getActivityTypeLabel = (type) => {
    const labels = {
        security_scan: 'Security',
        sentiment_alert: 'Sentiment',
        api_milestone: 'API',
        live_update: 'Live',
        system_alert: 'System',
        success: 'Success',
        error: 'Error',
        info: 'Info'
    }
    return labels[type] || 'Activity'
}

const formatTimestamp = (timestamp) => {
    const now = new Date()
    const diff = now - new Date(timestamp)
    const minutes = Math.floor(diff / 60000)
    
    if (minutes < 1) return 'Just now'
    if (minutes < 60) return `${minutes}m ago`
    
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    
    const days = Math.floor(hours / 24)
    return `${days}d ago`
}

const isRecent = (timestamp) => {
    const diff = Date.now() - new Date(timestamp).getTime()
    return diff < 300000 // Less than 5 minutes old
}
</script>