<?php

declare(strict_types=1);

namespace App\Services;

use OpenAI\Client as OpenAiClient;

final class OpenAiAuditService
{
    public function __construct(private readonly OpenAiClient $client)
    {
        // The client is automatically resolved via Laravel's service container.
    }

    /**
     * Run an AI-powered audit on blockchain transaction data.
     */
    public function auditTransaction(string $transactionData): array
    {
        return $this->client->chat()->create([
            'model' => 'gpt-4o-mini', // You can make this configurable.
            'messages' => [
                ['role' => 'system', 'content' => 'You are a blockchain audit assistant.'],
                ['role' => 'user', 'content' => $transactionData],
            ],
        ])->toArray();
    }
} 