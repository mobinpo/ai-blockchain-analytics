<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\FreeOllamaService;
use App\Services\FreeSentimentAnalyzer;
use App\Services\FreeCoinDataService;

class SetupFreeServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'free-services:setup 
                            {--check-only : Only check service availability}
                            {--install-ollama : Install Ollama if not available}
                            {--test-all : Test all free services}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup and verify free API alternatives';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🆓 Setting up Free API Alternatives for Sentiment Shield');
        $this->line('');

        if ($this->option('check-only')) {
            return $this->checkServices();
        }

        if ($this->option('install-ollama')) {
            $this->installOllama();
        }

        if ($this->option('test-all')) {
            return $this->testAllServices();
        }

        $this->setupServices();
    }

    /**
     * Check all service availability
     */
    private function checkServices(): int
    {
        $this->info('🔍 Checking Free Service Availability...');
        $this->line('');

        $services = [
            'Ollama (OpenAI replacement)' => $this->checkOllama(),
            'Free Sentiment Analyzer' => $this->checkFreeSentiment(),
            'CoinCap API' => $this->checkCoinCap(),
            'CoinGecko Free API' => $this->checkCoinGeckoFree(),
            'CryptoCompare Free API' => $this->checkCryptoCompareFree(),
        ];

        $allAvailable = true;

        foreach ($services as $service => $available) {
            $status = $available ? '✅ Available' : '❌ Unavailable';
            $this->line("  {$service}: {$status}");
            
            if (!$available) {
                $allAvailable = false;
            }
        }

        $this->line('');
        
        if ($allAvailable) {
            $this->info('🎉 All free services are available!');
            return 0;
        } else {
            $this->warn('⚠️  Some services are unavailable. Run with --install-ollama to set up Ollama.');
            return 1;
        }
    }

    /**
     * Test all services
     */
    private function testAllServices(): int
    {
        $this->info('🧪 Testing All Free Services...');
        $this->line('');

        $testResults = [
            'Ollama' => $this->testOllama(),
            'Free Sentiment' => $this->testFreeSentiment(),
            'Free Crypto Data' => $this->testFreeCrypto(),
        ];

        foreach ($testResults as $service => $result) {
            $status = $result ? '✅ Working' : '❌ Failed';
            $this->line("  {$service}: {$status}");
        }

        $this->line('');
        $this->info('🔍 Test completed!');
        
        return 0;
    }

    /**
     * Setup all services
     */
    private function setupServices(): void
    {
        $this->info('⚙️  Setting up Free Services...');
        $this->line('');

        // Check and setup Ollama
        if (!$this->checkOllama()) {
            $this->warn('⚠️  Ollama not available. Install with: curl -fsSL https://ollama.ai/install.sh | sh');
            $this->line('   Then run: ollama pull codellama:13b-instruct');
        } else {
            $this->info('✅ Ollama is available');
        }

        // Test sentiment analyzer
        if ($this->checkFreeSentiment()) {
            $this->info('✅ Free Sentiment Analyzer is ready');
        }

        // Test crypto data services
        if ($this->checkCoinCap()) {
            $this->info('✅ CoinCap API is available');
        }

        if ($this->checkCoinGeckoFree()) {
            $this->info('✅ CoinGecko Free API is available');
        }

        $this->line('');
        $this->info('🎉 Free services setup complete!');
        $this->line('');
        
        $this->displayCostSavings();
    }

    /**
     * Install Ollama
     */
    private function installOllama(): void
    {
        if ($this->checkOllama()) {
            $this->info('✅ Ollama is already installed');
            return;
        }

        $this->info('📥 Installing Ollama...');
        
        if ($this->confirm('Install Ollama locally? This will download and install Ollama.')) {
            $this->info('🔄 Installing Ollama...');
            $result = shell_exec('curl -fsSL https://ollama.ai/install.sh | sh');
            
            if ($result !== null) {
                $this->info('✅ Ollama installation completed');
                
                if ($this->confirm('Download CodeLlama model for smart contract analysis? (~7GB)')) {
                    $this->info('📥 Downloading CodeLlama model...');
                    shell_exec('ollama pull codellama:13b-instruct');
                    $this->info('✅ CodeLlama model downloaded');
                }
            } else {
                $this->error('❌ Ollama installation failed');
            }
        }
    }

    /**
     * Check Ollama availability
     */
    private function checkOllama(): bool
    {
        try {
            $service = app(FreeOllamaService::class);
            return $service->isAvailable();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check free sentiment analyzer
     */
    private function checkFreeSentiment(): bool
    {
        try {
            $service = app(FreeSentimentAnalyzer::class);
            return true; // Always available (no external dependencies)
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check CoinCap API
     */
    private function checkCoinCap(): bool
    {
        try {
            $response = Http::timeout(10)->get('https://api.coincap.io/v2/assets/bitcoin');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check CoinGecko Free API
     */
    private function checkCoinGeckoFree(): bool
    {
        try {
            $response = Http::timeout(10)->get('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check CryptoCompare Free API
     */
    private function checkCryptoCompareFree(): bool
    {
        try {
            $response = Http::timeout(10)->get('https://min-api.cryptocompare.com/data/price?fsym=BTC&tsyms=USD');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test Ollama service
     */
    private function testOllama(): bool
    {
        try {
            $service = app(FreeOllamaService::class);
            
            if (!$service->isAvailable()) {
                return false;
            }

            // Test with simple contract
            $result = $service->analyzeSmartContract('pragma solidity ^0.8.0; contract Test {}');
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test free sentiment analyzer
     */
    private function testFreeSentiment(): bool
    {
        try {
            $service = app(FreeSentimentAnalyzer::class);
            $result = $service->analyzeSentiment('This is a great project! I love it.');
            
            return $result['success'] && $result['sentiment_score'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test free crypto data service
     */
    private function testFreeCrypto(): bool
    {
        try {
            $service = app(FreeCoinDataService::class);
            $result = $service->getCurrentPrice('bitcoin');
            
            return $result['success'] && isset($result['data']['price_usd']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Display cost savings information
     */
    private function displayCostSavings(): void
    {
        $this->info('💰 Cost Savings Summary:');
        $this->line('');
        
        $savings = config('free_services.cost_savings.estimated_monthly_savings', []);
        
        foreach ($savings as $service => $amount) {
            if ($service !== 'total_monthly_savings') {
                $serviceName = str_replace('_', ' ', ucwords($service, '_'));
                $this->line("  💵 {$serviceName}: \${$amount}/month saved");
            }
        }
        
        $total = $savings['total_monthly_savings'] ?? 0;
        $this->line('');
        $this->info("🎉 Total Monthly Savings: \${$total}");
        $this->info("🎉 Annual Savings: \$" . ($total * 12));
    }
}
