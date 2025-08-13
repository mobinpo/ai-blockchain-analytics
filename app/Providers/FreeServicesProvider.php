<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FreeOllamaService;
use App\Services\FreeSentimentAnalyzer;
use App\Services\FreeCoinDataService;

class FreeServicesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Ollama service as singleton
        $this->app->singleton(FreeOllamaService::class, function ($app) {
            return new FreeOllamaService();
        });

        // Register Free Sentiment Analyzer as singleton
        $this->app->singleton(FreeSentimentAnalyzer::class, function ($app) {
            return new FreeSentimentAnalyzer();
        });

        // Register Free Coin Data Service as singleton
        $this->app->singleton(FreeCoinDataService::class, function ($app) {
            return new FreeCoinDataService();
        });

        // Conditionally replace paid services with free alternatives
        $this->registerConditionalServices();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register conditional services based on feature flags
     */
    private function registerConditionalServices(): void
    {
        // Replace OpenAI with Ollama if enabled
        if (config('free_services.features.replace_openai_with_ollama', false)) {
            $this->app->alias(FreeOllamaService::class, 'openai.service');
        }

        // Replace Google NLP with Free Sentiment if enabled
        if (config('free_services.features.replace_google_nlp_with_free', false)) {
            $this->app->alias(FreeSentimentAnalyzer::class, 'sentiment.service');
        }

        // Replace CoinGecko with Free Crypto Data if enabled
        if (config('free_services.features.use_multiple_free_price_sources', false)) {
            $this->app->alias(FreeCoinDataService::class, 'crypto.data.service');
        }
    }
}
