<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only modify if table exists
        if (Schema::hasTable('contract_cache')) {
            Schema::table('contract_cache', function (Blueprint $table) {
            // Add indexes for better query performance
            $table->index(['network', 'contract_address', 'cache_type'], 'idx_contract_lookup');
            $table->index(['expires_at'], 'idx_expires_at');
            $table->index(['created_at'], 'idx_created_at');
            $table->index(['cache_type', 'network'], 'idx_type_network');
            
            // Add API usage tracking
            $table->boolean('fetched_from_api')->default(true)->after('is_verified');
            $table->timestamp('last_api_fetch')->nullable()->after('fetched_from_api');
            $table->integer('api_fetch_count')->default(1)->after('last_api_fetch');
            
            // Add cache priority and quality metrics
            $table->enum('cache_priority', ['low', 'medium', 'high', 'critical'])->default('medium')->after('api_fetch_count');
            $table->float('cache_quality_score', 3, 2)->default(1.0)->after('cache_priority');
            $table->json('cache_metrics')->nullable()->after('cache_quality_score');
            
            // Add source verification and completeness
            $table->boolean('source_complete')->default(true)->after('cache_metrics');
            $table->boolean('abi_complete')->default(false)->after('source_complete');
            $table->integer('source_file_count')->default(1)->after('abi_complete');
            $table->integer('source_line_count')->default(0)->after('source_file_count');
            
            // Add refresh strategy
            $table->timestamp('next_refresh_at')->nullable()->after('source_line_count');
            $table->enum('refresh_strategy', ['never', 'weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly')->after('next_refresh_at');
            
            // Add error tracking
            $table->integer('error_count')->default(0)->after('refresh_strategy');
            $table->timestamp('last_error_at')->nullable()->after('error_count');
            $table->text('last_error_message')->nullable()->after('last_error_at');
            });
        }

        // Create cache analytics table
        Schema::create('contract_cache_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('network', 50);
            $table->enum('cache_type', ['source', 'abi', 'creation']);
            $table->date('date');
            $table->integer('total_requests')->default(0);
            $table->integer('cache_hits')->default(0);
            $table->integer('cache_misses')->default(0);
            $table->integer('api_calls_saved')->default(0);
            $table->float('cache_hit_rate', 5, 2)->default(0);
            $table->integer('unique_contracts')->default(0);
            $table->json('hourly_stats')->nullable();
            $table->timestamps();
            
            $table->unique(['network', 'cache_type', 'date'], 'idx_analytics_unique');
            $table->index(['date'], 'idx_analytics_date');
        });

        // Create API usage tracking table
        Schema::create('api_usage_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('network', 50);
            $table->string('explorer', 50);
            $table->string('endpoint', 100);
            $table->timestamp('request_time');
            $table->integer('response_time_ms')->nullable();
            $table->boolean('successful')->default(true);
            $table->string('error_type', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->string('contract_address', 42)->nullable();
            $table->json('request_metadata')->nullable();
            $table->timestamps();
            
            $table->index(['network', 'explorer', 'request_time'], 'idx_usage_tracking');
            $table->index(['request_time'], 'idx_request_time');
            $table->index(['successful'], 'idx_successful');
        });

        // Create cache warming queue table
        Schema::create('cache_warming_queue', function (Blueprint $table) {
            $table->id();
            $table->string('network', 50);
            $table->string('contract_address', 42);
            $table->enum('cache_type', ['source', 'abi', 'creation']);
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'priority', 'scheduled_at'], 'idx_queue_processing');
            $table->index(['network', 'contract_address'], 'idx_queue_contract');
            $table->unique(['network', 'contract_address', 'cache_type'], 'idx_queue_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_warming_queue');
        Schema::dropIfExists('api_usage_tracking');
        Schema::dropIfExists('contract_cache_analytics');
        
        if (Schema::hasTable('contract_cache')) {
            Schema::table('contract_cache', function (Blueprint $table) {
            $table->dropIndex('idx_contract_lookup');
            $table->dropIndex('idx_expires_at');
            $table->dropIndex('idx_created_at');
            $table->dropIndex('idx_type_network');
            
            $table->dropColumn([
                'fetched_from_api',
                'last_api_fetch',
                'api_fetch_count',
                'cache_priority',
                'cache_quality_score',
                'cache_metrics',
                'source_complete',
                'abi_complete',
                'source_file_count',
                'source_line_count',
                'next_refresh_at',
                'refresh_strategy',
                'error_count',
                'last_error_at',
                'last_error_message'
            ]);
            });
        }
    }
};