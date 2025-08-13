/**
 * Automated Video Demo Sequence Script
 * Run this in browser console to automate the demo for perfect video timing
 * 
 * Usage:
 * 1. Open http://localhost:8003 in browser
 * 2. Open Developer Console (F12)
 * 3. Paste this script and run
 * 4. Start recording and run: runVideoDemo()
 */

// Video demo sequence controller
class VideoDemoSequence {
    constructor() {
        this.currentStep = 0;
        this.isRunning = false;
        this.steps = [
            { name: 'Landing Page Introduction', duration: 3000, action: 'showLandingPage' },
            { name: 'Highlight One-Click Analyzer', duration: 2000, action: 'highlightAnalyzer' },
            { name: 'Show Famous Contracts', duration: 3000, action: 'showFamousContracts' },
            { name: 'Click Uniswap V3', duration: 1000, action: 'clickUniswap' },
            { name: 'Show Analysis Loading', duration: 2000, action: 'showLoading' },
            { name: 'Display Results', duration: 8000, action: 'showResults' },
            { name: 'Navigate to Dashboard', duration: 2000, action: 'goToDashboard' },
            { name: 'Show Advanced Features', duration: 10000, action: 'showAdvancedFeatures' },
            { name: 'Return to Landing', duration: 2000, action: 'returnToLanding' },
            { name: 'Click Euler Finance', duration: 1000, action: 'clickEuler' },
            { name: 'Show Exploit Warning', duration: 5000, action: 'showExploitWarning' },
            { name: 'Final Call to Action', duration: 3000, action: 'showCallToAction' }
        ];
    }

    // Main demo runner
    async runVideoDemo() {
        if (this.isRunning) {
            console.log('🚫 Demo already running');
            return;
        }

        this.isRunning = true;
        console.log('🎬 Starting Video Demo Sequence...');
        console.log('📋 Total duration: ~42 seconds (perfect for 2-min video with narration)');
        
        for (let i = 0; i < this.steps.length; i++) {
            const step = this.steps[i];
            console.log(`🎯 Step ${i + 1}/${this.steps.length}: ${step.name} (${step.duration}ms)`);
            
            try {
                await this[step.action]();
                await this.wait(step.duration);
            } catch (error) {
                console.error(`❌ Error in step ${i + 1}:`, error);
            }
        }
        
        this.isRunning = false;
        console.log('🎉 Video demo sequence completed!');
    }

    // Helper function for delays
    wait(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Demo actions
    async showLandingPage() {
        console.log('📍 Showing landing page introduction');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        this.highlightElement('.text-4xl', 'Hero headline');
    }

    async highlightAnalyzer() {
        console.log('🔍 Highlighting one-click analyzer');
        const analyzer = document.querySelector('.live-contract-analyzer');
        if (analyzer) {
            analyzer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            this.highlightElement('input[placeholder*="contract address"]', 'Input field');
        }
    }

    async showFamousContracts() {
        console.log('🏆 Showing famous contracts section');
        const famousSection = document.querySelector('h3:contains("Famous Contracts")');
        if (famousSection) {
            famousSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Hover over contracts to show interactivity
        const contracts = document.querySelectorAll('button[onclick*="analyzeQuickContract"]');
        for (let contract of contracts) {
            contract.style.transform = 'scale(1.05)';
            await this.wait(200);
            contract.style.transform = 'scale(1)';
            await this.wait(100);
        }
    }

    async clickUniswap() {
        console.log('🦄 Clicking Uniswap V3 contract');
        const uniswapBtn = document.querySelector('button[onclick*="0xE592427A0AEce92De3Edee1F18E0157C05861564"]');
        if (uniswapBtn) {
            this.highlightElement(uniswapBtn, 'Uniswap V3 button');
            await this.wait(500);
            uniswapBtn.click();
        }
    }

    async showLoading() {
        console.log('⏳ Showing analysis loading state');
        // The loading state should be automatic from the click
        const loadingElement = document.querySelector('.animate-spin');
        if (loadingElement) {
            this.highlightElement(loadingElement, 'Loading spinner');
        }
    }

    async showResults() {
        console.log('📊 Displaying analysis results');
        await this.wait(1000); // Wait for results to load
        
        // Scroll through results
        const resultsSection = document.querySelector('.analysis-results');
        if (resultsSection) {
            resultsSection.scrollIntoView({ behavior: 'smooth' });
            
            // Highlight key metrics
            const riskScore = document.querySelector('.risk-score');
            const findings = document.querySelector('.findings-count');
            const gasOptimization = document.querySelector('.gas-optimization');
            
            if (riskScore) this.highlightElement(riskScore, 'Risk Score');
            await this.wait(1000);
            if (findings) this.highlightElement(findings, 'Security Findings');
            await this.wait(1000);
            if (gasOptimization) this.highlightElement(gasOptimization, 'Gas Optimization');
        }
    }

    async goToDashboard() {
        console.log('📊 Navigating to dashboard');
        const dashboardLink = document.querySelector('a[href*="dashboard"]');
        if (dashboardLink) {
            dashboardLink.click();
        } else {
            window.location.href = '/dashboard';
        }
    }

    async showAdvancedFeatures() {
        console.log('🚀 Showcasing advanced features');
        await this.wait(2000); // Wait for dashboard to load
        
        // Scroll through dashboard sections
        window.scrollTo({ top: 0, behavior: 'smooth' });
        await this.wait(1000);
        
        // Show statistics
        const statsCards = document.querySelectorAll('.stats-card, .metric-card');
        for (let card of statsCards) {
            this.highlightElement(card, 'Metric card');
            await this.wait(500);
        }
        
        // Show charts
        const charts = document.querySelectorAll('.chart-container, canvas');
        for (let chart of charts) {
            chart.scrollIntoView({ behavior: 'smooth', block: 'center' });
            await this.wait(1000);
        }
    }

    async returnToLanding() {
        console.log('🏠 Returning to landing page');
        window.location.href = '/';
        await this.wait(1000);
    }

    async clickEuler() {
        console.log('🚨 Clicking Euler Finance (exploit)');
        const eulerBtn = document.querySelector('button[onclick*="0x27182842E098f60e3D576794A5bFFb0777E025d3"]');
        if (eulerBtn) {
            this.highlightElement(eulerBtn, 'Euler Finance button');
            await this.wait(500);
            eulerBtn.click();
        }
    }

    async showExploitWarning() {
        console.log('⚠️ Showing exploit warning and educational content');
        await this.wait(1000); // Wait for analysis
        
        // Look for exploit-specific content
        const criticalFindings = document.querySelectorAll('.severity-critical, .finding-critical');
        for (let finding of criticalFindings) {
            this.highlightElement(finding, 'Critical finding');
            await this.wait(800);
        }
    }

    async showCallToAction() {
        console.log('🎯 Final call to action');
        const analyzeButton = document.querySelector('button[type="submit"]');
        if (analyzeButton) {
            analyzeButton.scrollIntoView({ behavior: 'smooth', block: 'center' });
            this.highlightElement(analyzeButton, 'Analyze Now button');
        }
        
        // Show input field one more time
        const input = document.querySelector('input[placeholder*="contract address"]');
        if (input) {
            input.focus();
            this.highlightElement(input, 'Contract input field');
        }
    }

    // Utility function to highlight elements
    highlightElement(element, description) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (!element) {
            console.warn(`⚠️ Element not found: ${description}`);
            return;
        }

        console.log(`✨ Highlighting: ${description}`);
        
        // Add highlight effect
        const originalStyle = element.style.cssText;
        element.style.cssText += `
            box-shadow: 0 0 20px 5px rgba(66, 153, 225, 0.6) !important;
            border: 2px solid #4299e1 !important;
            transition: all 0.3s ease !important;
        `;
        
        // Remove highlight after 1 second
        setTimeout(() => {
            element.style.cssText = originalStyle;
        }, 1000);
    }

    // Manual step runner for precise control
    async runStep(stepNumber) {
        if (stepNumber < 1 || stepNumber > this.steps.length) {
            console.error('❌ Invalid step number');
            return;
        }
        
        const step = this.steps[stepNumber - 1];
        console.log(`🎯 Running Step ${stepNumber}: ${step.name}`);
        
        try {
            await this[step.action]();
            console.log(`✅ Step ${stepNumber} completed`);
        } catch (error) {
            console.error(`❌ Step ${stepNumber} failed:`, error);
        }
    }

    // Show available steps
    showSteps() {
        console.log('📋 Available Demo Steps:');
        this.steps.forEach((step, index) => {
            console.log(`${index + 1}. ${step.name} (${step.duration}ms)`);
        });
        console.log('\nUsage:');
        console.log('• runVideoDemo() - Run complete sequence');
        console.log('• runStep(n) - Run specific step');
        console.log('• showSteps() - Show this list');
    }
}

// Initialize demo controller
const videoDemo = new VideoDemoSequence();

// Global functions for easy access
window.runVideoDemo = () => videoDemo.runVideoDemo();
window.runStep = (n) => videoDemo.runStep(n);
window.showSteps = () => videoDemo.showSteps();

// Auto-announce when script is loaded
console.log('🎬 Video Demo Sequence Loaded!');
console.log('📋 Available commands:');
console.log('• runVideoDemo() - Start complete demo sequence');
console.log('• runStep(n) - Run individual step (1-12)');
console.log('• showSteps() - List all available steps');
console.log('');
console.log('🎯 Ready to record! Start your screen recorder and run: runVideoDemo()');

// Pre-flight check
setTimeout(() => {
    const input = document.querySelector('input[placeholder*="contract address"]');
    const famousContracts = document.querySelectorAll('button[onclick*="analyzeQuickContract"]');
    
    if (input && famousContracts.length >= 5) {
        console.log('✅ Platform ready for video recording!');
        console.log(`🏆 Found ${famousContracts.length} famous contracts`);
        console.log('🔍 Live analyzer input field detected');
    } else {
        console.warn('⚠️ Platform may not be fully ready');
        console.log('🔄 Try refreshing the page');
    }
}, 2000);
