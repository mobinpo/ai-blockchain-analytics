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
        Schema::create('crawler_rules', function (Blueprint $table) {
            $table->id();
            
            // Rule identification
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->integer('priority')->default(10)->index(); // 1 = highest priority
            
            // Platform configuration
            $table->jsonb('platforms'); // ['twitter', 'reddit', 'telegram']
            $table->jsonb('platform_configs')->nullable(); // Platform-specific settings
            
            // Content targeting
            $table->jsonb('keywords'); // ['bitcoin', 'ethereum', 'DeFi']
            $table->jsonb('hashtags')->nullable(); // ['#crypto', '#blockchain']
            $table->jsonb('accounts')->nullable(); // Specific accounts to monitor
            $table->jsonb('exclude_keywords')->nullable(); // Words to exclude
            
            // Filtering criteria
            $table->decimal('sentiment_threshold', 3, 2)->nullable(); // -1.0 to 1.0
            $table->integer('engagement_threshold')->nullable(); // Min likes/upvotes
            $table->integer('follower_threshold')->nullable(); // Min account followers
            $table->string('language', 10)->default('en'); // Content language
            
            // Advanced filters
            $table->jsonb('filters')->nullable(); // Custom filter rules
            $table->jsonb('geofencing')->nullable(); // Geographic restrictions
            $table->timestamp('start_date')->nullable(); // When to start crawling
            $table->timestamp('end_date')->nullable(); // When to stop crawling
            
            // Rate limiting and performance
            $table->integer('max_posts_per_hour')->default(100);
            $table->integer('crawl_interval_minutes')->default(15); // How often to run
            $table->boolean('real_time')->default(false); // Real-time streaming vs batch
            
            // Results and statistics
            $table->bigInteger('total_posts_found')->default(0);
            $table->bigInteger('total_posts_processed')->default(0);
            $table->timestamp('last_crawl_at')->nullable()->index();
            $table->jsonb('last_crawl_stats')->nullable();
            $table->jsonb('performance_metrics')->nullable();
            
            // Ownership and management
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('created_by')->nullable(); // System, API, User
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['active', 'priority']);
            $table->index(['last_crawl_at', 'crawl_interval_minutes']);
            $table->index(['start_date', 'end_date']);
            
            // GIN indexes for JSONB columns (PostgreSQL specific)
        });
        
        // PostgreSQL JSONB indexes
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_crawler_rules_platforms_gin ON crawler_rules USING gin (platforms)');
            DB::statement('CREATE INDEX idx_crawler_rules_keywords_gin ON crawler_rules USING gin (keywords)');
            DB::statement('CREATE INDEX idx_crawler_rules_filters_gin ON crawler_rules USING gin (filters)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawler_rules');
    }
};
