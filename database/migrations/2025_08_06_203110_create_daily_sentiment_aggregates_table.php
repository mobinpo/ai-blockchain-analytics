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
        Schema::create('daily_sentiment_aggregates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('platform', 20)->index(); // twitter, reddit, telegram
            $table->string('keyword')->nullable()->index(); // specific keyword analysis
            $table->integer('total_posts')->default(0);
            $table->integer('analyzed_posts')->default(0);
            $table->decimal('avg_sentiment_score', 5, 4)->nullable(); // -1.0000 to 1.0000
            $table->decimal('avg_magnitude', 5, 4)->nullable(); // 0.0000 to 4.0000+
            $table->integer('positive_count')->default(0);
            $table->integer('negative_count')->default(0);
            $table->integer('neutral_count')->default(0);
            $table->integer('unknown_count')->default(0);
            $table->decimal('positive_percentage', 5, 2)->default(0); // 0.00 to 100.00
            $table->decimal('negative_percentage', 5, 2)->default(0);
            $table->decimal('neutral_percentage', 5, 2)->default(0);
            $table->json('hourly_distribution')->nullable(); // sentiment by hour
            $table->json('top_keywords')->nullable(); // most mentioned keywords
            $table->json('metadata')->nullable(); // additional platform-specific data
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Composite indexes for efficient querying
            $table->unique(['date', 'platform', 'keyword']);
            $table->index(['date', 'platform']);
            $table->index(['date', 'keyword']);
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_sentiment_aggregates');
    }
};
