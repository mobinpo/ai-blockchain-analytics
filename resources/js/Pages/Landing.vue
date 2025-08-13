<template>
  <div class="landing-page">
    <!-- Navigation -->
    <nav class="fixed top-0 w-full bg-white/90 backdrop-blur-md border-b border-gray-200 z-50">
      <div class="container mx-auto px-6 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <div class="w-8 h-8 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
              <span class="text-white text-sm">🛡️</span>
            </div>
            <span class="text-xl font-bold text-gray-900">Sentiment Shield</span>
          </div>
          
          <div class="hidden md:flex items-center space-x-8">
            <a href="#features" class="text-gray-600 hover:text-gray-900 transition-colors">Features</a>
            <a href="#how-it-works" class="text-gray-600 hover:text-gray-900 transition-colors">How it Works</a>
            <a href="#pricing" class="text-gray-600 hover:text-gray-900 transition-colors">Pricing</a>
            <Link href="/login" class="text-gray-600 hover:text-gray-900 transition-colors">Login</Link>
            <Link href="/register" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-4 py-2 rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-300">
              Get Started
            </Link>
          </div>

          <!-- Mobile menu button -->
          <button 
            @click="showMobileMenu = !showMobileMenu"
            class="md:hidden p-2 text-gray-600 hover:text-gray-900"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
          </button>
        </div>

        <!-- Mobile menu -->
        <div v-if="showMobileMenu" class="md:hidden mt-4 pb-4 border-t border-gray-200">
          <div class="flex flex-col space-y-3 pt-4">
            <a href="#features" class="text-gray-600 hover:text-gray-900 transition-colors">Features</a>
            <a href="#how-it-works" class="text-gray-600 hover:text-gray-900 transition-colors">How it Works</a>
            <a href="#pricing" class="text-gray-600 hover:text-gray-900 transition-colors">Pricing</a>
            <Link href="/login" class="text-gray-600 hover:text-gray-900 transition-colors">Login</Link>
            <Link href="/register" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-4 py-2 rounded-lg text-center hover:from-blue-700 hover:to-purple-700 transition-all duration-300">
              Get Started
            </Link>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-16">
      <!-- Live Contract Analyzer -->
      <LiveContractAnalyzer />

      <!-- Features Section -->
      <section id="features" class="py-20 bg-white">
        <div class="container mx-auto px-6">
          <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">
              Powerful AI-Driven Analysis
            </h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
              Our advanced AI models analyze smart contracts for security vulnerabilities, gas optimization opportunities, and code quality issues in real-time.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Security Analysis -->
            <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-2xl p-8 border border-red-200">
              <div class="text-red-600 mb-4">
                <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-7-4z" clip-rule="evenodd" />
                </svg>
              </div>
              <h3 class="text-2xl font-semibold text-gray-900 mb-3">Security Analysis</h3>
              <p class="text-gray-600 mb-4">
                Detect vulnerabilities like reentrancy, integer overflow, and access control issues with our comprehensive security scanner.
              </p>
              <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  OWASP Top 10 vulnerabilities
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Custom vulnerability patterns
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Risk scoring and prioritization
                </li>
              </ul>
            </div>

            <!-- Gas Optimization -->
            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-2xl p-8 border border-yellow-200">
              <div class="text-yellow-600 mb-4">
                <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" />
                </svg>
              </div>
              <h3 class="text-2xl font-semibold text-gray-900 mb-3">Gas Optimization</h3>
              <p class="text-gray-600 mb-4">
                Identify gas-intensive operations and get recommendations to reduce transaction costs and improve efficiency.
              </p>
              <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Gas usage analysis
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Optimization suggestions
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Cost-benefit analysis
                </li>
              </ul>
            </div>

            <!-- Code Quality -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-8 border border-green-200">
              <div class="text-green-600 mb-4">
                <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </div>
              <h3 class="text-2xl font-semibold text-gray-900 mb-3">Code Quality</h3>
              <p class="text-gray-600 mb-4">
                Assess code maintainability, readability, and adherence to best practices with detailed quality metrics.
              </p>
              <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Code complexity analysis
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Best practices compliance
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Documentation coverage
                </li>
              </ul>
            </div>

            <!-- Multi-Chain Support -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-8 border border-blue-200">
              <div class="text-blue-600 mb-4">
                <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                </svg>
              </div>
              <h3 class="text-2xl font-semibold text-gray-900 mb-3">Multi-Chain Support</h3>
              <p class="text-gray-600 mb-4">
                Analyze contracts across Ethereum, BSC, Polygon, Arbitrum, Optimism, and Avalanche with unified analysis.
              </p>
              <div class="flex flex-wrap gap-2">
                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Ethereum</span>
                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">BSC</span>
                <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">Polygon</span>
                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Arbitrum</span>
                <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Optimism</span>
                <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Avalanche</span>
              </div>
            </div>

            <!-- Real-time Monitoring -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl p-8 border border-purple-200">
              <div class="text-purple-600 mb-4">
                <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9 12a3 3 0 100-6 3 3 0 000 6z" />
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a4 4 0 00-4 4 2 2 0 114 0 4 4 0 00-4 4 2 2 0 114 0 4 4 0 004-4 2 2 0 11-4 0 4 4 0 004-4 2 2 0 11-4 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <h3 class="text-2xl font-semibold text-gray-900 mb-3">Real-time Monitoring</h3>
              <p class="text-gray-600 mb-4">
                Monitor your contracts continuously for new vulnerabilities and security threats with automated alerts.
              </p>
              <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Continuous monitoring
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Instant alerts
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Trend analysis
                </li>
              </ul>
            </div>

            <!-- API Integration -->
            <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-2xl p-8 border border-indigo-200">
              <div class="text-brand-500 mb-4">
                <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 11-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <h3 class="text-2xl font-semibold text-gray-900 mb-3">API Integration</h3>
              <p class="text-gray-600 mb-4">
                Integrate our analysis engine into your development workflow with our comprehensive REST API.
              </p>
              <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  RESTful API
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  Webhook integration
                </li>
                <li class="flex items-center">
                  <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                  SDK libraries
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      <!-- How It Works Section -->
      <section id="how-it-works" class="py-20 bg-panel">
        <div class="container mx-auto px-6">
          <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">
              How It Works
            </h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
              Our AI-powered analysis engine processes your smart contracts through multiple layers of security and quality checks.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="text-center">
              <div class="w-16 h-16 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                1
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">Submit Contract</h3>
              <p class="text-gray-600">Enter your contract address or paste source code for analysis</p>
            </div>

            <div class="text-center">
              <div class="w-16 h-16 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                2
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">AI Analysis</h3>
              <p class="text-gray-600">Our AI models scan for vulnerabilities, gas optimization, and quality issues</p>
            </div>

            <div class="text-center">
              <div class="w-16 h-16 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                3
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">Generate Report</h3>
              <p class="text-gray-600">Receive detailed findings with severity levels and recommendations</p>
            </div>

            <div class="text-center">
              <div class="w-16 h-16 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
                4
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">Take Action</h3>
              <p class="text-gray-600">Implement fixes and monitor your contracts continuously</p>
            </div>
          </div>
        </div>
      </section>

      <!-- CTA Section -->
      <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="container mx-auto px-6 text-center">
          <h2 class="text-4xl font-bold mb-4">
            Ready to Secure Your Smart Contracts?
          </h2>
          <p class="text-xl mb-8 max-w-2xl mx-auto">
            Join thousands of developers who trust AI Blockchain Analytics to keep their contracts secure and optimized.
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <Link href="/register" class="bg-white text-blue-600 px-8 py-3 rounded-xl font-semibold hover:bg-ink transition-colors">
              Start Free Trial
            </Link>
            <a href="#features" class="border-2 border-white text-white px-8 py-3 rounded-xl font-semibold hover:bg-white hover:text-blue-600 transition-colors">
              Learn More
            </a>
          </div>
        </div>
      </section>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
      <div class="container mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <div>
            <div class="flex items-center space-x-2 mb-4">
              <div class="w-8 h-8 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg"></div>
              <span class="text-xl font-bold">AI Blockchain Analytics</span>
            </div>
            <p class="text-gray-400 mb-4">
              Advanced AI-powered smart contract analysis for the blockchain ecosystem.
            </p>
          </div>

          <div>
            <h3 class="text-lg font-semibold mb-4">Product</h3>
            <ul class="space-y-2 text-gray-400">
              <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
              <li><a href="#pricing" class="hover:text-white transition-colors">Pricing</a></li>
              <li><a href="#" class="hover:text-white transition-colors">API</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Documentation</a></li>
            </ul>
          </div>

          <div>
            <h3 class="text-lg font-semibold mb-4">Company</h3>
            <ul class="space-y-2 text-gray-400">
              <li><a href="#" class="hover:text-white transition-colors">About</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Blog</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Careers</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Contact</a></li>
            </ul>
          </div>

          <div>
            <h3 class="text-lg font-semibold mb-4">Support</h3>
            <ul class="space-y-2 text-gray-400">
              <li><a href="#" class="hover:text-white transition-colors">Help Center</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Community</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Status</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Security</a></li>
            </ul>
          </div>
        </div>

        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
          <p>&copy; 2024 AI Blockchain Analytics. All rights reserved.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import LiveContractAnalyzer from '@/Components/Landing/LiveContractAnalyzer.vue'

export default {
  name: 'Landing',
  components: {
    Link,
    LiveContractAnalyzer
  },
  setup() {
    const showMobileMenu = ref(false)

    return {
      showMobileMenu
    }
  }
}
</script>

<style scoped>
.container {
  max-width: 1200px;
}

/* Smooth scrolling for anchor links */
html {
  scroll-behavior: smooth;
}

/* Custom gradient animations */
@keyframes gradient {
  0% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0% 50%;
  }
}

.landing-page {
  min-height: 100vh;
}
</style>
