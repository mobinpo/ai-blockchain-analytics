<template>
    <button 
        @click="handleClick"
        class="bg-white bg-opacity-20 hover:bg-opacity-30 backdrop-blur-sm rounded-lg p-6 text-center transition-all duration-300 transform hover:scale-105"
    >
        <div class="flex flex-col items-center">
            <!-- Icon -->
            <div class="mb-4 p-3 bg-white bg-opacity-20 rounded-full">
                <component :is="iconComponent" class="h-8 w-8 text-white" />
            </div>
            
            <!-- Title -->
            <h4 class="text-lg font-semibold text-white mb-2">{{ title }}</h4>
            
            <!-- Description -->
            <p class="text-sm text-indigo-100">{{ description }}</p>
            
            <!-- Call to Action -->
            <div class="mt-4 flex items-center text-white">
                <span class="text-sm font-medium">Try Now</span>
                <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </div>
    </button>
</template>

<script setup>
import { computed } from 'vue'
import {
    CodeBracketIcon,
    ArrowTrendingUpIcon,
    MagnifyingGlassIcon,
    ChartBarIcon,
    ShieldCheckIcon,
    ChatBubbleLeftEllipsisIcon
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
    }
})

const emit = defineEmits(['click'])

// Icon mapping
const iconMap = {
    code: CodeBracketIcon,
    'trending-up': ArrowTrendingUpIcon,
    search: MagnifyingGlassIcon,
    'chart-bar': ChartBarIcon,
    'shield-check': ShieldCheckIcon,
    'chat': ChatBubbleLeftEllipsisIcon
}

// Computed
const iconComponent = computed(() => iconMap[props.icon] || ChartBarIcon)

// Methods
const handleClick = () => {
    emit('click')
}
</script>