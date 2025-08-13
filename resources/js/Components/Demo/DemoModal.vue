<template>
    <TransitionRoot as="template" :show="true">
        <Dialog as="div" class="relative z-50" @close="$emit('close')">
            <TransitionChild
                as="template"
                enter="ease-out duration-300"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-200"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-panel bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">
                            <!-- Modal Header -->
                            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <component :is="getModalIcon()" class="h-8 w-8 text-white mr-3" />
                                        <div>
                                            <DialogTitle as="h3" class="text-lg font-semibold text-white">
                                                {{ getModalTitle() }}
                                            </DialogTitle>
                                            <p class="text-indigo-200 text-sm">{{ getModalDescription() }}</p>
                                        </div>
                                    </div>
                                    <button
                                        @click="$emit('close')"
                                        class="rounded-md bg-white bg-opacity-20 p-2 hover:bg-opacity-30 focus:outline-none focus:ring-2 focus:ring-white"
                                    >
                                        <XMarkIcon class="h-5 w-5 text-white" />
                                    </button>
                                </div>
                            </div>

                            <!-- Modal Content -->
                            <div class="px-6 py-6">
                                <!-- Contract Upload Demo -->
                                <div v-if="demoType === 'contract_upload'" class="space-y-6">
                                    <ContractUploadDemo />
                                </div>

                                <!-- Sentiment Analysis Demo -->
                                <div v-else-if="demoType === 'sentiment_live'" class="space-y-6">
                                    <SentimentLiveDemo />
                                </div>

                                <!-- Explorer Demo -->
                                <div v-else-if="demoType === 'explorer_search'" class="space-y-6">
                                    <ExplorerSearchDemo />
                                </div>

                                <!-- Security Demo -->
                                <div v-else-if="demoType === 'security'" class="space-y-6">
                                    <SecurityAnalysisDemo />
                                </div>

                                <!-- Default Demo -->
                                <div v-else class="text-center py-12">
                                    <div class="mb-4">
                                        <div class="mx-auto h-16 w-16 bg-indigo-100 rounded-full flex items-center justify-center">
                                            <component :is="getModalIcon()" class="h-8 w-8 text-brand-500" />
                                        </div>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Demo Coming Soon</h3>
                                    <p class="text-gray-600 mb-6">This interactive demo is being prepared for you.</p>
                                    <button 
                                        @click="$emit('close')"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                                    >
                                        Close
                                    </button>
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="bg-panel px-6 py-4 flex justify-between items-center">
                                <div class="text-sm text-gray-500">
                                    💡 This is a live demo with real AI processing
                                </div>
                                <div class="flex space-x-3">
                                    <button 
                                        @click="$emit('close')"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-panel"
                                    >
                                        Close
                                    </button>
                                    <button 
                                        v-if="canStartDemo()"
                                        @click="startDemo"
                                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700"
                                    >
                                        Start Demo
                                    </button>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import {
    XMarkIcon,
    CodeBracketIcon,
    ArrowTrendingUpIcon,
    MagnifyingGlassIcon,
    ShieldCheckIcon,
    ChatBubbleLeftEllipsisIcon
} from '@heroicons/vue/24/outline'

// Import demo components
import ContractUploadDemo from '@/Components/Demo/Demos/ContractUploadDemo.vue'
import SentimentLiveDemo from '@/Components/Demo/Demos/SentimentLiveDemo.vue'
import ExplorerSearchDemo from '@/Components/Demo/Demos/ExplorerSearchDemo.vue'
import SecurityAnalysisDemo from '@/Components/Demo/Demos/SecurityAnalysisDemo.vue'

const props = defineProps({
    demoType: {
        type: String,
        required: true
    }
})

const emit = defineEmits(['close'])

// Demo configuration
const demoConfig = {
    contract_upload: {
        title: 'Smart Contract Security Analysis',
        description: 'Upload and analyze Solidity contracts for vulnerabilities',
        icon: CodeBracketIcon
    },
    sentiment_live: {
        title: 'Real-time Market Sentiment',
        description: 'Live social media sentiment analysis and price correlation',
        icon: ArrowTrendingUpIcon
    },
    explorer_search: {
        title: 'Multi-Chain Blockchain Explorer',
        description: 'Search and analyze transactions across 15+ blockchains',
        icon: MagnifyingGlassIcon
    },
    security: {
        title: 'AI Security Auditor',
        description: 'Advanced vulnerability detection with OWASP compliance',
        icon: ShieldCheckIcon
    },
    explorer: {
        title: 'Blockchain Explorer',
        description: 'Real-time blockchain data and analytics',
        icon: MagnifyingGlassIcon
    },
    sentiment: {
        title: 'Sentiment Analysis',
        description: 'Social media sentiment tracking and analysis',
        icon: ChatBubbleLeftEllipsisIcon
    }
}

// Computed properties
const getModalTitle = () => {
    return demoConfig[props.demoType]?.title || 'Demo'
}

const getModalDescription = () => {
    return demoConfig[props.demoType]?.description || 'Interactive demo'
}

const getModalIcon = () => {
    return demoConfig[props.demoType]?.icon || CodeBracketIcon
}

const canStartDemo = () => {
    return ['contract_upload', 'sentiment_live', 'explorer_search', 'security'].includes(props.demoType)
}

// Methods
const startDemo = () => {
    console.log(`Starting ${props.demoType} demo`)
    // Additional demo initialization logic can be added here
}
</script>