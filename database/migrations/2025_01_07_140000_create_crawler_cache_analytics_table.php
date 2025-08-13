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
        Schema::create('crawler_cache_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('platform');              // twitter, reddit, telegram
            $table->string('operation_type');        // search, timeline, auth, rate_limit
            $table->string('cache_status');          // hit, miss, stale_used, api_avoided
            $table->string('query_hash')->nullable(); // MD5 hash of search query
            $table->json('filters')->nullable();     // Search filters used
            $table->integer('result_count')->default(0);
            $table->integer('cache_ttl')->nullable(); // TTL used for caching
            $table->float('processing_time_ms')->nullable(); // Time to process request
            $table->boolean('api_call_made')->default(false);
            $table->float('api_cost_saved')->default(0.0); // Estimated cost savings
            $table->string('priority_level')->default('medium');
            $table->json('metadata')->nullable();    // Additional context
            $table->timestamp('created_at');
            
            // Indexes for performance
            $table->index(['platform', 'created_at']);
            $table->index(['operation_type', 'cache_status']);
            $table->index(['query_hash', 'platform']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawler_cache_analytics');
    }
};