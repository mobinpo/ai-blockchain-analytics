<?php

namespace App\Services;

use Google\Cloud\Language\V1\LanguageServiceClient;

class GoogleSentimentService
{
    public function __construct(private readonly LanguageServiceClient $client)
    {
    }

    /**
     * Analyze sentiment of given text.
     */
    public function analyze(string $text): array
    {
        $response = $this->client->analyzeSentiment($text);
        return $response->info();
    }
} 