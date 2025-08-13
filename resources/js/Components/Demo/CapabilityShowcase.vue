<template>
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition-all duration-300 cursor-pointer" @click="handleDemoClick">
        <div class="p-6">
            <!-- Header with Icon -->
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 rounded-full" :class="iconBgColor">
                    <component :is="iconComponent" class="h-6 w-6" :class="iconColor" />
                </div>
                <div class="text-xs font-medium text-gray-500 bg-ink px-2 py-1 rounded-full">
                    Demo Available
                </div>
            </div>
            
            <!-- Title and Description -->
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ title }}</h3>
                <p class="text-sm text-gray-600">{{ description }}</p>
            </div>
            
            <!-- Metrics -->
            <div class="space-y-2 mb-4">
                <div v-for="(metric, index) in metrics" :key="index" class="flex items-center text-sm">
                    <div class="h-2 w-2 rounded-full mr-2" :class="getMetricColor(index)"></div>
                    <span class="text-gray-700">{{ metric }}</span>
                </div>
            </div>
            
            <!-- Performance Indicator -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <div class="flex items-center space-x-2">
                    <div class="h-2 w-2 bg-green-400 rounded-full animate-pulse"></div>
                    <span class="text-xs text-green-600 font-medium">Active</span>
                </div>
                
                <button class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center">
                    Try Demo
                    <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Animated Progress Bar -->
        <div class="h-1 bg-ink">
            <div class="h-1 rounded-full transition-all duration-2000" :class="progressBarColor" :style="{ width: animatedProgress + '%' }"></div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
    GlobeAltIcon,
    ShieldCheckIcon,
    ChatBubbleLeftRightIcon,
    MagnifyingGlassIcon,
    ChartBarIcon,
    CogIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    description: {
        type: String,
        required: true
    },
    icon: {
        type: String,
        required: true
    },
    color: {
        type: String,
        default: 'blue'
    },
    metrics: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['demo-click'])

// Reactive state
const animatedProgress = ref(0)

// Icon mapping
const iconMap = {
    'globe-alt': GlobeAltIcon,
    'shield-check': ShieldCheckIcon,
    'chat-alt-2': ChatBubbleLeftRightIcon,
    'search': MagnifyingGlassIcon,
    'chart-bar': ChartBarIcon,
    'cog': CogIcon
}

// Computed properties
const iconComponent = computed(() => iconMap[props.icon] || ChartBarIcon)

const iconBgColor = computed(() => {
    const colors = {
        blue: 'bg-blue-100',
        green: 'bg-green-100',
        purple: 'bg-purple-100',
        red: 'bg-red-100',
        yellow: 'bg-yellow-100'
    }
    return colors[props.color] || colors.blue
})

const iconColor = computed(() => {
    const colors = {
        blue: 'text-blue-600',
        green: 'text-green-600',
        purple: 'text-purple-600',
        red: 'text-red-600',
        yellow: 'text-yellow-600'
    }
    return colors[props.color] || colors.blue
})

const progressBarColor = computed(() => {
    const colors = {
        blue: 'bg-blue-500',
        green: 'bg-green-500',
        purple: 'bg-purple-500',
        red: 'bg-red-500',
        yellow: 'bg-yellow-500'
    }
    return colors[props.color] || colors.blue
})

// Methods
const getMetricColor = (index) => {
    const colors = ['bg-green-400', 'bg-blue-400', 'bg-purple-400', 'bg-yellow-400']
    return colors[index % colors.length]
}

const handleDemoClick = () => {
    emit('demo-click')
}

// Lifecycle
onMounted(() => {
    // Animate progress bar on mount
    setTimeout(() => {
        animatedProgress.value = Math.floor(Math.random() * 30) + 70 // 70-100%
    }, 300)
})
</script>