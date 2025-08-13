<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            // OpenAI streaming specific fields
            $table->uuid('job_id')->nullable()->after('id'); // Unique job ID for tracking
            $table->string('openai_model')->nullable()->after('engine'); // GPT model used
            $table->integer('token_limit')->nullable()->after('openai_model'); // Max tokens for request
            $table->float('temperature', 3, 2)->nullable()->after('token_limit'); // OpenAI temperature setting
            $table->integer('tokens_used')->nullable()->after('temperature'); // Actual tokens consumed
            $table->integer('tokens_streamed')->default(0)->after('tokens_used'); // Tokens received via streaming
            $table->timestamp('streaming_started_at')->nullable()->after('started_at');
            $table->timestamp('streaming_completed_at')->nullable()->after('streaming_started_at');
            $table->json('streaming_metadata')->nullable()->after('metadata'); // Streaming-specific data
            $table->text('raw_openai_response')->nullable()->after('payload'); // Full OpenAI response
            $table->json('structured_result')->nullable()->after('raw_openai_response'); // Parsed structured data
            
            // Performance tracking for streaming
            $table->integer('stream_duration_ms')->nullable()->after('duration_seconds'); // Streaming duration
            $table->decimal('tokens_per_second', 8, 2)->nullable()->after('stream_duration_ms'); // Streaming rate
            $table->integer('stream_interruptions')->default(0)->after('tokens_per_second'); // Connection issues
            
            // Add index for job tracking
            $table->index('job_id');
            $table->index(['status', 'streaming_started_at']);
            $table->index(['openai_model', 'tokens_used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropIndex(['job_id']);
            $table->dropIndex(['status', 'streaming_started_at']);
            $table->dropIndex(['openai_model', 'tokens_used']);
            
            $table->dropColumn([
                'job_id',
                'openai_model',
                'token_limit',
                'temperature',
                'tokens_used',
                'tokens_streamed',
                'streaming_started_at',
                'streaming_completed_at',
                'streaming_metadata',
                'raw_openai_response',
                'structured_result',
                'stream_duration_ms',
                'tokens_per_second',
                'stream_interruptions'
            ]);
        });
    }
};