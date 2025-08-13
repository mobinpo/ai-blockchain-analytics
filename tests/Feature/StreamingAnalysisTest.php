<?php

namespace Tests\Feature;

use App\Jobs\ProcessOpenAiAnalysis;
use App\Models\Analysis;
use App\Models\Project;
use App\Models\User;
use App\Services\OpenAiStreamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StreamingAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_can_start_streaming_analysis()
    {
        Queue::fake();
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/streaming/analysis/start', [
                'project_id' => $this->project->id,
                'code' => 'pragma solidity ^0.8.0; contract Test { uint256 public value; }',
                'analysis_type' => 'security',
                'model' => 'gpt-4',
                'max_tokens' => 1000,
                'temperature' => 0.7
            ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'analysis_id',
                    'job_id',
                    'stream_channel',
                    'status_url',
                    'message'
                ]);

        Queue::assertPushed(ProcessOpenAiAnalysis::class);
        
        $this->assertDatabaseHas('analyses', [
            'project_id' => $this->project->id,
            'engine' => 'openai',
            'status' => 'pending',
            'analysis_type' => 'security',
            'openai_model' => 'gpt-4',
            'token_limit' => 1000,
            'temperature' => 0.7
        ]);
    }

    public function test_can_get_analysis_status()
    {
        $analysis = Analysis::factory()->create([
            'project_id' => $this->project->id,
            'engine' => 'openai',
            'status' => 'processing',
            'triggered_by_user_id' => $this->user->id,
            'job_id' => 'test-job-id',
            'streaming_started_at' => now()->subMinutes(2),
            'tokens_streamed' => 150,
            'token_limit' => 1000
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/streaming/analysis/{$analysis->id}/status");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'analysis' => [
                        'id',
                        'status',
                        'progress',
                        'duration',
                        'tokens_streamed',
                        'token_limit',
                        'model'
                    ],
                    'has_results',
                    'summary'
                ]);

        $responseData = $response->json();
        $this->assertEquals($analysis->id, $responseData['analysis']['id']);
        $this->assertEquals('processing', $responseData['analysis']['status']);
        $this->assertEquals(15.0, $responseData['analysis']['progress']); // 150/1000 * 100
    }

    public function test_can_get_completed_analysis_results()
    {
        $structuredResult = [
            'summary' => 'Test analysis summary',
            'findings' => [
                ['severity' => 'high', 'description' => 'Test finding'],
            ],
            'recommendations' => ['Test recommendation'],
            'risk_score' => 75
        ];

        $analysis = Analysis::factory()->create([
            'project_id' => $this->project->id,
            'engine' => 'openai',
            'status' => 'completed',
            'triggered_by_user_id' => $this->user->id,
            'structured_result' => $structuredResult,
            'raw_openai_response' => 'Test OpenAI response',
            'tokens_used' => 500,
            'risk_score' => 75
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/streaming/analysis/{$analysis->id}/results");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'analysis' => [
                        'summary',
                        'findings',
                        'recommendations',
                        'risk_score',
                        'raw_response',
                        'structured_result'
                    ],
                    'performance' => [
                        'tokens_used',
                        'model'
                    ]
                ]);

        $responseData = $response->json();
        $this->assertEquals('Test analysis summary', $responseData['analysis']['summary']);
        $this->assertEquals(75, $responseData['analysis']['risk_score']);
    }

    public function test_cannot_get_results_for_incomplete_analysis()
    {
        $analysis = Analysis::factory()->create([
            'project_id' => $this->project->id,
            'engine' => 'openai',
            'status' => 'processing',
            'triggered_by_user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/streaming/analysis/{$analysis->id}/results");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Analysis not yet completed'
                ]);
    }

    public function test_can_cancel_running_analysis()
    {
        $analysis = Analysis::factory()->create([
            'project_id' => $this->project->id,
            'engine' => 'openai',
            'status' => 'processing',
            'triggered_by_user_id' => $this->user->id,
            'streaming_started_at' => now()->subMinutes(1)
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/streaming/analysis/{$analysis->id}/cancel");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Analysis cancelled successfully'
                ]);

        $analysis->refresh();
        $this->assertEquals('cancelled', $analysis->status);
        $this->assertNotNull($analysis->streaming_completed_at);
        $this->assertEquals('Analysis cancelled by user', $analysis->error_message);
    }

    public function test_cannot_cancel_completed_analysis()
    {
        $analysis = Analysis::factory()->create([
            'project_id' => $this->project->id,
            'engine' => 'openai',
            'status' => 'completed',
            'triggered_by_user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/streaming/analysis/{$analysis->id}/cancel");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cannot cancel completed or failed analysis'
                ]);
    }

    public function test_can_list_user_analyses()
    {
        Analysis::factory()->count(5)->create([
            'project_id' => $this->project->id,
            'engine' => 'openai',
            'triggered_by_user_id' => $this->user->id
        ]);

        // Create analysis for different user (should not appear)
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        Analysis::factory()->create([
            'project_id' => $otherProject->id,
            'triggered_by_user_id' => $otherUser->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/streaming/analysis');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'analyses',
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total'
                    ]
                ]);

        $responseData = $response->json();
        $this->assertCount(5, $responseData['analyses']);
        $this->assertEquals(5, $responseData['pagination']['total']);
    }

    public function test_can_filter_analyses_by_status()
    {
        Analysis::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'completed',
            'triggered_by_user_id' => $this->user->id
        ]);

        Analysis::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'processing',
            'triggered_by_user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/streaming/analysis?status=completed');

        $response->assertStatus(200);
        
        $responseData = $response->json();
        $this->assertCount(1, $responseData['analyses']);
        $this->assertEquals('completed', $responseData['analyses'][0]['status']);
    }

    public function test_openai_stream_service_job_id_tracking()
    {
        $streamService = new OpenAiStreamService();
        
        // Mock the stream status
        $jobId = 'test-job-123';
        $status = $streamService->getStreamStatus($jobId);
        
        // Should return null for non-existent job
        $this->assertNull($status);
    }

    public function test_analysis_model_streaming_methods()
    {
        $analysis = new Analysis([
            'streaming_started_at' => now()->subMinutes(5),
            'streaming_completed_at' => now(),
            'tokens_streamed' => 150,
            'token_limit' => 1000,
            'structured_result' => [
                'summary' => 'Test summary',
                'findings' => [['severity' => 'high']],
                'recommendations' => ['Test rec']
            ]
        ]);

        $this->assertFalse($analysis->isStreaming());
        $this->assertEquals(15.0, $analysis->getStreamingProgress());
        $this->assertEquals(300, $analysis->getStreamingDuration());
        $this->assertEquals('Test summary', $analysis->getResultSummary());
        $this->assertCount(1, $analysis->getSecurityFindings());
        $this->assertCount(1, $analysis->getRecommendations());
    }
}