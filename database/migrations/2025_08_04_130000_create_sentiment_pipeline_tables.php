<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batch processing tracking
        Schema::create('sentiment_batches', function (Blueprint $table) {
            $table->id();
            $table->date('processing_date');
            $table->string('batch_id')->unique();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('total_documents')->default(0);
            $table->integer('processed_documents')->default(0);
            $table->integer('failed_documents')->default(0);
            $table->json('processing_stats')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('processing_cost', 10, 4)->nullable(); // API cost tracking
            $table->timestamps();
            
            $table->index(['processing_date', 'status']);
            $table->index(['status']);
        });

        // Individual document processing results
        Schema::create('sentiment_batch_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sentiment_batch_id')->constrained()->onDelete('cascade');
            $table->string('source_type'); // social_media_post, news_article, etc.
            $table->unsignedBigInteger('source_id');
            $table->text('processed_text');
            $table->string('detected_language', 10)->nullable();
            $table->decimal('sentiment_score', 3, 2)->nullable();
            $table->decimal('magnitude', 3, 2)->nullable();
            $table->json('entities')->nullable(); // Named entities from NLP
            $table->json('categories')->nullable(); // Content categories
            $table->string('processing_status')->default('pending');
            $table->json('error_details')->nullable();
            $table->timestamps();
            
            $table->index(['source_type', 'source_id']);
            $table->index(['sentiment_batch_id', 'processing_status']);
        });

        // Daily sentiment aggregates
        Schema::create('daily_sentiment_aggregates', function (Blueprint $table) {
            $table->id();
            $table->date('aggregate_date');
            $table->string('platform'); // twitter, reddit, telegram, all
            $table->string('keyword_category')->nullable(); // blockchain, security, contracts, all
            $table->integer('time_bucket')->nullable(); // Hour of day (0-23), null for full day
            $table->string('language', 10)->default('en');
            
            // Volume metrics
            $table->integer('total_posts')->default(0);
            $table->integer('processed_posts')->default(0);
            $table->bigInteger('total_engagement')->default(0);
            
            // Sentiment metrics
            $table->decimal('average_sentiment', 4, 3)->nullable();
            $table->decimal('weighted_sentiment', 4, 3)->nullable(); // Engagement weighted
            $table->decimal('average_magnitude', 4, 3)->nullable();
            
            // Sentiment distribution
            $table->integer('very_positive_count')->default(0); // > 0.6
            $table->integer('positive_count')->default(0); // 0.2 to 0.6
            $table->integer('neutral_count')->default(0); // -0.2 to 0.2
            $table->integer('negative_count')->default(0); // -0.6 to -0.2
            $table->integer('very_negative_count')->default(0); // < -0.6
            
            // Additional metrics
            $table->json('top_keywords')->nullable(); // Top 10 keywords with counts
            $table->json('top_entities')->nullable(); // Top named entities
            $table->json('language_distribution')->nullable();
            $table->decimal('sentiment_volatility', 4, 3)->nullable(); // Standard deviation
            
            // Comparison metrics
            $table->decimal('sentiment_change_1d', 4, 3)->nullable(); // vs previous day
            $table->decimal('sentiment_change_7d', 4, 3)->nullable(); // vs 7 days ago
            $table->decimal('volume_change_1d', 4, 3)->nullable(); // % change in volume
            
            $table->timestamps();
            
            $table->unique(['aggregate_date', 'platform', 'keyword_category', 'time_bucket', 'language'], 'daily_sentiment_unique');
            $table->index(['aggregate_date', 'platform']);
            $table->index(['aggregate_date', 'keyword_category']);
            $table->index(['average_sentiment']);
            $table->index(['total_posts']);
        });

        // Sentiment alerts
        Schema::create('sentiment_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type'); // sentiment_spike, volume_anomaly, keyword_trend
            $table->date('alert_date');
            $table->string('platform');
            $table->string('keyword_category')->nullable();
            $table->decimal('trigger_value', 8, 4); // Sentiment score or volume multiplier
            $table->json('alert_data'); // Context data for the alert
            $table->text('alert_message');
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->timestamps();
            
            $table->index(['alert_date', 'severity']);
            $table->index(['acknowledged', 'severity']);
            $table->index(['platform', 'alert_type']);
        });

        // Text preprocessing cache
        Schema::create('text_preprocessing_cache', function (Blueprint $table) {
            $table->id();
            $table->string('content_hash', 64)->unique(); // SHA-256 of original text
            $table->text('original_text');
            $table->text('processed_text');
            $table->string('detected_language', 10)->nullable();
            $table->json('preprocessing_steps')->nullable();
            $table->timestamp('last_used_at');
            $table->timestamps();
            
            $table->index(['content_hash']);
            $table->index(['last_used_at']); // For cleanup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_preprocessing_cache');
        Schema::dropIfExists('sentiment_alerts');
        Schema::dropIfExists('daily_sentiment_aggregates');
        Schema::dropIfExists('sentiment_batch_documents');
        Schema::dropIfExists('sentiment_batches');
    }
};