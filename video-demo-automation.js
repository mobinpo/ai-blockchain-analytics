// AI Blockchain Analytics - 2-Minute Promo Video Automation Script
// Copy and paste this into your browser console to automate the demo

class PromoVideoDemo {
    constructor() {
        this.currentStep = 0;
        this.steps = [
            { name: 'Landing Page', duration: 3000, action: () => this.showLandingPage() },
            { name: 'Scroll to Analyzer', duration: 2000, action: () => this.scrollToAnalyzer() },
            { name: 'Click Uniswap Demo', duration: 3000, action: () => this.clickUniswapDemo() },
            { name: 'Show Analysis Results', duration: 4000, action: () => this.showAnalysisResults() },
            { name: 'Navigate to Dashboard', duration: 3000, action: () => this.navigateToDashboard() },
            { name: 'Show Platform Stats', duration: 3000, action: () => this.showPlatformStats() },
            { name: 'Demo Famous Contracts', duration: 4000, action: () => this.demoFamousContracts() },
            { name: 'Show Exploit Examples', duration: 4000, action: () => this.showExploitExamples() },
            { name: 'Return to Landing', duration: 2000, action: () => this.returnToLanding() },
            { name: 'Final Call to Action', duration: 3000, action: () => this.finalCallToAction() }
        ];
        
        this.totalDuration = this.steps.reduce((sum, step) => sum + step.duration, 0);
        console.log(`🎬 PromoVideoDemo initialized - Total duration: ${this.totalDuration/1000}s`);
    }

    async runDemo() {
        console.log('🚀 Starting AI Blockchain Analytics 2-Minute Promo Demo...');
        console.log('📹 Start your screen recording NOW!');
        
        // Countdown before starting
        for (let i = 3; i > 0; i--) {
            console.log(`⏰ Starting in ${i}...`);
            await this.sleep(1000);
        }
        
        console.log('🎬 ACTION! Demo started...');
        
        for (let i = 0; i < this.steps.length; i++) {
            const step = this.steps[i];
            console.log(`📋 Step ${i + 1}/${this.steps.length}: ${step.name} (${step.duration/1000}s)`);
            
            try {
                await step.action();
                await this.sleep(step.duration);
            } catch (error) {
                console.warn(`⚠️ Step ${step.name} had issues:`, error.message);
            }
        }
        
        console.log('🎉 Demo completed! Stop your recording.');
        console.log('📊 Total demo time:', Math.round(this.totalDuration/1000), 'seconds');
    }

    async showLandingPage() {
        // Ensure we're on the landing page
        if (window.location.pathname !== '/') {
            window.location.href = '/';
            await this.sleep(1000);
        }
        
        // Add visual indicator for recording
        this.showRecordingIndicator('Landing Page - AI Blockchain Analytics');
        
        // Smooth scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        console.log('📍 Showing landing page with prominent analyzer');
    }

    async scrollToAnalyzer() {
        // Find the live contract analyzer section
        const analyzer = document.querySelector('.live-contract-analyzer') || 
                        document.querySelector('[class*="analyzer"]') ||
                        document.querySelector('input[placeholder*="contract"]');
        
        if (analyzer) {
            analyzer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            this.highlightElement(analyzer);
        }
        
        this.showRecordingIndicator('One-Click Contract Analyzer');
        console.log('🔍 Scrolled to live contract analyzer');
    }

    async clickUniswapDemo() {
        // Try to find and click the Uniswap demo button
        const uniswapButton = this.findElement([
            'button*=Uniswap',
            'button*=uniswap', 
            '[title*="Uniswap"]',
            'button*=V3',
            '.famous-contract*=Uniswap'
        ]);
        
        if (uniswapButton) {
            this.highlightElement(uniswapButton);
            await this.sleep(500);
            uniswapButton.click();
            console.log('🦄 Clicked Uniswap V3 demo button');
        } else {
            // Fallback: manually fill the analyzer
            const input = document.querySelector('input[placeholder*="contract"]') ||
                         document.querySelector('input[type="text"]');
            if (input) {
                input.focus();
                input.value = '0xE592427A0AEce92De3Edee1F18E0157C05861564';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                
                // Try to find and click analyze button
                await this.sleep(500);
                const analyzeBtn = this.findElement([
                    'button*=Analyze',
                    'button*=analyze',
                    'button[type="submit"]',
                    '.cta-button'
                ]);
                if (analyzeBtn) analyzeBtn.click();
                
                console.log('🔍 Manually filled Uniswap address and triggered analysis');
            }
        }
        
        this.showRecordingIndicator('Analyzing Uniswap V3 Router - $3.5B TVL');
    }

    async showAnalysisResults() {
        // Wait for results to appear and highlight them
        await this.sleep(1000);
        
        const results = this.findElement([
            '.analysis-results',
            '.risk-score',
            '[class*="result"]',
            '[class*="analysis"]'
        ]);
        
        if (results) {
            this.highlightElement(results);
        }
        
        this.showRecordingIndicator('Analysis Complete - Risk Score & Vulnerabilities');
        console.log('📊 Showing analysis results with risk scores');
    }

    async navigateToDashboard() {
        // Try to navigate to dashboard
        const dashboardLink = this.findElement([
            'a[href*="dashboard"]',
            'a*=Dashboard',
            'nav a*=Dashboard',
            '.nav-link*=Dashboard'
        ]);
        
        if (dashboardLink) {
            dashboardLink.click();
            await this.sleep(1000);
        } else {
            // Fallback: navigate manually
            window.location.href = '/dashboard';
            await this.sleep(1500);
        }
        
        this.showRecordingIndicator('Platform Dashboard - Real-time Analytics');
        console.log('📊 Navigated to dashboard');
    }

    async showPlatformStats() {
        // Highlight statistics and metrics
        const stats = document.querySelectorAll('[class*="stat"], [class*="metric"], .card, .widget');
        
        stats.forEach((stat, index) => {
            setTimeout(() => this.highlightElement(stat, 1000), index * 300);
        });
        
        this.showRecordingIndicator('Live Statistics - 15,200+ Contracts Analyzed');
        console.log('📈 Highlighting platform statistics');
    }

    async demoFamousContracts() {
        // Try to show famous contracts section
        const famousSection = this.findElement([
            '.famous-contracts',
            '[class*="famous"]',
            '.contract-examples',
            '.demo-contracts'
        ]);
        
        if (famousSection) {
            famousSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            this.highlightElement(famousSection);
        }
        
        // Click through a few contracts
        const contractButtons = document.querySelectorAll('button*=Aave, button*=Curve, button*=contract');
        contractButtons.forEach((btn, index) => {
            if (index < 3) {
                setTimeout(() => {
                    this.highlightElement(btn, 800);
                    if (index === 1) btn.click(); // Click one for demo
                }, index * 1000);
            }
        });
        
        this.showRecordingIndicator('Famous Contracts - Aave V3, Curve 3Pool');
        console.log('🏆 Demoing famous contracts (Aave, Curve)');
    }

    async showExploitExamples() {
        // Highlight exploit examples
        const exploitButtons = this.findElement([
            'button*=Euler',
            'button*=BSC',
            '.exploit',
            '[class*="exploit"]'
        ]);
        
        if (exploitButtons) {
            this.highlightElement(exploitButtons);
            await this.sleep(800);
            exploitButtons.click();
        }
        
        this.showRecordingIndicator('Learning from Exploits - $197M Euler, $570M BSC');
        console.log('🚨 Showing exploit examples (Euler Finance, BSC)');
    }

    async returnToLanding() {
        // Return to landing page
        window.location.href = '/';
        await this.sleep(1000);
        
        // Scroll to the analyzer section
        const analyzer = document.querySelector('.live-contract-analyzer') || 
                        document.querySelector('input[placeholder*="contract"]');
        if (analyzer) {
            analyzer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        this.showRecordingIndicator('Try Now - No Registration Required');
        console.log('🏠 Returned to landing page');
    }

    async finalCallToAction() {
        // Highlight the main CTA elements
        const ctaElements = this.findElements([
            'button*=Analyze',
            'button*=Try',
            '.cta-button',
            '[class*="cta"]'
        ]);
        
        ctaElements.forEach((element, index) => {
            setTimeout(() => this.highlightElement(element, 1000), index * 500);
        });
        
        // Show final stats
        const stats = document.querySelectorAll('[class*="stat"]');
        stats.forEach((stat, index) => {
            setTimeout(() => this.highlightElement(stat, 500), index * 200);
        });
        
        this.showRecordingIndicator('Join 15,000+ Developers - Secure Your Contracts Today!');
        console.log('🎯 Final call to action with highlighted CTAs');
    }

    // Utility functions
    findElement(selectors) {
        for (const selector of selectors) {
            let element;
            if (selector.includes('*=')) {
                const [tag, text] = selector.split('*=');
                element = Array.from(document.querySelectorAll(tag)).find(el => 
                    el.textContent.toLowerCase().includes(text.toLowerCase())
                );
            } else {
                element = document.querySelector(selector);
            }
            if (element) return element;
        }
        return null;
    }

    findElements(selectors) {
        const elements = [];
        for (const selector of selectors) {
            if (selector.includes('*=')) {
                const [tag, text] = selector.split('*=');
                const found = Array.from(document.querySelectorAll(tag)).filter(el => 
                    el.textContent.toLowerCase().includes(text.toLowerCase())
                );
                elements.push(...found);
            } else {
                elements.push(...document.querySelectorAll(selector));
            }
        }
        return elements;
    }

    highlightElement(element, duration = 2000) {
        if (!element) return;
        
        const originalStyle = element.style.cssText;
        element.style.cssText += `
            outline: 3px solid #3B82F6 !important;
            outline-offset: 2px !important;
            box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.3) !important;
            transition: all 0.3s ease !important;
            z-index: 9999 !important;
            position: relative !important;
        `;
        
        setTimeout(() => {
            element.style.cssText = originalStyle;
        }, duration);
    }

    showRecordingIndicator(text) {
        // Remove existing indicator
        const existing = document.getElementById('demo-indicator');
        if (existing) existing.remove();
        
        // Create new indicator
        const indicator = document.createElement('div');
        indicator.id = 'demo-indicator';
        indicator.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #3B82F6, #8B5CF6);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: fadeIn 0.3s ease;
        `;
        indicator.textContent = text;
        
        // Add fade in animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(indicator);
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Initialize and provide instructions
const demoRunner = new PromoVideoDemo();

console.log('🎬 AI Blockchain Analytics - 2-Minute Promo Video Demo Ready!');
console.log('');
console.log('📋 Instructions:');
console.log('1. Start your screen recording software (OBS, etc.)');
console.log('2. Make sure you are on: http://localhost:8003');
console.log('3. Type: demoRunner.runDemo()');
console.log('4. Press Enter and start recording immediately');
console.log('5. The demo will run automatically for ~2 minutes');
console.log('');
console.log('🎯 Demo will showcase:');
console.log('   • Landing page with one-click analyzer');  
console.log('   • Live contract analysis (Uniswap V3)');
console.log('   • Platform dashboard and statistics');
console.log('   • Famous contracts (Aave, Curve)');
console.log('   • Exploit examples (Euler Finance, BSC)');
console.log('   • Final call to action');
console.log('');
console.log('▶️ Ready? Type: demoRunner.runDemo()');

// Make it globally available
window.demoRunner = demoRunner;
