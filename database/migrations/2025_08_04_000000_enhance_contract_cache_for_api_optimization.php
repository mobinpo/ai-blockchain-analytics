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
        if (Schema::hasTable('contract_cache')) {
            Schema::table('contract_cache', function (Blueprint $table) {
            // Enhanced caching fields for API optimization (only add missing columns)
            if (!Schema::hasColumn('contract_cache', 'stale_extension_count')) {
                $table->integer('stale_extension_count')->default(0);
            }
            if (!Schema::hasColumn('contract_cache', 'optimization_metadata')) {
                $table->json('optimization_metadata')->nullable();
            }
            
            // API efficiency tracking
            if (!Schema::hasColumn('contract_cache', 'cache_hits')) {
                $table->integer('cache_hits')->default(0);
            }
            if (!Schema::hasColumn('contract_cache', 'cache_misses')) {
                $table->integer('cache_misses')->default(0);
            }
            if (!Schema::hasColumn('contract_cache', 'last_accessed_at')) {
                $table->timestamp('last_accessed_at')->nullable();
            }
            if (!Schema::hasColumn('contract_cache', 'access_frequency_score')) {
                $table->integer('access_frequency_score')->default(1); // 1-10 scale
            }
            if (!Schema::hasColumn('contract_cache', 'cache_priority')) {
                $table->integer('cache_priority')->default(1); // 1-10 scale
            }
            
            // Contract importance indicators
            if (!Schema::hasColumn('contract_cache', 'is_popular_contract')) {
                $table->boolean('is_popular_contract')->default(false);
            }
            if (!Schema::hasColumn('contract_cache', 'is_high_value_contract')) {
                $table->boolean('is_high_value_contract')->default(false);
            }
            if (!Schema::hasColumn('contract_cache', 'contract_size_score')) {
                $table->integer('contract_size_score')->default(1); // 1-10 based on source size
            }
            });
            
            // Add indexes in a separate schema call to avoid issues
            Schema::table('contract_cache', function (Blueprint $table) {
            // Enhanced indexing for performance
            $table->index(['network', 'expires_at', 'cache_priority'], 'idx_cache_optimization');
            $table->index(['is_popular_contract', 'is_high_value_contract'], 'idx_contract_importance');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contract_cache')) {
            Schema::table('contract_cache', function (Blueprint $table) {
            $table->dropIndex('idx_cache_optimization');
            $table->dropIndex('idx_contract_importance');
            
            $table->dropColumn([
                'stale_extension_count',
                'optimization_metadata',
                'cache_hits',
                'cache_misses',
                'last_accessed_at',
                'access_frequency_score',
                'cache_priority',
                'is_popular_contract',
                'is_high_value_contract',
                'contract_size_score'
            ]);
            });
        }
    }
};