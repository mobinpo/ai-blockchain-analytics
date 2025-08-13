<?php

declare(strict_types=1);

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
        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            
            // Platform and post identification
            $table->string('platform', 50)->index(); // twitter, reddit, telegram
            $table->string('external_id')->index(); // Platform-specific post ID
            $table->string('post_type', 50); // original, retweet, reply, etc.
            
            // Content
            $table->text('content');
            $table->text('content_html')->nullable(); // Rich content if available
            $table->jsonb('attachments')->nullable(); // Images, videos, etc.
            
            // Author information
            $table->string('author_username')->nullable()->index();
            $table->string('author_display_name')->nullable();
            $table->string('author_id')->nullable()->index();
            $table->bigInteger('author_followers')->default(0);
            $table->boolean('author_verified')->default(false);
            
            // Engagement metrics
            $table->jsonb('engagement_metrics'); // Platform-specific metrics
            $table->integer('engagement_score')->default(0)->index(); // Calculated score
            
            // Analysis data
            $table->decimal('sentiment_score', 5, 3)->nullable()->index(); // -1.000 to 1.000
            $table->string('language', 10)->default('en');
            $table->jsonb('entities')->nullable(); // Hashtags, mentions, etc.
            $table->jsonb('topics')->nullable(); // Detected topics/categories
            
            // Metadata and source tracking
            $table->jsonb('metadata'); // Platform-specific metadata
            $table->jsonb('matched_keywords'); // Keywords that triggered capture
            $table->jsonb('matched_hashtags')->nullable(); // Hashtags that matched
            $table->timestamp('posted_at')->index(); // Original post timestamp
            
            // Processing status
            $table->string('processing_status', 50)->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->jsonb('processing_errors')->nullable();
            
            // Relationships
            $table->foreignId('crawler_rule_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            // Quality and filtering
            $table->boolean('is_spam')->default(false)->index();
            $table->boolean('is_duplicate')->default(false)->index();
            $table->decimal('quality_score', 5, 3)->default(0.5); // 0.000 to 1.000
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['platform', 'external_id'], 'idx_platform_external');
            $table->index(['platform', 'posted_at'], 'idx_platform_posted');
            $table->index(['crawler_rule_id', 'posted_at'], 'idx_rule_posted');
            $table->index(['processing_status', 'created_at'], 'idx_processing_created');
            $table->index(['sentiment_score', 'engagement_score'], 'idx_sentiment_engagement');
            
            // Unique constraint to prevent duplicates
            $table->unique(['platform', 'external_id'], 'uk_platform_external');
        });
        
        // PostgreSQL JSONB indexes
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_social_media_posts_engagement_gin ON social_media_posts USING gin (engagement_metrics)');
            DB::statement('CREATE INDEX idx_social_media_posts_metadata_gin ON social_media_posts USING gin (metadata)');
            DB::statement('CREATE INDEX idx_social_media_posts_entities_gin ON social_media_posts USING gin (entities)');
            DB::statement('CREATE INDEX idx_social_media_posts_keywords_gin ON social_media_posts USING gin (matched_keywords)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};
