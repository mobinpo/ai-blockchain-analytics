<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CoinGeckoService;
use App\Http\Controllers\Api\SentimentChartController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DemoSentimentChart extends Command
{
    protected $signature = 'chart:demo-sentiment-price 
                           {--coin=bitcoin : Cryptocurrency to analyze}
                           {--days=30 : Number of days to analyze}
                           {--platform=all : Platform to analyze (twitter,reddit,telegram,all)}
                           {--chart-type=dual : Chart type (line,scatter,dual)}';

    protected $description = 'Demo the sentiment vs price chart with real data';

    public function handle(
        CoinGeckoService $coingeckoService,
        SentimentChartController $chartController
    ): int {
        $coin = $this->option('coin');
        $days = (int) $this->option('days');
        $platform = $this->option('platform');
        $chartType = $this->option('chart-type');

        $this->displayHeader($coin, $days, $platform, $chartType);

        try {
            // Test Coingecko API connectivity
            $this->info('🔗 Testing Coingecko API connectivity...');
            $coins = $coingeckoService->getSupportedCoins();
            $this->line("  ✅ Connected! Found " . count($coins) . " supported cryptocurrencies");

            // Find the selected coin
            $selectedCoin = collect($coins)->firstWhere('id', $coin);
            if (!$selectedCoin) {
                $this->error("❌ Coin '{$coin}' not found. Try: bitcoin, ethereum, cardano, solana");
                return Command::FAILURE;
            }
            
            $this->line("  📊 Selected: {$selectedCoin['name']} ({$selectedCoin['symbol']})");
            $this->newLine();

            // Test price data retrieval
            $this->info('💰 Fetching price data...');
            $endDate = Carbon::today();
            $startDate = Carbon::today()->subDays($days);
            
            $priceData = $coingeckoService->getPriceHistory($coin, $startDate, $endDate);
            $this->line("  ✅ Retrieved " . count($priceData) . " days of price data");
            
            if (!empty($priceData)) {
                $latestPrice = end($priceData);
                $firstPrice = reset($priceData);
                $priceChange = (($latestPrice['price'] - $firstPrice['price']) / $firstPrice['price']) * 100;
                
                $this->line("  📈 Price: $" . number_format($latestPrice['price'], 2));
                $this->line("  📊 {$days}-day change: " . ($priceChange >= 0 ? '+' : '') . number_format($priceChange, 2) . '%');
            }
            $this->newLine();

            // Simulate chart API call
            $this->info('📊 Testing chart API...');
            $request = Request::create('/api/sentiment-charts/data', 'GET', [
                'coin_id' => $coin,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'platforms' => [$platform],
                'include_price' => 'true',
                'include_volume' => 'false'
            ]);

            $response = $chartController->getSentimentPriceData($request);
            $responseData = json_decode($response->getContent(), true);
            
            if ($response->getStatusCode() === 200) {
                $this->line("  ✅ Chart API working correctly");
                $this->displayChartData($responseData, $chartType);
            } else {
                $this->error("  ❌ Chart API error: " . ($responseData['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            $this->error('❌ Demo failed: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error('Stack trace:');
                $this->line($e->getTraceAsString());
            }
            
            return Command::FAILURE;
        }

        $this->displayUsageInstructions($coin);
        return Command::SUCCESS;
    }

    private function displayHeader(string $coin, int $days, string $platform, string $chartType): void
    {
        $this->info('📊 Sentiment vs Price Chart Demo');
        $this->info('Integration: Vue.js + Chart.js + Coingecko API + Google Cloud NLP');
        $this->newLine();

        $this->table(
            ['Setting', 'Value'],
            [
                ['Cryptocurrency', strtoupper($coin)],
                ['Time Period', "{$days} days"],
                ['Platform', ucfirst($platform)],
                ['Chart Type', ucfirst($chartType)],
                ['Currency', 'USD'],
                ['API Provider', 'CoinGecko']
            ]
        );
        $this->newLine();
    }

    private function displayChartData(array $data, string $chartType): void
    {
        $this->newLine();
        $this->info('📈 Chart Data Analysis:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $metadata = $data['metadata'] ?? [];
        $statistics = $data['statistics'] ?? [];
        $correlationData = $data['correlation_data'] ?? [];

        // Metadata summary
        $this->table(
            ['Metric', 'Value'],
            [
                ['Coin ID', $metadata['coin_id'] ?? 'N/A'],
                ['Date Range', ($metadata['start_date'] ?? 'N/A') . ' to ' . ($metadata['end_date'] ?? 'N/A')],
                ['Total Days', $metadata['total_days'] ?? 0],
                ['Platforms', implode(', ', $metadata['platforms'] ?? [])],
                ['Data Points', count($correlationData)]
            ]
        );

        // Statistics (if available)
        if (!empty($statistics)) {
            $this->newLine();
            $this->info('📊 Statistical Analysis:');
            
            $correlation = $statistics['correlation_coefficient'] ?? null;
            $correlationStrength = $statistics['correlation_strength'] ?? 'Unknown';
            $avgSentiment = $statistics['sentiment_stats']['average'] ?? null;
            $avgPriceChange = $statistics['price_stats']['average_change'] ?? null;

            $correlationColor = $this->getCorrelationColor($correlation);
            $sentimentColor = $this->getSentimentColor($avgSentiment);
            $priceColor = $avgPriceChange >= 0 ? 'info' : 'error';

            $this->line("  • Correlation: <{$correlationColor}>" . ($correlation !== null ? number_format($correlation, 3) : 'N/A') . "</{$correlationColor}> ({$correlationStrength})");
            $this->line("  • Avg Sentiment: <{$sentimentColor}>" . ($avgSentiment !== null ? number_format($avgSentiment, 3) : 'N/A') . "</{$sentimentColor}>");
            $this->line("  • Avg Price Change: <{$priceColor}>" . ($avgPriceChange !== null ? number_format($avgPriceChange, 2) . '%' : 'N/A') . "</{$priceColor}>");
        }

        // Sample data points
        if (!empty($correlationData)) {
            $this->newLine();
            $this->info('📅 Sample Data Points:');
            
            $sampleSize = min(5, count($correlationData));
            $samples = array_slice($correlationData, 0, $sampleSize);
            
            $sampleData = [];
            foreach ($samples as $point) {
                $sampleData[] = [
                    'Date' => Carbon::parse($point['date'])->format('M d'),
                    'Sentiment' => number_format($point['sentiment'] ?? 0, 3),
                    'Price Change' => number_format($point['price_change'] ?? 0, 2) . '%',
                    'Posts' => number_format($point['posts'] ?? 0)
                ];
            }
            
            $this->table(['Date', 'Sentiment', 'Price Change', 'Posts'], $sampleData);
        }

        // Chart type explanation
        $this->newLine();
        $this->info("📊 Chart Type: " . ucfirst($chartType));
        $this->line($this->getChartTypeDescription($chartType));
    }

    private function displayUsageInstructions(string $coin): void
    {
        $this->newLine();
        $this->info('🚀 Chart Usage Instructions:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->newLine();
        $this->comment('💻 Web Interface:');
        $this->line('  1. Open: http://localhost:8003/sentiment-analysis/chart');
        $this->line("  2. Select cryptocurrency: {$coin}");
        $this->line('  3. Choose date range and platform filters');
        $this->line('  4. Switch between chart types for different views');

        $this->newLine();
        $this->comment('🔧 API Usage:');
        $this->line('  GET /api/sentiment-charts/data');
        $this->line('  Parameters: coin_id, start_date, end_date, platforms[]');

        $this->newLine();
        $this->comment('📱 Component Usage:');
        $this->line('  <SentimentPriceChart :initial-coin="' . $coin . '" :initial-days="30" />');

        $this->newLine();
        $this->comment('🎯 Try Other Commands:');
        $this->line('  • php artisan chart:demo-sentiment-price --coin=ethereum --days=90');
        $this->line('  • php artisan chart:demo-sentiment-price --coin=cardano --platform=twitter');
        $this->line('  • php artisan chart:demo-sentiment-price --coin=solana --chart-type=scatter');

        $this->newLine();
        $this->info('✨ The sentiment vs price chart component is ready to use!');
    }

    private function getCorrelationColor(?float $correlation): string
    {
        if ($correlation === null) return 'comment';
        
        $abs = abs($correlation);
        if ($abs >= 0.6) return $correlation > 0 ? 'info' : 'error';
        if ($abs >= 0.3) return $correlation > 0 ? 'info' : 'error';
        return 'comment';
    }

    private function getSentimentColor(?float $sentiment): string
    {
        if ($sentiment === null) return 'comment';
        
        if ($sentiment > 0.2) return 'info';
        if ($sentiment < -0.2) return 'error';
        return 'comment';
    }

    private function getChartTypeDescription(string $chartType): string
    {
        return match($chartType) {
            'line' => '  Two line charts with dual Y-axes showing trends over time',
            'scatter' => '  Bubble chart showing correlation with post volume as bubble size',
            'dual' => '  Normalized dual-axis view for direct sentiment vs price comparison',
            default => '  Custom chart configuration'
        };
    }
}