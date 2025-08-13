<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { ref, onMounted, onUnmounted } from 'vue';

const projects = ref([
  {
    id: 1,
    name: 'Uniswap V4 Core',
    description: 'Next-generation AMM with hooks and concentrated liquidity',
    status: 'analyzing',
    risk: 'medium',
    progress: 75,
    lastAnalysis: '2 minutes ago',
    criticalIssues: 2,
    highIssues: 5,
    mediumIssues: 8,
    lowIssues: 12,
    sentiment: 0.73,
    contractAddress: '0x1f9840a85d5aF5bf1D1762F925BDADdC4201F984',
    network: 'Ethereum Mainnet'
  },
  {
    id: 2,
    name: 'Aave V3 Lending Pool',
    description: 'Multi-collateral lending protocol with enhanced capital efficiency',
    status: 'completed',
    risk: 'low',
    progress: 100,
    lastAnalysis: '1 hour ago',
    criticalIssues: 0,
    highIssues: 1,
    mediumIssues: 2,
    lowIssues: 5,
    sentiment: 0.85,
    contractAddress: '0x7Fc66500c84A76Ad7e9c93437bFc5Ac33E2DDaE9',
    network: 'Ethereum Mainnet'
  },
  {
    id: 3,
    name: 'Compound Governance',
    description: 'Decentralized governance system for protocol upgrades',
    status: 'completed',
    risk: 'high',
    progress: 100,
    lastAnalysis: '3 hours ago',
    criticalIssues: 5,
    highIssues: 8,
    mediumIssues: 10,
    lowIssues: 15,
    sentiment: 0.42,
    contractAddress: '0xc00e94Cb662C3520282E6f5717214004A7f26888',
    network: 'Ethereum Mainnet'
  },
  {
    id: 4,
    name: 'MakerDAO Multi-Collateral',
    description: 'Stablecoin generation system with multiple collateral types',
    status: 'pending',
    risk: 'medium',
    progress: 0,
    lastAnalysis: 'Never',
    criticalIssues: 0,
    highIssues: 0,
    mediumIssues: 0,
    lowIssues: 0,
    sentiment: 0.68,
    contractAddress: '0x9f8F72aA9304c8B593d555F12eF6589cC3A579A2',
    network: 'Ethereum Mainnet'
  }
]);

const showNewProjectModal = ref(false);
const selectedProject = ref(null);

const getRiskColor = (risk) => {
  const colors = {
    low: 'text-green-600 bg-green-50 border-green-200',
    medium: 'text-yellow-600 bg-yellow-50 border-yellow-200',
    high: 'text-red-600 bg-red-50 border-red-200'
  };
  return colors[risk] || 'text-gray-600 bg-panel border-gray-200';
};

const getStatusColor = (status) => {
  const colors = {
    analyzing: 'text-blue-600 bg-blue-50 border-blue-200',
    completed: 'text-green-600 bg-green-50 border-green-200',
    pending: 'text-gray-600 bg-panel border-gray-200',
    failed: 'text-red-600 bg-red-50 border-red-200'
  };
  return colors[status] || 'text-gray-600 bg-panel border-gray-200';
};

const getTotalIssues = (project) => {
  return project.criticalIssues + project.highIssues + project.mediumIssues + project.lowIssues;
};

// Track running analysis intervals for cleanup
const analysisIntervals = new Map();
const isComponentActive = ref(true);

const startAnalysis = async (project) => {
  try {
    // Prevent multiple analyses on same project
    if (analysisIntervals.has(project.id)) {
      console.log(`⚠️ Analysis already running for project ${project.id}`);
      return;
    }
    
    console.log(`🚀 Starting analysis for project: ${project.name}`);
    project.status = 'analyzing';
    project.progress = 0;
    
    // Simulate analysis with proper error handling and cleanup
    const analysisPromise = new Promise((resolve, reject) => {
      const interval = setInterval(() => {
        try {
          if (!isComponentActive.value) {
            console.log('⏹️ Component inactive, stopping analysis');
            clearInterval(interval);
            analysisIntervals.delete(project.id);
            reject(new Error('Component unmounted during analysis'));
            return;
          }
          
          // Simulate random progress with potential failures
          const progressIncrement = Math.random() * 10;
          project.progress = Math.min(100, project.progress + progressIncrement);
          
          // Simulate potential failures (5% chance)
          if (Math.random() < 0.05 && project.progress > 20) {
            throw new Error('Analysis service temporarily unavailable');
          }
          
          if (project.progress >= 100) {
            project.progress = 100;
            project.status = 'completed';
            project.lastAnalysis = 'Just now';
            
            // Update some findings for demo
            if (Math.random() > 0.5) {
              project.criticalIssues += Math.floor(Math.random() * 2);
              project.highIssues += Math.floor(Math.random() * 3);
            }
            
            clearInterval(interval);
            analysisIntervals.delete(project.id);
            console.log(`✅ Analysis completed for project: ${project.name}`);
            resolve(project);
          }
        } catch (error) {
          console.error(`❌ Analysis error for project ${project.name}:`, error);
          project.status = 'failed';
          project.progress = 0;
          clearInterval(interval);
          analysisIntervals.delete(project.id);
          reject(error);
        }
      }, 500);
      
      // Store interval for cleanup
      analysisIntervals.set(project.id, interval);
      
      // Add timeout for the entire analysis
      setTimeout(() => {
        if (analysisIntervals.has(project.id)) {
          console.error(`⏰ Analysis timeout for project: ${project.name}`);
          project.status = 'failed';
          project.progress = 0;
          clearInterval(interval);
          analysisIntervals.delete(project.id);
          reject(new Error('Analysis timed out after 60 seconds'));
        }
      }, 60000); // 60 second timeout
    });
    
    try {
      await analysisPromise;
    } catch (error) {
      console.error(`🚨 Analysis failed for project ${project.name}:`, error);
      
      // Implement retry logic
      if (error.message.includes('temporarily unavailable')) {
        console.log(`🔄 Retrying analysis for ${project.name} in 5 seconds...`);
        setTimeout(() => {
          if (isComponentActive.value) {
            startAnalysis(project);
          }
        }, 5000);
      }
    }
    
  } catch (error) {
    console.error(`💥 Failed to start analysis for project ${project.name}:`, error);
    project.status = 'failed';
    project.progress = 0;
  }
};

// Cleanup function for all running analyses
const cleanupAnalyses = () => {
  console.log('🧹 Cleaning up running analyses...');
  analysisIntervals.forEach((interval, projectId) => {
    clearInterval(interval);
    console.log(`⏹️ Stopped analysis for project ${projectId}`);
  });
  analysisIntervals.clear();
};

onMounted(() => {
  console.log('📋 Projects component mounted');
  isComponentActive.value = true;
});

onUnmounted(() => {
  console.log('🧹 Projects component unmounting...');
  isComponentActive.value = false;
  cleanupAnalyses();
  console.log('✅ Projects cleanup completed');
});
</script>

<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Projects</h1>
        <p class="text-gray-600">Manage and monitor your blockchain security analysis projects</p>
      </div>
      <button @click="showNewProjectModal = true" 
              class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        <span>New Project</span>
      </button>
    </div>

    <!-- Projects Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-for="project in projects" :key="project.id" 
           class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
        <!-- Project Header -->
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ project.name }}</h3>
              <p class="text-sm text-gray-600 mb-3">{{ project.description }}</p>
              <div class="flex items-center space-x-4 text-xs text-gray-500">
                <span>📍 {{ project.network }}</span>
                <span>📄 {{ project.contractAddress.slice(0, 10) }}...</span>
              </div>
            </div>
            <div class="flex items-center space-x-2">
              <span :class="getRiskColor(project.risk)" 
                    class="px-3 py-1 text-xs font-medium rounded-full border">
                {{ project.risk.toUpperCase() }} RISK
              </span>
              <span :class="getStatusColor(project.status)" 
                    class="px-3 py-1 text-xs font-medium rounded-full border">
                {{ project.status.toUpperCase() }}
              </span>
            </div>
          </div>

          <!-- Progress Bar (for analyzing projects) -->
          <div v-if="project.status === 'analyzing'" class="mb-4">
            <div class="flex items-center justify-between text-sm text-gray-600 mb-1">
              <span>Analysis Progress</span>
              <span>{{ Math.round(project.progress) }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" 
                   :style="{ width: project.progress + '%' }"></div>
            </div>
          </div>
        </div>

        <!-- Project Stats -->
        <div class="p-6">
          <div class="grid grid-cols-2 gap-6 mb-6">
            <!-- Issues Summary -->
            <div>
              <h4 class="text-sm font-medium text-gray-900 mb-3">Security Issues</h4>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                    <span>Critical</span>
                  </div>
                  <span class="font-medium">{{ project.criticalIssues }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                    <span>High</span>
                  </div>
                  <span class="font-medium">{{ project.highIssues }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                    <span>Medium</span>
                  </div>
                  <span class="font-medium">{{ project.mediumIssues }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                    <span>Low</span>
                  </div>
                  <span class="font-medium">{{ project.lowIssues }}</span>
                </div>
              </div>
            </div>

            <!-- Sentiment & Last Analysis -->
            <div>
              <h4 class="text-sm font-medium text-gray-900 mb-3">Analysis Summary</h4>
              <div class="space-y-3">
                <div>
                  <div class="flex items-center justify-between text-sm mb-1">
                    <span>Market Sentiment</span>
                    <span class="font-medium">{{ Math.round(project.sentiment * 100) }}%</span>
                  </div>
                  <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full transition-all duration-300" 
                         :style="{ width: (project.sentiment * 100) + '%' }"></div>
                  </div>
                </div>
                <div class="text-sm">
                  <span class="text-gray-600">Last Analysis:</span>
                  <span class="font-medium ml-1">{{ project.lastAnalysis }}</span>
                </div>
                <div class="text-sm">
                  <span class="text-gray-600">Total Issues:</span>
                  <span class="font-medium ml-1">{{ getTotalIssues(project) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center space-x-3">
            <button class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium">
              View Details
            </button>
            <button v-if="project.status !== 'analyzing'" 
                    @click="startAnalysis(project)"
                    class="px-4 py-2 bg-ink text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium">
              {{ project.status === 'pending' ? 'Start Analysis' : 'Re-analyze' }}
            </button>
            <button class="px-4 py-2 bg-ink text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium">
              Export
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State (if no projects) -->
    <div v-if="projects.length === 0" class="text-center py-12">
      <div class="text-6xl mb-4">🏗️</div>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No projects yet</h3>
      <p class="text-gray-600 mb-6">Get started by creating your first blockchain security analysis project.</p>
      <button @click="showNewProjectModal = true" 
              class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
        Create Your First Project
      </button>
    </div>

    <!-- Demo Info Banner -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
      <div class="flex items-start space-x-3">
        <div class="text-2xl">💡</div>
        <div>
          <h3 class="text-lg font-semibold text-blue-900 mb-2">Demo Features</h3>
          <ul class="text-sm text-blue-800 space-y-1">
            <li>• Real-time security analysis with AI-powered vulnerability detection</li>
            <li>• Market sentiment analysis from social media and news sources</li>
            <li>• Automated smart contract auditing with detailed reports</li>
            <li>• Multi-chain support for Ethereum, Polygon, and other networks</li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>