<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { ref, computed } from 'vue';

const selectedSeverity = ref('all');
const selectedProject = ref('all');

const findings = ref([
  {
    id: 1,
    title: 'Reentrancy Vulnerability in Transfer Function',
    severity: 'critical',
    project: 'Uniswap V4',
    description: 'The transfer function allows for reentrancy attacks due to external calls before state changes.',
    line: 127,
    file: 'UniswapV4Pool.sol',
    impact: 'High - Could lead to complete draining of pool funds',
    recommendation: 'Implement reentrancy guard or follow checks-effects-interactions pattern',
    detectedAt: '5 minutes ago',
    status: 'open',
    codeSnippet: `function transfer(address to, uint256 amount) external {
    require(balances[msg.sender] >= amount, "Insufficient balance");
    // VULNERABILITY: External call before state change
    IERC20(token).transfer(to, amount);
    balances[msg.sender] -= amount; // State change after external call
}`
  },
  {
    id: 2,
    title: 'Unchecked Return Value',
    severity: 'high',
    project: 'Compound Gov',
    description: 'External call return value is not checked, potentially leading to silent failures.',
    line: 89,
    file: 'Governance.sol',
    impact: 'Medium - Governance proposals may fail silently',
    recommendation: 'Always check return values of external calls or use SafeERC20',
    detectedAt: '1 hour ago',
    status: 'open',
    codeSnippet: `function executeProposal(uint256 proposalId) external {
    Proposal storage proposal = proposals[proposalId];
    // VULNERABILITY: Return value not checked
    proposal.target.call(proposal.data);
    proposal.executed = true;
}`
  },
  {
    id: 3,
    title: 'Integer Overflow in Calculation',
    severity: 'high',
    project: 'Uniswap V4',
    description: 'Arithmetic operation may overflow without proper checks.',
    line: 245,
    file: 'Math.sol',
    impact: 'High - Incorrect calculations could affect trading',
    recommendation: 'Use SafeMath library or Solidity 0.8+ built-in overflow checks',
    detectedAt: '2 hours ago',
    status: 'investigating',
    codeSnippet: `function calculatePrice(uint256 amount0, uint256 amount1) pure returns (uint256) {
    // VULNERABILITY: Potential overflow
    return (amount0 * 1e18) / amount1;
}`
  },
  {
    id: 4,
    title: 'Gas Optimization Opportunity',
    severity: 'medium',
    project: 'Aave V3 Lending',
    description: 'Loop iteration could be optimized to reduce gas consumption.',
    line: 156,
    file: 'LendingPool.sol',
    impact: 'Low - Higher gas costs for users',
    recommendation: 'Consider batching operations or using more efficient data structures',
    detectedAt: '3 hours ago',
    status: 'resolved',
    codeSnippet: `function updateReserves() external {
    for (uint256 i = 0; i < reserves.length; i++) {
        // OPTIMIZATION: This loop could be gas-intensive for many reserves
        _updateReserveInterestRates(reserves[i]);
    }
}`
  },
  {
    id: 5,
    title: 'Missing Input Validation',
    severity: 'medium',
    project: 'Compound Gov',
    description: 'Function parameters are not properly validated before use.',
    line: 78,
    file: 'Timelock.sol',
    impact: 'Medium - Could lead to unexpected behavior',
    recommendation: 'Add proper input validation and boundary checks',
    detectedAt: '4 hours ago',
    status: 'open',
    codeSnippet: `function setDelay(uint256 delay_) external {
    // VULNERABILITY: No validation on delay_ parameter
    delay = delay_;
    emit DelayChanged(delay_);
}`
  }
]);

const projects = ['all', 'Uniswap V4', 'Aave V3 Lending', 'Compound Gov'];
const severities = ['all', 'critical', 'high', 'medium', 'low'];

const filteredFindings = computed(() => {
  return findings.value.filter(finding => {
    const severityMatch = selectedSeverity.value === 'all' || finding.severity === selectedSeverity.value;
    const projectMatch = selectedProject.value === 'all' || finding.project === selectedProject.value;
    return severityMatch && projectMatch;
  });
});

const getSeverityColor = (severity) => {
  const colors = {
    critical: 'text-red-600 bg-red-50 border-red-200',
    high: 'text-orange-600 bg-orange-50 border-orange-200',
    medium: 'text-yellow-600 bg-yellow-50 border-yellow-200',
    low: 'text-blue-600 bg-blue-50 border-blue-200'
  };
  return colors[severity] || 'text-gray-600 bg-panel border-gray-200';
};

const getStatusColor = (status) => {
  const colors = {
    open: 'text-red-600 bg-red-50',
    investigating: 'text-yellow-600 bg-yellow-50',
    resolved: 'text-green-600 bg-green-50'
  };
  return colors[status] || 'text-gray-600 bg-panel';
};

const getSeverityStats = () => {
  const stats = { critical: 0, high: 0, medium: 0, low: 0 };
  findings.value.forEach(finding => {
    stats[finding.severity]++;
  });
  return stats;
};

const severityStats = getSeverityStats();
const selectedFinding = ref(null);
</script>

<template>
  <AppLayout>
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-2">Security Analysis</h1>
      <p class="text-gray-600">Comprehensive smart contract vulnerability detection and analysis</p>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Critical Issues</p>
            <p class="text-2xl font-bold text-red-600">{{ severityStats.critical }}</p>
          </div>
          <div class="text-3xl">🚨</div>
        </div>
      </div>
      
      <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">High Issues</p>
            <p class="text-2xl font-bold text-orange-600">{{ severityStats.high }}</p>
          </div>
          <div class="text-3xl">⚠️</div>
        </div>
      </div>
      
      <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Medium Issues</p>
            <p class="text-2xl font-bold text-yellow-600">{{ severityStats.medium }}</p>
          </div>
          <div class="text-3xl">⚡</div>
        </div>
      </div>
      
      <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Total Scanned</p>
            <p class="text-2xl font-bold text-gray-800">{{ findings.length }}</p>
          </div>
          <div class="text-3xl">🔍</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8">
      <div class="flex flex-wrap items-center gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Severity</label>
          <select v-model="selectedSeverity" 
                  class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option v-for="severity in severities" :key="severity" :value="severity">
              {{ severity === 'all' ? 'All Severities' : severity.charAt(0).toUpperCase() + severity.slice(1) }}
            </option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
          <select v-model="selectedProject" 
                  class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option v-for="project in projects" :key="project" :value="project">
              {{ project === 'all' ? 'All Projects' : project }}
            </option>
          </select>
        </div>
        
        <div class="flex-1"></div>
        
        <button class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium">
          Export Report
        </button>
      </div>
    </div>

    <!-- Findings List -->
    <div class="space-y-4">
      <div v-for="finding in filteredFindings" :key="finding.id" 
           class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
        <div class="p-6">
          <!-- Header -->
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <div class="flex items-center space-x-3 mb-2">
                <h3 class="text-lg font-semibold text-gray-900">{{ finding.title }}</h3>
                <span :class="getSeverityColor(finding.severity)" 
                      class="px-3 py-1 text-xs font-medium rounded-full border">
                  {{ finding.severity.toUpperCase() }}
                </span>
                <span :class="getStatusColor(finding.status)" 
                      class="px-3 py-1 text-xs font-medium rounded-full">
                  {{ finding.status.toUpperCase() }}
                </span>
              </div>
              <div class="flex items-center space-x-6 text-sm text-gray-600 mb-3">
                <span>📁 {{ finding.project }}</span>
                <span>📄 {{ finding.file }}:{{ finding.line }}</span>
                <span>🕒 {{ finding.detectedAt }}</span>
              </div>
              <p class="text-gray-700 mb-4">{{ finding.description }}</p>
            </div>
          </div>

          <!-- Details Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Impact & Recommendation -->
            <div class="space-y-4">
              <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Impact</h4>
                <p class="text-sm text-gray-700 bg-red-50 p-3 rounded-md">{{ finding.impact }}</p>
              </div>
              <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Recommendation</h4>
                <p class="text-sm text-gray-700 bg-green-50 p-3 rounded-md">{{ finding.recommendation }}</p>
              </div>
            </div>

            <!-- Code Snippet -->
            <div>
              <h4 class="text-sm font-semibold text-gray-900 mb-2">Code Snippet</h4>
              <div class="bg-gray-900 text-gray-100 p-4 rounded-md text-xs font-mono overflow-x-auto">
                <pre>{{ finding.codeSnippet }}</pre>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center space-x-3 pt-4 border-t border-gray-200">
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors text-sm font-medium">
              View Full Details
            </button>
            <button v-if="finding.status === 'open'" 
                    class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded-md hover:bg-yellow-200 transition-colors text-sm font-medium">
              Mark as Investigating
            </button>
            <button v-if="finding.status !== 'resolved'" 
                    class="px-4 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition-colors text-sm font-medium">
              Mark as Resolved
            </button>
            <button class="px-4 py-2 bg-ink text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm font-medium">
              Add Comment
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="filteredFindings.length === 0" class="text-center py-12">
      <div class="text-6xl mb-4">🔍</div>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No findings match your filters</h3>
      <p class="text-gray-600 mb-6">Try adjusting your severity or project filters to see more results.</p>
      <button @click="selectedSeverity = 'all'; selectedProject = 'all'" 
              class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
        Clear Filters
      </button>
    </div>

    <!-- Demo Banner -->
    <div class="mt-8 bg-gradient-to-r from-purple-500 to-pink-600 rounded-lg p-6 text-white">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold mb-2">🔬 AI-Powered Security Analysis</h3>
          <p class="text-purple-100">Our advanced AI models detect complex vulnerabilities that traditional tools miss, providing comprehensive smart contract security analysis.</p>
        </div>
        <button class="px-6 py-3 bg-white bg-opacity-20 rounded-lg font-medium hover:bg-opacity-30 transition-colors">
          Learn More
        </button>
      </div>
    </div>
  </AppLayout>
</template>