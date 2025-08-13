<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Social media posts table
        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // twitter, reddit, telegram
            $table->string('platform_id')->unique(); // post/tweet/message ID from platform
            $table->string('author_username')->nullable();
            $table->string('author_id')->nullable();
            $table->text('content');
            $table->json('metadata')->nullable(); // platform-specific data
            $table->string('url')->nullable();
            $table->timestamp('published_at');
            $table->integer('engagement_score')->default(0); // likes, shares, comments
            $table->decimal('sentiment_score', 3, 2)->nullable();
            $table->string('sentiment_label')->nullable(); // positive, negative, neutral
            $table->json('matched_keywords'); // which keywords triggered this capture
            $table->timestamps();
            
            $table->index(['platform', 'published_at']);
            $table->index(['sentiment_score']);
            $table->index(['published_at']);
        });

        // Keyword tracking table
        Schema::create('keyword_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_media_post_id')->constrained()->onDelete('cascade');
            $table->string('keyword');
            $table->string('keyword_category'); // blockchain, security, contracts
            $table->integer('match_count')->default(1);
            $table->string('priority'); // high, medium, low, critical
            $table->timestamps();
            
            $table->index(['keyword', 'created_at']);
            $table->index(['keyword_category', 'created_at']);
            $table->index(['priority', 'created_at']);
        });

        // Trend analytics table
        Schema::create('social_trends', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->string('platform');
            $table->date('trend_date');
            $table->integer('mention_count')->default(0);
            $table->decimal('average_sentiment', 3, 2)->nullable();
            $table->integer('total_engagement')->default(0);
            $table->json('hourly_breakdown')->nullable(); // mentions per hour
            $table->timestamps();
            
            $table->unique(['keyword', 'platform', 'trend_date']);
            $table->index(['trend_date', 'mention_count']);
        });

        // Crawler job status table
        Schema::create('crawler_job_status', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('job_type'); // keyword_search, user_timeline, channel_monitor
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->integer('posts_collected')->default(0);
            $table->json('last_error')->nullable();
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->timestamps();
            
            $table->unique(['platform', 'job_type']);
        });

        // Alert log table
        Schema::create('social_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type'); // security_spike, trending_keyword, negative_sentiment
            $table->string('trigger_keyword')->nullable();
            $table->string('platform');
            $table->integer('trigger_count');
            $table->decimal('trigger_value', 8, 4)->nullable(); // sentiment score or other metric
            $table->text('alert_message');
            $table->json('related_posts'); // IDs of posts that triggered alert
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            
            $table->index(['alert_type', 'created_at']);
            $table->index(['acknowledged']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_alerts');
        Schema::dropIfExists('crawler_job_status');
        Schema::dropIfExists('social_trends');
        Schema::dropIfExists('keyword_matches');
        Schema::dropIfExists('social_media_posts');
    }
};