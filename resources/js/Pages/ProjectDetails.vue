<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { ref, onMounted } from 'vue';
import api from '@/services/api';

const props = defineProps({
  projectId: String
});

const project = ref(null);
const loading = ref(false);
const error = ref(null);

const fetchProjectDetails = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    const response = await api.get(`/projects/${props.projectId}`);
    project.value = response.data.project;
  } catch (err) {
    error.value = 'Failed to load project details';
    console.error('Error fetching project details:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await fetchProjectDetails();
});

const goBack = () => {
  window.history.back();
};
</script>

<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center space-x-4">
        <button @click="goBack" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-3xl font-bold text-gray-900 mb-2">
            {{ project?.name || 'Project Details' }}
          </h1>
          <p class="text-gray-600">View detailed information about this project</p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      <span class="ml-2 text-gray-600">Loading project details...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
      <div class="flex items-center">
        <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span class="text-red-700">{{ error }}</span>
        <button @click="fetchProjectDetails" class="ml-4 text-red-600 hover:text-red-800 underline">
          Try Again
        </button>
      </div>
    </div>

    <!-- Project Details -->
    <div v-else-if="project" class="space-y-6">
      <!-- Project Overview -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Project Overview</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-sm font-medium text-gray-500 mb-1">Project Name</h3>
            <p class="text-gray-900">{{ project.name }}</p>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500 mb-1">Network</h3>
            <p class="text-gray-900">{{ project.network || 'N/A' }}</p>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500 mb-1">Contract Address</h3>
            <p class="text-gray-900 font-mono text-sm">{{ project.contractAddress || 'N/A' }}</p>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500 mb-1">Status</h3>
            <span :class="{
              'text-green-600 bg-green-50 border-green-200': project.status === 'active',
              'text-yellow-600 bg-yellow-50 border-yellow-200': project.status === 'pending',
              'text-red-600 bg-red-50 border-red-200': project.status === 'failed'
            }" class="px-3 py-1 text-xs font-medium rounded-full border">
              {{ project.status?.toUpperCase() }}
            </span>
          </div>
        </div>
        <div v-if="project.description" class="mt-4">
          <h3 class="text-sm font-medium text-gray-500 mb-1">Description</h3>
          <p class="text-gray-900">{{ project.description }}</p>
        </div>
      </div>

      <!-- Analysis History -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Analysis History</h2>
        <div v-if="project.analyses && project.analyses.length > 0" class="space-y-4">
          <div v-for="analysis in project.analyses" :key="analysis.id" 
               class="border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="font-medium text-gray-900">Analysis #{{ analysis.id }}</span>
              <span :class="{
                'text-green-600 bg-green-50 border-green-200': analysis.status === 'completed',
                'text-blue-600 bg-blue-50 border-blue-200': analysis.status === 'processing',
                'text-yellow-600 bg-yellow-50 border-yellow-200': analysis.status === 'pending',
                'text-red-600 bg-red-50 border-red-200': analysis.status === 'failed'
              }" class="px-2 py-1 text-xs font-medium rounded-full border">
                {{ analysis.status?.toUpperCase() }}
              </span>
            </div>
            <div class="text-sm text-gray-600 mb-2">
              Created: {{ new Date(analysis.created_at).toLocaleString() }}
            </div>
            <div v-if="analysis.findings_count > 0" class="text-sm text-gray-600">
              Findings: {{ analysis.findings_count }}
            </div>
            <div v-if="analysis.sentiment_score" class="text-sm text-gray-600">
              Sentiment Score: {{ Math.round(analysis.sentiment_score * 100) }}%
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          No analyses found for this project.
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center space-x-3">
        <button class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
          Start New Analysis
        </button>
        <button @click="goBack" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors">
          Back to Projects
        </button>
      </div>
    </div>
  </AppLayout>
</template>