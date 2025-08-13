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
        Schema::create('open_ai_job_results', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique()->index();
            $table->string('job_type')->index(); // security_analysis, sentiment_analysis, etc.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Job status and tracking
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->integer('attempts_made')->default(0);
            
            // Input data
            $table->longText('prompt');
            $table->json('config')->nullable(); // model, temperature, max_tokens, etc.
            $table->json('metadata')->nullable(); // additional context data
            
            // Output data
            $table->longText('response')->nullable();
            $table->json('parsed_response')->nullable(); // structured response if JSON
            $table->json('token_usage')->nullable(); // token counts, costs, etc.
            $table->json('streaming_stats')->nullable(); // streaming performance data
            
            // Performance metrics
            $table->integer('processing_time_ms')->nullable();
            $table->decimal('estimated_cost_usd', 10, 6)->nullable();
            
            // Error handling
            $table->text('error_message')->nullable();
            
            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['job_type', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['created_at', 'status']);
            $table->index(['job_type', 'created_at']);
            $table->index(['started_at', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_ai_job_results');
    }
};