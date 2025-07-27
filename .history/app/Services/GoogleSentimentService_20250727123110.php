<?php

namespace App\Services;

use Google\Cloud\Language\LanguageClient;

class GoogleSentimentService
{
    public function __construct(private readonly LanguageClient $client)
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