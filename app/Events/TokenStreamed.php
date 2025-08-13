<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TokenStreamed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly string $token,
        public readonly array $metadata = [],
        public readonly ?string $analysisId = null,
        public readonly ?int $tokenIndex = null,
        public readonly ?string $content = null
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('openai-streaming.' . $this->jobId),
            new Channel('openai-streaming-global')
        ];

        // Add analysis-specific channel if available
        if ($this->analysisId) {
            $channels[] = new Channel("analysis.{$this->analysisId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'token.streamed';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobId,
            'analysis_id' => $this->analysisId,
            'token' => $this->token,
            'token_index' => $this->tokenIndex,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString()
        ];
    }

    public function shouldBroadcast(): bool
    {
        // Only broadcast meaningful tokens (not empty or whitespace only)
        return !empty(trim($this->token));
    }
}