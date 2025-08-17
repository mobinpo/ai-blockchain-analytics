<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Crawler configurations and keyword rules
        Schema::create('crawler_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('platform', ['twitter', 'reddit', 'telegram']);
            $table->boolean('enabled')->default(true);
            $table->integer('rate_limit_per_hour')->default(100);
            $table->integer('max_results_per_run')->default(100);
            $table->json('api_credentials'); // Encrypted API keys
            $table->json('settings')->nullable(); // Platform-specific settings
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            
            $table->index(['platform', 'enabled']);
            $table->index('next_run_at');
        });

        // Keyword rules for content filtering
        Schema::create('keyword_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('keywords'); // Array of keywords/phrases
            $table->json('exclude_keywords')->nullable(); // Keywords to exclude
            $table->enum('match_type', ['any', 'all', 'exact', 'regex'])->default('any');
            $table->boolean('case_sensitive')->default(false);
            $table->integer('priority')->default(1); // 1-10, higher = more important
            $table->json('platforms'); // Which platforms to apply to
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable(); // Additional rule context
            $table->timestamps();
            
            $table->index(['active', 'priority']);
        });

        // Scraped social media posts
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique(); // Platform-specific post ID
            $table->enum('platform', ['twitter', 'reddit', 'telegram']);
            $table->string('source_url');
            $table->string('author_username')->nullable();
            $table->string('author_display_name')->nullable();
            $table->string('author_id')->nullable();
            $table->text('content');
            $table->json('media_urls')->nullable(); // Images, videos, etc.
            $table->integer('engagement_count')->default(0); // Likes/upvotes/reactions
            $table->integer('share_count')->default(0); // Retweets/shares
            $table->integer('comment_count')->default(0);
            $table->timestamp('published_at');
            $table->json('raw_data'); // Full API response
            $table->json('matched_keywords')->nullable(); // Which keywords matched
            $table->json('sentiment_analysis')->nullable(); // AI sentiment scores
            $table->decimal('relevance_score', 5, 3)->default(0); // 0-1 relevance
            $table->boolean('is_processed')->default(false);
            $table->boolean('is_relevant')->nullable(); // Manual/AI classification
            $table->timestamps();
            
            $table->index(['platform', 'published_at']);
            $table->index(['is_processed', 'is_relevant']);
            $table->index('relevance_score');
            $table->index('author_username');
            
            // Only add fulltext index for MySQL/PostgreSQL
            if (config('database.default') !== 'sqlite') {
                $table->fullText(['content', 'author_username']);
            }
        });

        // Keyword matches for analytics
        Schema::create('keyword_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->onDelete('cascade');
            $table->foreignId('keyword_rule_id')->constrained()->onDelete('cascade');
            $table->string('matched_keyword');
            $table->integer('match_count')->default(1);
            $table->json('match_positions')->nullable(); // Character positions
            $table->decimal('confidence_score', 5, 3)->default(1.0);
            $table->timestamps();
            
            $table->unique(['social_post_id', 'keyword_rule_id']);
            $table->index('matched_keyword');
        });

        // Crawler job queue and status
        Schema::create('crawler_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawler_config_id')->constrained()->onDelete('cascade');
            $table->string('job_type'); // 'scheduled', 'keyword_search', 'user_mentions'
            $table->json('parameters'); // Search parameters, keywords, etc.
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('posts_found')->default(0);
            $table->integer('posts_processed')->default(0);
            $table->json('error_details')->nullable();
            $table->json('execution_stats')->nullable(); // Performance metrics
            $table->timestamps();
            
            $table->index(['status', 'started_at']);
            $table->index(['crawler_config_id', 'job_type']);
        });

        // Rate limiting and API quota tracking
        Schema::create('api_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('endpoint');
            $table->string('api_key_hash'); // Hashed API key for identification
            $table->integer('requests_made')->default(0);
            $table->integer('requests_limit');
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->timestamp('reset_at')->nullable();
            $table->boolean('is_exceeded')->default(false);
            $table->timestamps();
            
            $table->unique(['platform', 'endpoint', 'api_key_hash', 'window_start']);
            $table->index(['platform', 'is_exceeded', 'reset_at']);
        });

        // Analytics and trending topics
        Schema::create('trending_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->enum('platform', ['twitter', 'reddit', 'telegram', 'all']);
            $table->integer('mention_count')->default(0);
            $table->decimal('sentiment_score', 5, 3)->nullable(); // -1 to 1
            $table->decimal('engagement_score', 8, 3)->default(0);
            $table->date('trend_date');
            $table->integer('trend_rank')->nullable();
            $table->json('related_keywords')->nullable();
            $table->json('top_posts')->nullable(); // IDs of most relevant posts
            $table->timestamps();
            
            $table->unique(['keyword', 'platform', 'trend_date']);
            $table->index(['trend_date', 'trend_rank']);
            $table->index(['platform', 'mention_count']);
        });

        // User/channel monitoring
        Schema::create('monitored_accounts', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['twitter', 'reddit', 'telegram']);
            $table->string('username');
            $table->string('display_name')->nullable();
            $table->string('external_id')->nullable();
            $table->string('profile_url');
            $table->text('description')->nullable();
            $table->integer('follower_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->enum('account_type', ['individual', 'organization', 'bot', 'news'])->default('individual');
            $table->integer('priority')->default(1); // 1-10
            $table->boolean('active')->default(true);
            $table->json('tags')->nullable(); // Custom categorization
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();
            
            $table->unique(['platform', 'username']);
            $table->index(['active', 'priority']);
            $table->index('last_scraped_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trending_keywords');
        Schema::dropIfExists('monitored_accounts');
        Schema::dropIfExists('api_rate_limits');
        Schema::dropIfExists('crawler_jobs');
        Schema::dropIfExists('keyword_matches');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('keyword_rules');
        Schema::dropIfExists('crawler_configs');
    }
};