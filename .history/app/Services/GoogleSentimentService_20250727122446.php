<?php

namespace App\Services;

use Google\Cloud\Language\LanguageClient;

class GoogleSentimentService
{
    protected LanguageClient $client;

    public function __construct()
    {
        $this->client = new LanguageClient([
            'keyFilePath' => config('services.google_language.credentials'),
        ]);
    }

    public function analyze(string $text): array
    {
        $result = $this->client->analyzeSentiment($text);
        return $result->info();
    }
} 