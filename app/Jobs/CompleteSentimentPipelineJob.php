<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\CrawlerSentimentIntegration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CompleteSentimentPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes
    public int $tries = 3;
    public int $backoff = 300; // 5 minutes between retries

    public function __construct(
        public array $pipelineConfig,
        public string $pipelineId = '',
        public bool $sendNotifications = true
    ) {
        $this->pipelineId = $pipelineId ?: 'job_' . uniqid();
    }

    /**
     * Execute the complete sentiment pipeline job
     */
    public function handle(CrawlerSentimentIntegration $integration): void
    {
        Log::info('Starting complete sentiment pipeline job', [
            'pipeline_id' => $this->pipelineId,
            'config' => $this->pipelineConfig,
            'queue' => $this->queue
        ]);

        try {
            $startTime = microtime(true);
            
            // Execute the complete pipeline
            $results = $integration->executePipeline($this->pipelineConfig);
            
            $duration = microtime(true) - $startTime;
            
            Log::info('Sentiment pipeline job completed', [
                'pipeline_id' => $this->pipelineId,
                'duration_seconds' => round($duration, 2),
                'status' => $results['status'],
                'summary' => $results['summary']
            ]);
            
            // Send notifications if enabled
            if ($this->sendNotifications) {
                $this->sendCompletionNotifications($results);
            }
            
            // Store results for later retrieval
            $this->storeResults($results);
            
        } catch (\Exception $e) {
            Log::error('Sentiment pipeline job failed', [
                'pipeline_id' => $this->pipelineId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Send failure notifications
            if ($this->sendNotifications) {
                $this->sendFailureNotifications($e);
            }
            
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Sentiment pipeline job failed permanently', [
            'pipeline_id' => $this->pipelineId,
            'attempts' => $this->attempts(),
            'exception' => $exception->getMessage()
        ]);
        
        if ($this->sendNotifications) {
            $this->sendFailureNotifications($exception);
        }
    }

    /**
     * Send completion notifications
     */
    private function sendCompletionNotifications(array $results): void
    {
        try {
            // Email notification (if configured)
            if (config('crawler_microservice.monitoring.alerts.channels.email')) {
                \Mail::raw(
                    $this->formatCompletionEmail($results),
                    function ($message) {
                        $message->to(config('crawler_microservice.monitoring.alerts.channels.email'))
                               ->subject("Sentiment Pipeline Completed - {$this->pipelineId}");
                    }
                );
            }
            
            // Slack notification (if configured)
            $slackWebhook = config('crawler_microservice.monitoring.alerts.channels.slack');
            if ($slackWebhook) {
                $this->sendSlackNotification($slackWebhook, $results);
            }
            
            // Discord notification (if configured)
            $discordWebhook = config('crawler_microservice.monitoring.alerts.channels.discord');
            if ($discordWebhook) {
                $this->sendDiscordNotification($discordWebhook, $results);
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to send pipeline notifications', [
                'pipeline_id' => $this->pipelineId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send failure notifications
     */
    private function sendFailureNotifications(\Throwable $exception): void
    {
        try {
            $message = "🚨 Sentiment Pipeline Failed\n\n";
            $message .= "Pipeline ID: {$this->pipelineId}\n";
            $message .= "Error: {$exception->getMessage()}\n";
            $message .= "Time: " . now()->toDateTimeString() . "\n";
            $message .= "Attempts: {$this->attempts()}/{$this->tries}\n";
            
            // Email notification
            if (config('crawler_microservice.monitoring.alerts.channels.email')) {
                \Mail::raw($message, function ($mail) {
                    $mail->to(config('crawler_microservice.monitoring.alerts.channels.email'))
                         ->subject("🚨 Sentiment Pipeline Failed - {$this->pipelineId}");
                });
            }
            
            // Slack notification
            $slackWebhook = config('crawler_microservice.monitoring.alerts.channels.slack');
            if ($slackWebhook) {
                \Http::post($slackWebhook, [
                    'text' => $message,
                    'channel' => '#alerts',
                    'username' => 'Sentiment Pipeline Bot',
                    'icon_emoji' => ':warning:'
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send failure notifications', [
                'pipeline_id' => $this->pipelineId,
                'notification_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Format completion email content
     */
    private function formatCompletionEmail(array $results): string
    {
        $summary = $results['summary'] ?? [];
        $status = $results['status'] ?? 'unknown';
        
        $statusEmoji = match($status) {
            'completed' => '✅',
            'completed_with_errors' => '⚠️',
            'failed' => '❌',
            default => '⚪'
        };
        
        $email = "{$statusEmoji} Sentiment Pipeline Completed\n\n";
        $email .= "Pipeline ID: {$this->pipelineId}\n";
        $email .= "Status: {$status}\n";
        $email .= "Completion Time: " . now()->toDateTimeString() . "\n\n";
        
        $email .= "📊 Summary:\n";
        $email .= "- Posts Collected: " . number_format($summary['total_posts_collected'] ?? 0) . "\n";
        $email .= "- Keyword Matches: " . number_format($summary['keyword_matches'] ?? 0) . "\n";
        $email .= "- Documents Analyzed: " . number_format($summary['documents_analyzed'] ?? 0) . "\n";
        $email .= "- Daily Aggregates: " . number_format($summary['daily_aggregates_created'] ?? 0) . "\n";
        $email .= "- Success Rate: " . ($summary['success_rate'] ?? 0) . "%\n";
        $email .= "- Data Quality Score: " . ($summary['data_quality_score'] ?? 0) . "%\n\n";
        
        if (!empty($summary['platforms_processed'])) {
            $email .= "🌐 Platforms: " . implode(', ', $summary['platforms_processed']) . "\n";
        }
        
        $email .= "\n⏱️ Processing Times:\n";
        $processingChain = $summary['processing_chain'] ?? [];
        foreach ($processingChain as $phase => $duration) {
            $email .= "- " . ucfirst(str_replace('_', ' ', $phase)) . ": " . number_format($duration) . "ms\n";
        }
        
        $email .= "\nTotal Duration: " . number_format($results['total_duration_ms'] ?? 0) . "ms\n";
        
        return $email;
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification(string $webhook, array $results): void
    {
        $summary = $results['summary'] ?? [];
        $status = $results['status'] ?? 'unknown';
        
        $color = match($status) {
            'completed' => 'good',
            'completed_with_errors' => 'warning',
            'failed' => 'danger',
            default => '#808080'
        };
        
        $payload = [
            'attachments' => [
                [
                    'color' => $color,
                    'title' => "Sentiment Pipeline - {$status}",
                    'fields' => [
                        [
                            'title' => 'Pipeline ID',
                            'value' => $this->pipelineId,
                            'short' => true
                        ],
                        [
                            'title' => 'Posts Collected',
                            'value' => number_format($summary['total_posts_collected'] ?? 0),
                            'short' => true
                        ],
                        [
                            'title' => 'Documents Analyzed',
                            'value' => number_format($summary['documents_analyzed'] ?? 0),
                            'short' => true
                        ],
                        [
                            'title' => 'Success Rate',
                            'value' => ($summary['success_rate'] ?? 0) . '%',
                            'short' => true
                        ]
                    ],
                    'footer' => 'Sentiment Pipeline Bot',
                    'ts' => time()
                ]
            ]
        ];
        
        \Http::post($webhook, $payload);
    }

    /**
     * Send Discord notification
     */
    private function sendDiscordNotification(string $webhook, array $results): void
    {
        $summary = $results['summary'] ?? [];
        $status = $results['status'] ?? 'unknown';
        
        $statusEmoji = match($status) {
            'completed' => '✅',
            'completed_with_errors' => '⚠️',
            'failed' => '❌',
            default => '⚪'
        };
        
        $embed = [
            'title' => "{$statusEmoji} Sentiment Pipeline {$status}",
            'description' => "Pipeline ID: `{$this->pipelineId}`",
            'color' => match($status) {
                'completed' => 0x00ff00,
                'completed_with_errors' => 0xffff00,
                'failed' => 0xff0000,
                default => 0x808080
            },
            'fields' => [
                [
                    'name' => 'Posts Collected',
                    'value' => number_format($summary['total_posts_collected'] ?? 0),
                    'inline' => true
                ],
                [
                    'name' => 'Documents Analyzed',
                    'value' => number_format($summary['documents_analyzed'] ?? 0),
                    'inline' => true
                ],
                [
                    'name' => 'Success Rate',
                    'value' => ($summary['success_rate'] ?? 0) . '%',
                    'inline' => true
                ]
            ],
            'timestamp' => now()->toISOString(),
            'footer' => [
                'text' => 'Sentiment Pipeline Bot'
            ]
        ];
        
        \Http::post($webhook, [
            'embeds' => [$embed]
        ]);
    }

    /**
     * Store pipeline results for later retrieval
     */
    private function storeResults(array $results): void
    {
        try {
            \Cache::put(
                "pipeline_results_{$this->pipelineId}",
                $results,
                now()->addDays(7) // Keep results for 7 days
            );
            
            Log::info('Pipeline results stored in cache', [
                'pipeline_id' => $this->pipelineId,
                'cache_key' => "pipeline_results_{$this->pipelineId}"
            ]);
            
        } catch (\Exception $e) {
            Log::warning('Failed to store pipeline results', [
                'pipeline_id' => $this->pipelineId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'sentiment-pipeline',
            'pipeline:' . $this->pipelineId,
            'type:complete-pipeline'
        ];
    }
}