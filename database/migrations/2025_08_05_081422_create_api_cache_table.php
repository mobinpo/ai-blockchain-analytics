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
        Schema::create('api_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 255)->unique();
            $table->string('service', 100)->index(); // e.g., 'coingecko', 'sentiment', 'blockchain'
            $table->string('endpoint', 255)->index(); // API endpoint or operation
            $table->json('request_params')->nullable(); // Request parameters for cache validation
            $table->longText('response_data'); // Cached API response
            $table->json('metadata')->nullable(); // Additional metadata (headers, status codes, etc.)
            $table->timestamp('expires_at')->index(); // Cache expiration
            $table->boolean('is_demo_data')->default(false)->index(); // Flag for demo/synthetic data
            $table->integer('hit_count')->default(0); // Track cache usage
            $table->timestamp('last_accessed')->nullable()->index(); // Track last access
            $table->timestamps();

            // Indexes for performance
            $table->index(['service', 'endpoint']);
            $table->index(['expires_at', 'service']);
            $table->index(['is_demo_data', 'service']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_cache');
    }
};
