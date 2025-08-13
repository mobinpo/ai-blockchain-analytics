<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import LiveContractAnalyzer from '@/Components/LiveContractAnalyzer.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

// Reference to the LiveContractAnalyzer component
const liveAnalyzer = ref(null);

// Quick contract analysis function
function analyzeQuickContract(address, name) {
    // Find the contract input field and populate it
    const contractInput = document.querySelector('input[placeholder*="Enter contract address"]');
    if (contractInput) {
        contractInput.value = address;
        contractInput.dispatchEvent(new Event('input', { bubbles: true }));
        
        // Trigger analysis
        setTimeout(() => {
            const analyzeButton = document.querySelector('button[type="submit"]');
            if (analyzeButton && !analyzeButton.disabled) {
                analyzeButton.click();
            }
        }, 100);

        // Scroll to analyzer
        const analyzerElement = document.querySelector('.live-contract-analyzer');
        if (analyzerElement) {
            analyzerElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <Head title="Sentiment Shield - AI-Powered Blockchain Security" />
    <div class="bg-panel text-black/50 dark:bg-black dark:text-white/50">
        <img
            id="background"
            class="absolute -left-20 top-0 max-w-[877px]"
            src="https://laravel.com/assets/img/welcome/background.svg"
        />
        <div
            class="relative flex min-h-screen flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white"
        >
            <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                <header
                    class="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3"
                >
                    <div class="flex lg:col-start-2 lg:justify-center">
                        <div class="flex items-center space-x-3">
                            <div class="h-12 w-12 lg:h-16 lg:w-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                                <span class="text-2xl lg:text-3xl">🛡️</span>
                            </div>
                            <div class="text-center">
                                <h1 class="text-xl lg:text-2xl font-bold text-black dark:text-white">
                                    Sentiment Shield
                                </h1>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    AI-Powered Blockchain Security Platform
                                </p>
                            </div>
                        </div>
                    </div>
                    <nav v-if="canLogin" class="-mx-3 flex flex-1 justify-end">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                        >
                            Dashboard
                        </Link>

                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Log in
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Register
                            </Link>
                        </template>
                    </nav>
                </header>

                <main class="mt-6">
                    <!-- Hero Section with Prominent Live Analyzer -->
                    <div class="text-center mb-8">
                        <div class="mb-6">
                            <div class="inline-flex items-center space-x-3 bg-gradient-to-r from-blue-100 to-purple-100 dark:from-blue-900/30 dark:to-purple-900/30 px-6 py-3 rounded-full border-2 border-blue-200 dark:border-blue-700 mb-4">
                                <span class="text-2xl">🔍</span>
                                <span class="text-lg font-semibold text-blue-800 dark:text-blue-300">Live Contract Analyzer</span>
                                <span class="bg-green-500 text-white px-3 py-1 rounded-full text-sm font-bold">FREE</span>
                            </div>
                        </div>
                        
                        <h1 class="text-4xl lg:text-6xl font-bold text-black dark:text-white mb-6">
                            <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                                🛡️ Sentiment Shield
                            </span>
                            <br/>
                            AI Security & Sentiment Analysis
                        </h1>
                        
                        <p class="text-xl text-gray-600 dark:text-gray-300 mb-4 max-w-4xl mx-auto leading-relaxed">
                            🚀 <strong>The world's first dual-analysis platform</strong> combining smart contract security with real-time sentiment analysis. 
                            <br/>
                            <span class="text-lg text-blue-600 dark:text-blue-400 font-medium">
                                ⚡ AI-powered security audits • 📊 Social sentiment tracking • 💰 $25B+ TVL protected
                            </span>
                        </p>
                        
                        <!-- Enhanced Stats -->
                        <div class="flex flex-wrap justify-center gap-6 mb-4">
                            <div class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-300">
                                <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                                <span><strong>15,200+</strong> Contracts Analyzed</span>
                            </div>
                            <div class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-300">
                                <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                                <span><strong>1,847</strong> Vulnerabilities Found</span>
                            </div>
                            <div class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-300">
                                <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                                <span><strong>95%</strong> Detection Accuracy</span>
                            </div>
                        </div>
                        
                        <!-- Call to Action -->
                        <div class="space-y-4 mb-8">
                            <!-- Live Demo Badge -->
                            <div class="inline-flex items-center space-x-2 bg-gradient-to-r from-green-100 to-blue-100 dark:bg-gradient-to-r dark:from-green-900/30 dark:to-blue-900/30 text-green-800 dark:text-green-300 px-6 py-3 rounded-full text-sm font-medium border-2 border-green-200 dark:border-green-700 animate-bounce">
                                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                <span>🔴 LIVE DEMO - One-Click Analysis Ready!</span>
                                <span class="text-xs bg-yellow-200 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200 px-2 py-1 rounded-full ml-2">FREE</span>
                            </div>
                            
                            <!-- CTA Arrows -->
                            <div class="flex justify-center items-center space-x-4 text-2xl animate-pulse">
                                <span>👇</span>
                                <span class="text-lg font-bold text-blue-600 dark:text-blue-400">Try it now - Paste any contract address below!</span>
                                <span>👇</span>
                            </div>
                        </div>
                    </div>

                    <!-- Prominent Live Contract Analyzer with Enhanced Wrapper -->
                    <div class="mb-16 live-contract-analyzer relative">
                        <!-- Spotlight Effect -->
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-purple-500/10 rounded-3xl blur-xl"></div>
                        <div class="relative">
                            <!-- Prominent Header -->
                            <div class="text-center mb-6">
                                <h2 class="text-3xl lg:text-4xl font-bold text-black dark:text-white mb-2">
                                    🚀 Start Your Analysis Here
                                </h2>
                                <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                                    Simply paste a contract address (like 0x...) or Solidity code in the field below and click "Analyze Now" for instant results.
                                </p>
                            </div>
                            
                            <LiveContractAnalyzer ref="liveAnalyzer" />
                            
                            <!-- Post-Analyzer CTA -->
                            <div class="text-center mt-6 space-y-3">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    💡 <strong>Pro Tip:</strong> Try the famous contract buttons above the input field for instant examples!
                                </p>
                                <div class="flex justify-center space-x-4 text-xs text-gray-400 dark:text-gray-500">
                                    <span>✅ No account needed</span>
                                    <span>⚡ Results in seconds</span>
                                    <span>🔒 Professional security analysis</span>
                                    <span>📊 Detailed vulnerability reports</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Famous Contracts Section -->
                    <div class="mb-12 text-center">
                        <h3 class="text-2xl font-bold text-black dark:text-white mb-2">
                            🏆 One-Click Demo: Famous Contracts
                        </h3>
                        <p class="text-gray-600 dark:text-gray-300 mb-6">
                            Click any contract below to instantly analyze it - no typing required!
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 max-w-6xl mx-auto">
                            <button 
                                @click="analyzeQuickContract('0xE592427A0AEce92De3Edee1F18E0157C05861564', 'Uniswap V3')"
                                class="bg-white dark:bg-zinc-800 rounded-xl p-5 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border-2 border-green-200 dark:border-green-700 hover:border-green-400 dark:hover:border-green-500 group"
                                title="Click to analyze Uniswap V3 Router contract instantly"
                            >
                                <div class="text-3xl mb-2 group-hover:scale-110 transition-transform">🦄</div>
                                <div class="font-bold text-sm text-black dark:text-white">Uniswap V3</div>
                                <div class="text-xs text-green-600 dark:text-green-400 font-medium">$3.5B TVL</div>
                                <div class="text-xs text-gray-500 mt-1">✅ Secure DeFi</div>
                            </button>
                            <button 
                                @click="analyzeQuickContract('0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2', 'Aave V3')"
                                class="bg-white dark:bg-zinc-800 rounded-xl p-5 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border-2 border-green-200 dark:border-green-700 hover:border-green-400 dark:hover:border-green-500 group"
                                title="Click to analyze Aave V3 Pool contract instantly"
                            >
                                <div class="text-3xl mb-2 group-hover:scale-110 transition-transform">👻</div>
                                <div class="font-bold text-sm text-black dark:text-white">Aave V3</div>
                                <div class="text-xs text-green-600 dark:text-green-400 font-medium">$2.8B TVL</div>
                                <div class="text-xs text-gray-500 mt-1">✅ Top Lending</div>
                            </button>
                            <button 
                                @click="analyzeQuickContract('0xbEbc44782C7dB0a1A60Cb6fe97d0b483032FF1C7', 'Curve 3Pool')"
                                class="bg-white dark:bg-zinc-800 rounded-xl p-5 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border-2 border-green-200 dark:border-green-700 hover:border-green-400 dark:hover:border-green-500 group"
                                title="Click to analyze Curve 3Pool contract instantly"
                            >
                                <div class="text-3xl mb-2 group-hover:scale-110 transition-transform">🌊</div>
                                <div class="font-bold text-sm text-black dark:text-white">Curve 3Pool</div>
                                <div class="text-xs text-green-600 dark:text-green-400 font-medium">$1.2B TVL</div>
                                <div class="text-xs text-gray-500 mt-1">✅ Stablecoin DEX</div>
                            </button>
                            <button 
                                @click="analyzeQuickContract('0x27182842E098f60e3D576794A5bFFb0777E025d3', 'Euler Finance')"
                                class="bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-xl p-5 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border-2 border-red-300 dark:border-red-700 hover:border-red-500 dark:hover:border-red-500 group"
                                title="Educational: Analyze the Euler Finance exploit case study"
                            >
                                <div class="text-3xl mb-2 group-hover:scale-110 transition-transform group-hover:animate-pulse">🚨</div>
                                <div class="font-bold text-sm text-red-700 dark:text-red-300">Euler Finance</div>
                                <div class="text-xs text-red-600 dark:text-red-400 font-medium">$197M Loss</div>
                                <div class="text-xs text-red-500 mt-1">🚨 Exploited 2023</div>
                            </button>
                            <button 
                                @click="analyzeQuickContract('0x0000000000000000000000000000000000001004', 'BSC Token Hub')"
                                class="bg-gradient-to-br from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 rounded-xl p-5 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border-2 border-red-300 dark:border-red-700 hover:border-red-500 dark:hover:border-red-500 group"
                                title="Educational: Analyze the largest DeFi exploit in history"
                            >
                                <div class="text-3xl mb-2 group-hover:scale-110 transition-transform group-hover:animate-pulse">💥</div>
                                <div class="font-bold text-sm text-red-700 dark:text-red-300">BSC Hub</div>
                                <div class="text-xs text-red-600 dark:text-red-400 font-medium">$570M Loss</div>
                                <div class="text-xs text-red-500 mt-1">💥 Biggest Exploit</div>
                            </button>
                        </div>
                        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                            💡 One-click analysis: Just click any contract above to see instant security analysis results!
                        </div>
                    </div>

                    <!-- Features Section -->
                    <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
                        <div class="flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] dark:bg-zinc-900 dark:ring-zinc-800">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-blue-500/10 sm:size-16">
                                <span class="text-2xl">🔒</span>
                            </div>
                            <div class="pt-3 sm:pt-5">
                                <h3 class="text-xl font-semibold text-black dark:text-white">Security Analysis</h3>
                                <p class="mt-4 text-sm/relaxed">Advanced vulnerability detection using OWASP standards and AI-powered analysis.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] dark:bg-zinc-900 dark:ring-zinc-800">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-green-500/10 sm:size-16">
                                <span class="text-2xl">⚡</span>
                            </div>
                            <div class="pt-3 sm:pt-5">
                                <h3 class="text-xl font-semibold text-black dark:text-white">Gas Optimization</h3>
                                <p class="mt-4 text-sm/relaxed">Identify gas inefficiencies and receive actionable recommendations to optimize costs.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] dark:bg-zinc-900 dark:ring-zinc-800">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-purple-500/10 sm:size-16">
                                <span class="text-2xl">🌐</span>
                            </div>
                            <div class="pt-3 sm:pt-5">
                                <h3 class="text-xl font-semibold text-black dark:text-white">Multi-Chain Support</h3>
                                <p class="mt-4 text-sm/relaxed">Analyze contracts across Ethereum, Polygon, BSC, Arbitrum, and more.</p>
                            </div>
                        </div>
                    </div>
                </main>

                <footer
                    class="py-16 text-center text-sm text-black dark:text-white/70"
                >
                    Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})
                </footer>
            </div>
        </div>
    </div>
</template>
