<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ContractAnalysis;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnalysisProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly string $status,
        public readonly array $progressData = [],
        public readonly ?ContractAnalysis $analysis = null,
        public readonly ?int $progress = null,
        public readonly ?string $message = null,
        public readonly array $metadata = []
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('openai-progress.' . $this->jobId),
            new Channel('openai-progress-global')
        ];

        // Add analysis-specific channels if analysis exists
        if ($this->analysis) {
            $channels[] = new Channel('contract-analysis');
            $channels[] = new Channel("analysis.{$this->analysis->id}");
            $channels[] = new PrivateChannel("user.{$this->analysis->user_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'analysis.progress';
    }

    public function broadcastWith(): array
    {
        $data = [
            'job_id' => $this->jobId,
            'status' => $this->status,
            'progress_data' => $this->progressData,
            'progress' => $this->progress,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString()
        ];

        // Add analysis data if available
        if ($this->analysis) {
            $data['analysis_id'] = $this->analysis->id;
            $data['contract_address'] = $this->analysis->contract_address;
            $data['network'] = $this->analysis->network;
            $data['user_id'] = $this->analysis->user_id;
        }

        return $data;
    }
}