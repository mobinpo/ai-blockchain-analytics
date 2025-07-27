<?php

namespace App\Services;

// Using FQN to avoid static analysis issues when the Google SDK may be absent in tooling.
class GoogleSentimentService
{
    private $client;

    public function __construct()
    {
        // Initialize the Google Cloud client with supplied credentials path.
        $clientClass = '\\Google\\Cloud\\Language\\V1\\LanguageServiceClient';
        // Dynamically instantiate to avoid static analysis issues if class is not available.
        $this->client = new $clientClass([
            'credentials' => config('services.google_language.credentials'),
        ]);
    }

    /**
     * Analyze sentiment of given text.
     */
    public function analyze(string $text): array
    {
        // Perform sentiment analysis. Convert protobuf response to associative array.
        $response = $this->client->analyzeSentiment([
            'document' => [
                'content' => $text,
                'type' => 1, // DOCUMENT_TYPE_PLAIN_TEXT
            ],
            'encodingType' => 1, // ENCODING_TYPE_UTF8
        ]);

        return json_decode($response->serializeToJsonString(), true);
    }
} 