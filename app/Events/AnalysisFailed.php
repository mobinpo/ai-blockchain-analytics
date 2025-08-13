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

class AnalysisFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ContractAnalysis $analysis,
        public readonly string $errorMessage
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('contract-analysis'),
            new Channel("analysis.{$this->analysis->id}"),
            new PrivateChannel("user.{$this->analysis->user_id}")
        ];
    }

    public function broadcastAs(): string
    {
        return 'analysis.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'analysis_id' => $this->analysis->id,
            'contract_address' => $this->analysis->contract_address,
            'network' => $this->analysis->network,
            'status' => $this->analysis->status,
            'error_message' => $this->errorMessage,
            'failed_at' => now()->toISOString(),
            'user_id' => $this->analysis->user_id
        ];
    }
}