<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import api from '@/services/api';

const projects = ref([]);
const loading = ref(false);
const error = ref(null);

const showNewProjectModal = ref(false);
const selectedProject = ref(null);

// New project form data
const newProject = ref({
  name: '',
  description: '',
  blockchain_network: 'ethereum',
  main_contract_address: '',
  project_type: 'smart_contract',
});

const isCreatingProject = ref(false);

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
  return (project.criticalIssues || 0) + (project.highIssues || 0) + (project.mediumIssues || 0) + (project.lowIssues || 0) || project.findings || 0;
};

// Fetch projects from API
const fetchProjects = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    const response = await api.get('/projects');
    projects.value = response.data.projects || response.data || [];
  } catch (err) {
    error.value = 'Failed to load projects';
    console.error('Error fetching projects:', err);
    // Keep empty array as fallback
    projects.value = [];
  } finally {
    loading.value = false;
  }
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
    
    try {
      // Get contract address and network
      const contractAddress = project.contractAddress || project.main_contract_address;
      const network = project.network || project.blockchain_network || 'ethereum';
      
      // Validate required fields
      if (!contractAddress) {
        throw new Error('Contract address is missing. Please edit the project to add a valid contract address.');
      }
      
      if (!/^0x[a-fA-F0-9]{40}$/.test(contractAddress)) {
        throw new Error('Invalid contract address format. Please ensure the contract address is a valid Ethereum address.');
      }
      
      // Prepare analysis data
      const analysisData = {
        contract_address: contractAddress,
        network: network,
        project_id: project.id,
        analysis_type: 'full'
      };
      
      console.log('🔍 Analysis data being sent:', analysisData);
      
      // Call real API to start analysis
      const response = await api.post('/analyses', analysisData);
      
      if (response.data.success) {
        const analysisId = response.data.analysis_id;
        
        // Poll for analysis status
        const pollAnalysis = () => {
          if (!isComponentActive.value || !analysisIntervals.has(project.id)) {
            return;
          }
          
          api.get(`/analyses/${analysisId}/status`)
            .then(statusResponse => {
              if (statusResponse.data.success) {
                const status = statusResponse.data.data;
                project.progress = status.progress || 0;
                project.status = status.status;
                
                if (status.status === 'completed') {
                  project.lastAnalysis = 'Just now';
                  project.criticalIssues = status.issues_found?.critical || 0;
                  project.highIssues = status.issues_found?.high || 0;
                  project.mediumIssues = status.issues_found?.medium || 0;
                  project.lowIssues = status.issues_found?.low || 0;
                  analysisIntervals.delete(project.id);
                  console.log(`✅ Analysis completed for project: ${project.name}`);
                } else if (status.status === 'failed') {
                  project.status = 'failed';
                  project.progress = 0;
                  analysisIntervals.delete(project.id);
                  console.error(`❌ Analysis failed for project: ${project.name}`);
                } else {
                  // Continue polling
                  setTimeout(pollAnalysis, 2000);
                }
              }
            })
            .catch(error => {
              console.error(`❌ Error polling analysis status:`, error);
              project.status = 'failed';
              project.progress = 0;
              analysisIntervals.delete(project.id);
            });
        };
        
        // Mark as actively polling
        analysisIntervals.set(project.id, true);
        
        // Start polling
        setTimeout(pollAnalysis, 1000);
        
      } else {
        throw new Error(response.data.message || 'Failed to start analysis');
      }
      
    } catch (error) {
      console.error(`🚨 Analysis failed for project ${project.name}:`, error);
      if (error.response?.data) {
        console.error('🚨 Detailed error:', error.response.data);
      }
      
      // Show user-friendly error message
      const errorMessage = error.message || error.response?.data?.message || 'Failed to start analysis';
      alert(`Analysis failed: ${errorMessage}`);
      
      project.status = 'failed';
      project.progress = 0;
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

// Additional project actions
const viewProjectDetails = (project) => {
  // Navigate to project detail page
  console.log('View details for project:', project.name);
  window.location.href = `/projects/${project.id}`;
};

const exportProject = (project) => {
  // Export project data as JSON or PDF
  console.log('Export project:', project.name);
  
  const projectData = {
    ...project,
    exportedAt: new Date().toISOString(),
    exportType: 'project_summary'
  };
  
  const blob = new Blob([JSON.stringify(projectData, null, 2)], { 
    type: 'application/json' 
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${project.name.replace(/\s+/g, '_')}_export.json`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
};

const createProject = async () => {
  if (!newProject.value.name.trim()) {
    alert('Project name is required');
    return;
  }
  
  if (!newProject.value.main_contract_address.trim()) {
    alert('Contract address is required');
    return;
  }
  
  if (!/^0x[a-fA-F0-9]{40}$/.test(newProject.value.main_contract_address)) {
    alert('Please enter a valid contract address');
    return;
  }
  
  isCreatingProject.value = true;
  
  try {
    // First create the project
    const projectResponse = await api.post('/projects', newProject.value);
    
    if (projectResponse.data.success) {
      // Close modal and reset form
      showNewProjectModal.value = false;
      newProject.value = {
        name: '',
        description: '',
        blockchain_network: 'ethereum',
        main_contract_address: '',
        project_type: 'smart_contract',
      };
      
      // Refresh projects list
      await fetchProjects();
      
      alert('Project created successfully!');
    } else {
      throw new Error(projectResponse.data.message || 'Failed to create project');
    }
  } catch (err) {
    console.error('Error creating project:', err);
    alert(err.response?.data?.message || err.message || 'Failed to create project');
  } finally {
    isCreatingProject.value = false;
  }
};

const cancelCreateProject = () => {
  showNewProjectModal.value = false;
  newProject.value = {
    name: '',
    description: '',
    blockchain_network: 'ethereum',
    main_contract_address: '',
    project_type: 'smart_contract',
  };
};

onMounted(async () => {
  console.log('📋 Projects component mounted');
  isComponentActive.value = true;
  await fetchProjects();
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

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      <span class="ml-2 text-gray-600">Loading projects...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
      <div class="flex items-center">
        <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span class="text-red-700">{{ error }}</span>
        <button @click="fetchProjects" class="ml-4 text-red-600 hover:text-red-800 underline">
          Try Again
        </button>
      </div>
    </div>

    <!-- Projects Grid -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-for="project in projects" :key="project.id" 
           class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
        <!-- Project Header -->
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ project.name }}</h3>
              <p class="text-sm text-gray-600 mb-3">{{ project.description || 'Smart contract analysis project' }}</p>
              <div class="flex items-center space-x-4 text-xs text-gray-500">
                <span>📍 {{ project.network }}</span>
                <span v-if="project.contractAddress">📄 {{ project.contractAddress.slice(0, 10) }}...</span>
                <span v-else>📄 Contract address not available</span>
              </div>
            </div>
            <div class="flex items-center space-x-2">
              <span :class="getRiskColor(project.riskLevel)" 
                    class="px-3 py-1 text-xs font-medium rounded-full border">
                {{ project.riskLevel?.toUpperCase() }} RISK
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
                  <span class="font-medium">{{ project.criticalIssues || 0 }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                    <span>High</span>
                  </div>
                  <span class="font-medium">{{ project.highIssues || 0 }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                    <span>Medium</span>
                  </div>
                  <span class="font-medium">{{ project.mediumIssues || 0 }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                    <span>Low</span>
                  </div>
                  <span class="font-medium">{{ project.lowIssues || 0 }}</span>
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
                  <span class="font-medium ml-1">{{ project.lastAnalyzed || project.lastAnalysis || 'Never' }}</span>
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
            <button @click="viewProjectDetails(project)" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium">
              View Details
            </button>
            <button v-if="project.status !== 'analyzing'" 
                    @click="startAnalysis(project)"
                    class="px-4 py-2 bg-ink text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium">
              {{ project.status === 'pending' ? 'Start Analysis' : 'Re-analyze' }}
            </button>
            <button @click="exportProject(project)" class="px-4 py-2 bg-ink text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium">
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

    <!-- New Project Modal -->
    <div v-if="showNewProjectModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="cancelCreateProject"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                  Create New Project
                </h3>
                
                <!-- Project Form -->
                <form @submit.prevent="createProject" class="space-y-4">
                  <!-- Project Name -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Project Name *
                    </label>
                    <input 
                      v-model="newProject.name"
                      type="text" 
                      required
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-900 placeholder-gray-500"
                      placeholder="Enter project name"
                    />
                  </div>

                  <!-- Description -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Description
                    </label>
                    <textarea 
                      v-model="newProject.description"
                      rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-900 placeholder-gray-500"
                      placeholder="Enter project description (optional)"
                    ></textarea>
                  </div>

                  <!-- Network Selection -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Blockchain Network *
                    </label>
                    <select 
                      v-model="newProject.blockchain_network"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-900"
                    >
                      <option value="ethereum">Ethereum</option>
                      <option value="bsc">BSC (Binance Smart Chain)</option>
                      <option value="polygon">Polygon</option>
                      <option value="arbitrum">Arbitrum</option>
                      <option value="optimism">Optimism</option>
                      <option value="avalanche">Avalanche</option>
                      <option value="fantom">Fantom</option>
                    </select>
                  </div>

                  <!-- Contract Address -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Main Contract Address *
                    </label>
                    <input 
                      v-model="newProject.main_contract_address"
                      type="text" 
                      required
                      pattern="^0x[a-fA-F0-9]{40}$"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-900 placeholder-gray-500"
                      placeholder="0x..."
                    />
                    <p class="text-xs text-gray-500 mt-1">Enter the main smart contract address to analyze</p>
                  </div>

                  <!-- Project Type -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Project Type
                    </label>
                    <select 
                      v-model="newProject.project_type"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-900"
                    >
                      <option value="smart_contract">Smart Contract</option>
                      <option value="defi">DeFi Protocol</option>
                      <option value="nft">NFT Collection</option>
                      <option value="dao">DAO</option>
                      <option value="bridge">Bridge</option>
                      <option value="dex">DEX</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              @click="createProject"
              :disabled="isCreatingProject"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
              <span v-if="isCreatingProject">Creating...</span>
              <span v-else>Create Project</span>
            </button>
            <button
              @click="cancelCreateProject"
              :disabled="isCreatingProject"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:bg-gray-100 disabled:cursor-not-allowed"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>