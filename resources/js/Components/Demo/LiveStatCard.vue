<template>
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border-l-4" :class="borderColor">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">{{ title }}</p>
                    <div class="flex items-baseline mt-2">
                        <p class="text-2xl font-bold text-gray-900">
                            {{ formattedValue }}
                        </p>
                        <span v-if="suffix" class="ml-2 text-sm text-gray-500">{{ suffix }}</span>
                    </div>
                </div>
                
                <!-- Icon -->
                <div class="p-3 rounded-full" :class="iconBgColor">
                    <component :is="iconComponent" class="h-6 w-6" :class="iconColor" />
                </div>
            </div>
            
            <!-- Change Indicator -->
            <div v-if="change !== null" class="mt-4 flex items-center">
                <div class="flex items-center" :class="changeColor">
                    <component :is="changeIcon" class="h-4 w-4 mr-1" />
                    <span class="text-sm font-medium">
                        {{ Math.abs(change) }}{{ isPercentage ? '%' : '' }}
                    </span>
                </div>
                <span class="text-sm text-gray-500 ml-2">vs last period</span>
            </div>
            
            <!-- Live indicator for real-time stats -->
            <div v-if="isLive" class="mt-3 flex items-center">
                <div class="h-2 w-2 bg-green-400 rounded-full animate-pulse mr-2"></div>
                <span class="text-xs text-green-600 font-medium">Live</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import {
    ShieldCheckIcon,
    ExclamationTriangleIcon,
    HeartIcon,
    ChartBarIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    MinusIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    value: {
        type: [Number, String],
        required: true
    },
    change: {
        type: Number,
        default: null
    },
    icon: {
        type: String,
        required: true
    },
    color: {
        type: String,
        default: 'blue'
    },
    suffix: {
        type: String,
        default: ''
    },
    isPercentage: {
        type: Boolean,
        default: true
    },
    decimalPlaces: {
        type: Number,
        default: 0
    },
    isLive: {
        type: Boolean,
        default: false
    }
})

// Icon mapping
const iconMap = {
    'shield-check': ShieldCheckIcon,
    'exclamation-triangle': ExclamationTriangleIcon,
    'heart': HeartIcon,
    'chart-bar': ChartBarIcon
}

// Computed properties
const iconComponent = computed(() => iconMap[props.icon] || ChartBarIcon)

const formattedValue = computed(() => {
    if (typeof props.value === 'string') return props.value
    
    if (props.decimalPlaces > 0) {
        return Number(props.value).toFixed(props.decimalPlaces)
    }
    
    // Format large numbers with commas
    return Number(props.value).toLocaleString()
})

const borderColor = computed(() => {
    const colors = {
        blue: 'border-blue-500',
        red: 'border-red-500',
        green: 'border-green-500',
        purple: 'border-purple-500',
        yellow: 'border-yellow-500'
    }
    return colors[props.color] || colors.blue
})

const iconBgColor = computed(() => {
    const colors = {
        blue: 'bg-blue-100',
        red: 'bg-red-100',
        green: 'bg-green-100',
        purple: 'bg-purple-100',
        yellow: 'bg-yellow-100'
    }
    return colors[props.color] || colors.blue
})

const iconColor = computed(() => {
    const colors = {
        blue: 'text-blue-600',
        red: 'text-red-600',
        green: 'text-green-600',
        purple: 'text-purple-600',
        yellow: 'text-yellow-600'
    }
    return colors[props.color] || colors.blue
})

const changeColor = computed(() => {
    if (props.change === null) return 'text-gray-500'
    if (props.change > 0) return 'text-green-600'
    if (props.change < 0) return 'text-red-600'
    return 'text-gray-500'
})

const changeIcon = computed(() => {
    if (props.change === null) return MinusIcon
    if (props.change > 0) return ArrowTrendingUpIcon
    if (props.change < 0) return ArrowTrendingDownIcon
    return MinusIcon
})
</script>