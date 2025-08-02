<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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

// Demo data for North-Star booth
const stats = ref({
  totalProjects: 47,
  activeAnalyses: 12,
  criticalFindings: 3,
  avgSentiment: 0.72,
  lastAnalysis: '2 minutes ago',
  securityScore: 85,
  riskLevel: 'Medium',
  trendsImproving: 8
});

const recentProjects = ref([
  {
    id: 1,
    name: 'UniswapV4 Core',
    network: 'Ethereum',
    status: 'analyzing',
    riskLevel: 'low',
    lastAnalyzed: '5 min ago',
    findings: 2,
    sentiment: 0.85
  },
  {
    id: 2,
    name: 'AAVE V3 Lending',
    network: 'Polygon',
    status: 'completed',
    riskLevel: 'medium',
    lastAnalyzed: '1 hour ago',
    findings: 7,
    sentiment: 0.68
  },
  {
    id: 3,
    name: 'Compound Governor',
    network: 'Ethereum',
    status: 'completed',
    riskLevel: 'high',
    lastAnalyzed: '3 hours ago',
    findings: 15,
    sentiment: 0.45
  }
]);

const criticalFindings = ref([
  {
    id: 1,
    title: 'Reentrancy Vulnerability',
    severity: 'critical',
    contract: 'LendingPool.sol',
    function: 'withdraw()',
    impact: 'High',
    cvss: 9.1
  },
  {
    id: 2,
    title: 'Integer Overflow Risk',
    severity: 'high',
    contract: 'TokenVault.sol',
    function: 'calculateRewards()',
    impact: 'Medium',
    cvss: 7.5
  },
  {
    id: 3,
    title: 'Access Control Bypass',
    severity: 'medium',
    contract: 'Governance.sol',
    function: 'executeProposal()',
    impact: 'Low',
    cvss: 5.3
  }
]);

const aiInsights = ref([
  {
    type: 'security',
    title: 'Pattern Recognition Alert',
    message: 'Detected similar vulnerability patterns across 3 contracts. Consider implementing unified security library.',
    confidence: 94,
    action: 'Review Pattern'
  },
  {
    type: 'performance',
    title: 'Gas Optimization Opportunity',
    message: 'Function batching could reduce gas costs by 35% in high-frequency operations.',
    confidence: 87,
    action: 'Optimize Gas'
  },
  {
    type: 'sentiment',
    title: 'Community Sentiment Shift',
    message: 'Positive sentiment increased 23% after latest security audit completion.',
    confidence: 91,
    action: 'View Trends'
  }
]);

const getSeverityColor = (severity) => {
  switch (severity) {
    case 'critical': return 'text-red-600 bg-red-50';
    case 'high': return 'text-orange-600 bg-orange-50';
    case 'medium': return 'text-yellow-600 bg-yellow-50';
    case 'low': return 'text-green-600 bg-green-50';
    default: return 'text-gray-600 bg-gray-50';
  }
};

const getRiskColor = (risk) => {
  switch (risk) {
    case 'high': return 'text-red-600 bg-red-100';
    case 'medium': return 'text-yellow-600 bg-yellow-100';
    case 'low': return 'text-green-600 bg-green-100';
    default: return 'text-gray-600 bg-gray-100';
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
</script>

<template>
    <Head title="AI Blockchain Analytics Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900">
                        AI Blockchain Analytics
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Real-time security analysis and sentiment monitoring
                    </p>
                </div>
                <div class="flex items-center space-x-4">
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
                                    <ChartBarIcon class="h-8 w-8 text-indigo-600" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Projects</p>
                                    <p class="text-2xl font-semibold text-gray-900">{{ stats.totalProjects }}</p>
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
                                        <p class="text-2xl font-semibold text-gray-900">{{ stats.activeAnalyses }}</p>
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
                                    <p class="text-2xl font-semibold text-red-600">{{ stats.criticalFindings }}</p>
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
                                        <p class="text-2xl font-semibold text-green-600">{{ (stats.avgSentiment * 100).toFixed(0) }}%</p>
                                        <ArrowTrendingUpIcon class="h-4 w-4 text-green-500 ml-1" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                    class="p-6 hover:bg-gray-50 transition-colors cursor-pointer"
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
                                        <button class="text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-100 transition-colors">
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
                                <button class="text-sm bg-red-50 text-red-700 px-3 py-1 rounded-md hover:bg-red-100 transition-colors">
                                    View All
                                </button>
                            </div>
                        </div>
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
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
                                    <tr v-for="finding in criticalFindings" :key="finding.id" class="hover:bg-gray-50">
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
                                            <button class="text-indigo-600 hover:text-indigo-900 font-medium">View Details</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center justify-center">
                        <CpuChipIcon class="h-5 w-5 mr-2" />
                        Start New Analysis
                    </button>
                    <button class="bg-white hover:bg-gray-50 text-gray-700 px-6 py-3 rounded-lg font-medium border border-gray-300 transition-colors flex items-center justify-center">
                        <EyeIcon class="h-5 w-5 mr-2" />
                        View All Projects
                    </button>
                    <button class="bg-white hover:bg-gray-50 text-gray-700 px-6 py-3 rounded-lg font-medium border border-gray-300 transition-colors flex items-center justify-center">
                        <ShieldCheckIcon class="h-5 w-5 mr-2" />
                        Security Report
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
