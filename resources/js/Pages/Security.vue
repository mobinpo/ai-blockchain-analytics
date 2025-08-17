<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  initialFindings: {
    type: Array,
    default: () => []
  },
  initialProjects: {
    type: Array,
    default: () => []
  }
});

const selectedSeverity = ref('all');
const selectedProject = ref('all');
const loading = ref(false);

// Use props data or fetch from API
const findings = ref(props.initialFindings);

// Dynamic projects list based on findings
const projects = computed(() => {
  const uniqueProjects = ['all', ...new Set(findings.value.map(f => f.project).filter(Boolean))];
  return props.initialProjects.length > 0 ? ['all', ...props.initialProjects] : uniqueProjects;
});

const severities = ['all', 'critical', 'high', 'medium', 'low'];

// Fetch security findings from API if not provided via props
const fetchFindings = async () => {
  if (props.initialFindings.length > 0) return;
  
  loading.value = true;
  try {
    // Fetch recent security findings from API
    const response = await fetch('/api/security/findings', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        findings.value = data.findings || [];
      } else {
        console.error('Failed to fetch security findings:', data.error);
      }
    } else {
      console.error('HTTP error:', response.status, response.statusText);
    }
  } catch (error) {
    console.error('Error fetching security findings:', error);
    // Keep empty array as fallback
    findings.value = [];
  } finally {
    loading.value = false;
  }
};

const filteredFindings = computed(() => {
  return findings.value.filter(finding => {
    const severityMatch = selectedSeverity.value === 'all' || finding.severity === selectedSeverity.value;
    const projectMatch = selectedProject.value === 'all' || finding.project === selectedProject.value;
    return severityMatch && projectMatch;
  });
});

const severityStats = computed(() => {
  const stats = { critical: 0, high: 0, medium: 0, low: 0 };
  findings.value.forEach(finding => {
    if (stats.hasOwnProperty(finding.severity)) {
      stats[finding.severity]++;
    }
  });
  return stats;
});

const getSeverityColor = (severity) => {
  switch (severity) {
    case 'critical': return 'bg-red-100 text-red-800 border-red-200';
    case 'high': return 'bg-orange-100 text-orange-800 border-orange-200';
    case 'medium': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    case 'low': return 'bg-blue-100 text-blue-800 border-blue-200';
    default: return 'bg-gray-100 text-gray-800 border-gray-200';
  }
};

const getStatusColor = (status) => {
  switch (status) {
    case 'open': return 'bg-red-100 text-red-800';
    case 'investigating': return 'bg-yellow-100 text-yellow-800';
    case 'resolved': return 'bg-green-100 text-green-800';
    default: return 'bg-gray-100 text-gray-800';
  }
};

onMounted(() => {
  fetchFindings();
});
</script>

<template>
  <AppLayout>
    <div class="p-6 bg-gray-50 min-h-screen">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">🔒 Security Analysis</h1>
        <p class="text-gray-600 mb-6">Monitor and track security vulnerabilities across your smart contracts</p>

        <!-- Severity Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <div class="bg-white rounded-lg shadow-sm p-6 border">
            <div class="flex items-center">
              <div class="p-2 bg-red-100 rounded-lg">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-sm font-medium text-gray-900">Critical</h3>
                <p class="text-2xl font-bold text-red-600">{{ severityStats.critical }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm p-6 border">
            <div class="flex items-center">
              <div class="p-2 bg-orange-100 rounded-lg">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-sm font-medium text-gray-900">High</h3>
                <p class="text-2xl font-bold text-orange-600">{{ severityStats.high }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm p-6 border">
            <div class="flex items-center">
              <div class="p-2 bg-yellow-100 rounded-lg">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-sm font-medium text-gray-900">Medium</h3>
                <p class="text-2xl font-bold text-yellow-600">{{ severityStats.medium }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm p-6 border">
            <div class="flex items-center">
              <div class="p-2 bg-blue-100 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-sm font-medium text-gray-900">Low</h3>
                <p class="text-2xl font-bold text-blue-600">{{ severityStats.low }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="severity" class="block text-sm font-medium text-gray-700 mb-2">Filter by Severity</label>
            <select
              v-model="selectedSeverity"
              id="severity"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option v-for="severity in severities" :key="severity" :value="severity">
                {{ severity.charAt(0).toUpperCase() + severity.slice(1) }}
              </option>
            </select>
          </div>

          <div>
            <label for="project" class="block text-sm font-medium text-gray-700 mb-2">Filter by Project</label>
            <select
              v-model="selectedProject"
              id="project"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option v-for="project in projects" :key="project" :value="project">
                {{ project.charAt(0).toUpperCase() + project.slice(1) }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="bg-white rounded-lg shadow-sm border p-12 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p class="text-gray-600">Loading security findings...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredFindings.length === 0" class="bg-white rounded-lg shadow-sm border p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No Security Findings</h3>
        <p class="text-gray-600">
          {{ findings.length === 0 ? 'No security analyses have been performed yet.' : 'No findings match your current filters.' }}
        </p>
      </div>

      <!-- Security Findings List -->
      <div v-else class="space-y-6">
        <div
          v-for="finding in filteredFindings"
          :key="finding.id"
          class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-shadow"
        >
          <div class="p-6">
            <div class="flex items-start justify-between mb-4">
              <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                  <h3 class="text-lg font-semibold text-gray-900">{{ finding.title }}</h3>
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium border', getSeverityColor(finding.severity)]">
                    {{ finding.severity.toUpperCase() }}
                  </span>
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusColor(finding.status)]">
                    {{ finding.status.charAt(0).toUpperCase() + finding.status.slice(1) }}
                  </span>
                </div>
                <p class="text-gray-600 mb-3">{{ finding.description }}</p>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                  <div>
                    <span class="font-medium text-gray-700">Project:</span>
                    <span class="ml-1 text-gray-900">{{ finding.project }}</span>
                  </div>
                  <div>
                    <span class="font-medium text-gray-700">File:</span>
                    <span class="ml-1 text-gray-900">{{ finding.file }}</span>
                  </div>
                  <div>
                    <span class="font-medium text-gray-700">Line:</span>
                    <span class="ml-1 text-gray-900">{{ finding.line }}</span>
                  </div>
                </div>
              </div>
              
              <div class="ml-4 text-right">
                <p class="text-sm text-gray-500">{{ finding.detectedAt }}</p>
                <div v-if="finding.cvss" class="mt-1">
                  <span class="text-sm font-medium text-gray-700">CVSS:</span>
                  <span class="ml-1 text-sm font-bold" :class="finding.cvss >= 7 ? 'text-red-600' : finding.cvss >= 4 ? 'text-yellow-600' : 'text-green-600'">
                    {{ finding.cvss }}
                  </span>
                </div>
              </div>
            </div>

            <div class="border-t pt-4 space-y-3">
              <div>
                <h4 class="text-sm font-medium text-gray-900 mb-1">Impact:</h4>
                <p class="text-sm text-gray-600">{{ finding.impact }}</p>
              </div>
              
              <div>
                <h4 class="text-sm font-medium text-gray-900 mb-1">Recommendation:</h4>
                <p class="text-sm text-gray-600">{{ finding.recommendation }}</p>
              </div>

              <div v-if="finding.codeSnippet">
                <h4 class="text-sm font-medium text-gray-900 mb-2">Code Snippet:</h4>
                <pre class="bg-gray-50 border rounded p-3 text-xs overflow-x-auto"><code>{{ finding.codeSnippet }}</code></pre>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>