<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  initialStats: {
    type: Object,
    default: () => ({})
  },
  initialProjects: {
    type: Array,
    default: () => []
  },
  initialFindings: {
    type: Array,
    default: () => []
  },
  projectId: {
    type: [String, Number],
    default: null
  },
  showingProject: {
    type: Boolean,
    default: false
  }
});
import { 
  ChartBarIcon, 
  ShieldCheckIcon, 
  HeartIcon, 
  CpuChipIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  ClockIcon,
  EyeIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  MinusIcon
} from '@heroicons/vue/24/outline';
import SecurityChart from '@/Components/Analytics/SecurityChart.vue';
import SentimentGauge from '@/Components/Analytics/SentimentGauge.vue';
import RiskMatrix from '@/Components/Analytics/RiskMatrix.vue';
import NetworkStatus from '@/Components/Analytics/NetworkStatus.vue';
import RealTimeMonitor from '@/Components/Demo/RealTimeMonitor.vue';
import BlockchainExplorer from '@/Components/Demo/BlockchainExplorer.vue';

// Dashboard stats - use props or fetch from API
const stats = ref(props.initialStats);
const loading = ref(false);

// Project-specific data
const projectDetails = ref(null);
const isProjectView = computed(() => props.showingProject && props.projectId);

// Fetch dashboard stats from API if not provided via props
const fetchDashboardStats = async () => {
  if (Object.keys(props.initialStats).length > 0) return;
  
  loading.value = true;
  try {
    const response = await fetch('/api/dashboard/stats', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        stats.value = data.stats || {};
      } else {
        console.error('Failed to fetch dashboard stats:', data.error);
      }
    } else {
      console.error('HTTP error:', response.status, response.statusText);
    }
  } catch (error) {
    console.error('Error fetching dashboard stats:', error);
    // Fallback to empty stats
    stats.value = {};
  } finally {
    loading.value = false;
  }
};

// Recent projects - use props or fetch from API
const recentProjects = ref(props.initialProjects);

// Fetch recent projects from API if not provided via props
const fetchRecentProjects = async () => {
  if (props.initialProjects.length > 0) return;
  
  try {
    const response = await fetch('/api/dashboard/projects', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        recentProjects.value = data.projects || [];
      } else {
        console.error('Failed to fetch recent projects:', data.error);
      }
    } else {
      console.error('HTTP error:', response.status, response.statusText);
    }
  } catch (error) {
    console.error('Error fetching recent projects:', error);
    recentProjects.value = [];
  }
};

// Critical findings - use props or fetch from API
const criticalFindings = ref(props.initialFindings);

// Fetch critical findings from API if not provided via props
const fetchCriticalFindings = async () => {
  if (props.initialFindings.length > 0) return;
  
  try {
    const response = await fetch('/api/dashboard/critical-findings', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        criticalFindings.value = data.findings || [];
      } else {
        console.error('Failed to fetch critical findings:', data.error);
      }
    } else {
      console.error('HTTP error:', response.status, response.statusText);
    }
  } catch (error) {
    console.error('Error fetching critical findings:', error);
    criticalFindings.value = [];
  }
};

// AI Insights - use props or fetch from API
const aiInsights = ref([]);

// Fetch AI insights from API
const fetchAIInsights = async () => {
  try {
    const response = await fetch('/api/dashboard/ai-insights', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        aiInsights.value = data.insights || [];
      } else {
        console.error('Failed to fetch AI insights:', data.error);
      }
    } else {
      console.error('HTTP error:', response.status, response.statusText);
    }
  } catch (error) {
    console.error('Error fetching AI insights:', error);
    aiInsights.value = [];
  }
};

// Fetch project details when viewing a specific project
const fetchProjectDetails = async () => {
  if (!props.projectId) return;
  
  loading.value = true;
  try {
    const response = await fetch(`/api/dashboard/project/${props.projectId}`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        projectDetails.value = data.project;
        // Override dashboard data with project-specific data
        stats.value = {
          totalProjects: data.project.totalProjects,
          activeAnalyses: data.project.activeAnalyses,
          criticalFindings: data.project.criticalFindings,
          avgSentiment: data.project.avgSentiment,
          lastAnalysis: data.project.lastAnalysis,
          securityScore: data.project.securityScore
        };
        criticalFindings.value = data.project.detailedFindings || [];
        aiInsights.value = data.project.aiInsights || [];
      } else {
        console.error('Failed to fetch project details:', data.error);
      }
    } else {
      console.error('HTTP error:', response.status, response.statusText);
    }
  } catch (error) {
    console.error('Error fetching project details:', error);
  } finally {
    loading.value = false;
  }
};

const getSeverityColor = (severity) => {
  switch (severity) {
    case 'critical': return 'text-red-600 bg-red-50';
    case 'high': return 'text-orange-600 bg-orange-50';
    case 'medium': return 'text-yellow-600 bg-yellow-50';
    case 'low': return 'text-green-600 bg-green-50';
    default: return 'text-gray-600 bg-panel';
  }
};

const getRiskColor = (risk) => {
  switch (risk) {
    case 'high': return 'text-red-600 bg-red-100';
    case 'medium': return 'text-yellow-600 bg-yellow-100';
    case 'low': return 'text-green-600 bg-green-100';
    default: return 'text-gray-600 bg-ink';
  }
};

const getSentimentIcon = (sentiment) => {
  if (sentiment > 0.1) return ArrowTrendingUpIcon;
  if (sentiment < -0.1) return ArrowTrendingDownIcon;
  return MinusIcon;
};

const getSentimentColor = (sentiment) => {
  if (sentiment > 0.1) return 'text-green-500';
  if (sentiment < -0.1) return 'text-red-500';
  return 'text-gray-500';
};

// Navigation and action methods
const startNewAnalysis = () => {
  // Navigate to the landing page where users can start analysis
  window.location.href = '/';
};

const viewAllProjects = () => {
  // Navigate to projects page
  window.location.href = '/projects';
};

const viewSecurityReport = () => {
  // Navigate to security page
  window.location.href = '/security';
};

const viewAllInsights = () => {
  // Scroll to insights section or expand view
  console.log('View all insights');
};

const viewAllFindings = () => {
  // Navigate to security page with findings filter
  window.location.href = '/security?filter=critical';
};

const showSecurityDetails = (finding) => {
  // Show modal or navigate to detailed view
  console.log('Show security details for:', finding);
  alert(`Security Finding: ${finding.title}\nSeverity: ${finding.severity}\nDescription: ${finding.description}`);
};

const executeInsightAction = (insight) => {
  // Handle insight action based on type
  switch (insight.type) {
    case 'security':
      window.location.href = '/security';
      break;
    case 'performance':
      console.log('Navigate to performance optimization');
      break;
    case 'sentiment':
      window.location.href = '/sentiment-analysis';
      break;
    default:
      console.log('Execute action:', insight.action);
  }
};

// Initialize data on component mount
onMounted(() => {
  if (isProjectView.value) {
    // If viewing a specific project, fetch project details
    fetchProjectDetails();
  } else {
    // Otherwise, fetch general dashboard data
    fetchDashboardStats();
    fetchRecentProjects();
    fetchCriticalFindings();
    fetchAIInsights();
  }
});
</script>

<template>
    <Head :title="isProjectView ? `${projectDetails?.name || 'Project'} - Security Analysis` : 'Sentiment Shield - AI Security Dashboard'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900">
                        {{ isProjectView ? `📊 ${projectDetails?.name || 'Project Details'}` : '🛡️ Sentiment Shield' }}
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ isProjectView ? projectDetails?.description || 'Loading project details...' : 'AI-powered blockchain security with dual smart contract & sentiment analysis' }}
                    </p>
                    <div v-if="isProjectView && projectDetails" class="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                        <span>📍 {{ projectDetails.network }}</span>
                        <span>📄 {{ projectDetails.contractAddress }}</span>
                        <span :class="projectDetails.riskLevel === 'high' ? 'text-red-600' : projectDetails.riskLevel === 'medium' ? 'text-yellow-600' : 'text-green-600'">
                            🛡️ {{ projectDetails.riskLevel?.toUpperCase() }} RISK
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div v-if="isProjectView" class="text-right">
                        <button @click="window.location.href = '/dashboard'" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            ← Back to Dashboard
                        </button>
                    </div>
                    <div class="flex items-center text-sm text-gray-500">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse mr-2"></div>
                        Live Monitoring Active
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <ChartBarIcon class="h-8 w-8 text-brand-500" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Projects</p>
                                    <p class="text-2xl font-semibold text-gray-900">
                                        <span v-if="loading">...</span>
                                        <span v-else>{{ stats.totalProjects || 0 }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <CpuChipIcon class="h-8 w-8 text-blue-600" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Active Analyses</p>
                                    <div class="flex items-center">
                                        <p class="text-2xl font-semibold text-gray-900">
                                            <span v-if="loading">...</span>
                                            <span v-else>{{ stats.activeAnalyses || 0 }}</span>
                                        </p>
                                        <div class="ml-2 flex items-center text-xs text-gray-500">
                                            <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <ExclamationTriangleIcon class="h-8 w-8 text-red-600" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Critical Findings</p>
                                    <p class="text-2xl font-semibold text-red-600">
                                        <span v-if="loading">...</span>
                                        <span v-else>{{ stats.criticalFindings || 0 }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <HeartIcon class="h-8 w-8 text-green-600" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Avg Sentiment</p>
                                    <div class="flex items-center">
                                        <p class="text-2xl font-semibold text-green-600">
                                            <span v-if="loading">...</span>
                                            <span v-else>{{ stats.avgSentiment ? (stats.avgSentiment * 100).toFixed(0) : 0 }}%</span>
                                        </p>
                                        <ArrowTrendingUpIcon class="h-4 w-4 text-green-500 ml-1" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Visualization Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <SecurityChart />
                    <SentimentGauge 
                        :sentiment="loading ? 0 : stats.avgSentiment"
                        :project-count="loading ? null : stats.totalProjects"
                        :analysis-count="loading ? null : stats.totalAnalyses"
                        :sentiment-change24h="loading ? 0 : stats.sentimentChange24h"
                    />
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <RiskMatrix />
                    <NetworkStatus />
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Recent Projects -->
                    <div class="lg:col-span-2">
                        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">Recent Projects</h3>
                                <p class="text-sm text-gray-600">Latest blockchain projects under analysis</p>
                            </div>
                            <div class="divide-y divide-gray-200">
                                <div 
                                    v-for="project in recentProjects" 
                                    :key="project.id"
                                    class="p-6 hover:bg-panel transition-colors cursor-pointer"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3">
                                                <h4 class="text-sm font-medium text-gray-900">{{ project.name }}</h4>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ project.network }}
                                                </span>
                                                <span :class="['inline-flex items-center px-2 py-1 rounded-full text-xs font-medium', getRiskColor(project.riskLevel)]">
                                                    {{ project.riskLevel.charAt(0).toUpperCase() + project.riskLevel.slice(1) }} Risk
                                                </span>
                                            </div>
                                            <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                                <span>{{ project.findings }} findings</span>
                                                <span>•</span>
                                                <div class="flex items-center">
                                                    <component :is="getSentimentIcon(project.sentiment)" class="h-4 w-4 mr-1" :class="getSentimentColor(project.sentiment)" />
                                                    <span>{{ (project.sentiment * 100).toFixed(0) }}% sentiment</span>
                                                </div>
                                                <span>•</span>
                                                <span>{{ project.lastAnalyzed }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <div v-if="project.status === 'analyzing'" class="flex items-center text-blue-600">
                                                <div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse mr-2"></div>
                                                <span class="text-xs">Analyzing</span>
                                            </div>
                                            <div v-else class="flex items-center text-green-600">
                                                <CheckCircleIcon class="h-4 w-4 mr-1" />
                                                <span class="text-xs">Complete</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Insights Panel -->
                    <div>
                        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">AI Insights</h3>
                                <p class="text-sm text-gray-600">Automated recommendations</p>
                            </div>
                            <div class="p-6 space-y-4">
                                <div 
                                    v-for="insight in aiInsights" 
                                    :key="insight.type"
                                    class="border border-gray-200 rounded-lg p-4"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h4 class="text-sm font-medium text-gray-900">{{ insight.title }}</h4>
                                            <p class="text-xs text-gray-600 mt-1">{{ insight.message }}</p>
                                            <div class="mt-2 flex items-center text-xs text-gray-500">
                                                <span>{{ insight.confidence }}% confidence</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button @click="executeInsightAction(insight)" class="text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-100 transition-colors">
                                            {{ insight.action }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critical Findings -->
                <div class="mt-8">
                    <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Critical Security Findings</h3>
                                    <p class="text-sm text-gray-600">High-priority vulnerabilities requiring immediate attention</p>
                                </div>
                                <button @click="viewAllFindings" class="text-sm bg-red-50 text-red-700 px-3 py-1 rounded-md hover:bg-red-100 transition-colors">
                                    View All
                                </button>
                            </div>
                        </div>
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-panel">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Finding</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CVSS</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Impact</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="finding in criticalFindings" :key="finding.id" class="hover:bg-panel">
                                        <td class="px-6 py-4">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ finding.title }}</div>
                                                <div class="text-sm text-gray-500">{{ finding.function }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ finding.contract }}</td>
                                        <td class="px-6 py-4">
                                            <span :class="['inline-flex items-center px-2 py-1 rounded-full text-xs font-medium', getSeverityColor(finding.severity)]">
                                                {{ finding.severity.charAt(0).toUpperCase() + finding.severity.slice(1) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ finding.cvss }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ finding.impact }}</td>
                                        <td class="px-6 py-4 text-right text-sm">
                                            <button class="text-brand-500 hover:text-indigo-900 font-medium" @click="showSecurityDetails(finding)">View Details</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Demo Features -->
                <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <RealTimeMonitor />
                    <BlockchainExplorer />
                </div>

                <!-- Quick Actions -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button @click="startNewAnalysis" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center justify-center">
                        <CpuChipIcon class="h-5 w-5 mr-2" />
                        Start New Analysis
                    </button>
                    <button @click="viewAllProjects" class="bg-white hover:bg-panel text-gray-700 px-6 py-3 rounded-lg font-medium border border-gray-300 transition-colors flex items-center justify-center">
                        <EyeIcon class="h-5 w-5 mr-2" />
                        View All Projects
                    </button>
                    <button @click="viewSecurityReport" class="bg-white hover:bg-panel text-gray-700 px-6 py-3 rounded-lg font-medium border border-gray-300 transition-colors flex items-center justify-center">
                        <ShieldCheckIcon class="h-5 w-5 mr-2" />
                        Security Report
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
