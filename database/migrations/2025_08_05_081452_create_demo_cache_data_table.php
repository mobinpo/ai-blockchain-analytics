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
        Schema::create('demo_cache_data', function (Blueprint $table) {
            $table->id();
            $table->string('data_type', 100)->index(); // e.g., 'live_stats', 'threats', 'activities'
            $table->string('identifier', 255)->index(); // Unique identifier for the data
            $table->json('data'); // The demo data payload
            $table->integer('refresh_interval')->default(300); // Refresh interval in seconds
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // Composite index for efficient lookups
            $table->index(['data_type', 'identifier']);
            $table->index(['is_active', 'data_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_cache_data');
    }
};
