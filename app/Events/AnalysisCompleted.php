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

class AnalysisCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ContractAnalysis $analysis
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
        return 'analysis.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'analysis_id' => $this->analysis->id,
            'contract_address' => $this->analysis->contract_address,
            'network' => $this->analysis->network,
            'status' => $this->analysis->status,
            'findings_count' => $this->analysis->findings_count,
            'severity_counts' => $this->analysis->getSeverityCounts(),
            'risk_score' => $this->analysis->getRiskScore(),
            'categories' => $this->analysis->getUniqueCategories(),
            'tokens_used' => $this->analysis->tokens_used,
            'processing_time' => $this->analysis->getProcessingTimeFormatted(),
            'completed_at' => $this->analysis->completed_at?->toISOString(),
            'duration' => $this->analysis->getDuration(),
            'user_id' => $this->analysis->user_id,
            'summary' => $this->analysis->getAnalysisSummary()
        ];
    }
}