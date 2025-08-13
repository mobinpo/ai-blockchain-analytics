<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('api_cache', function (Blueprint $table) {
            // Rename service to api_source for consistency
            $table->renameColumn('service', 'api_source');
            
            // Add new columns for comprehensive caching (nullable first)
            $table->string('resource_type', 100)->nullable()->after('endpoint');
            $table->string('resource_id', 255)->nullable()->after('resource_type');
            $table->text('response_hash')->nullable()->after('response_data');
            $table->renameColumn('last_accessed', 'last_accessed_at');
            $table->bigInteger('response_size')->default(0)->after('last_accessed_at');
            $table->string('status', 50)->default('active')->after('response_size');
            $table->integer('api_call_cost')->default(1)->after('status');
            $table->decimal('cache_efficiency', 5, 2)->default(0)->after('api_call_cost');
            
            // Drop the is_demo_data column as it's no longer needed
            $table->dropColumn('is_demo_data');
            
            // Note: JSONB conversion will be handled separately with raw SQL
        });
        
        // Update existing data to match new schema
        DB::statement("
            UPDATE api_cache 
            SET 
                resource_type = CASE 
                    WHEN endpoint LIKE '%price%' THEN 'price'
                    WHEN endpoint LIKE '%contract%' THEN 'contract'
                    WHEN endpoint LIKE '%balance%' THEN 'balance'
                    WHEN endpoint LIKE '%transaction%' THEN 'transaction'
                    ELSE 'unknown'
                END,
                response_hash = md5(response_data::text),
                response_size = length(response_data::text),
                status = CASE 
                    WHEN expires_at > NOW() THEN 'active'
                    ELSE 'expired'
                END
        ");
        
        // Convert columns to JSONB using raw SQL (PostgreSQL specific)
        DB::statement('ALTER TABLE api_cache ALTER COLUMN response_data TYPE jsonb USING response_data::jsonb');
        DB::statement('ALTER TABLE api_cache ALTER COLUMN request_params TYPE jsonb USING request_params::jsonb');
        DB::statement('ALTER TABLE api_cache ALTER COLUMN metadata TYPE jsonb USING metadata::jsonb');
        
        // Now make resource_type NOT NULL after populating data
        Schema::table('api_cache', function (Blueprint $table) {
            $table->string('resource_type', 100)->nullable(false)->change();
            $table->text('response_hash')->nullable(false)->change();
        });
        
        // Add composite indexes for better query performance
        Schema::table('api_cache', function (Blueprint $table) {
            $table->index(['api_source', 'resource_type', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index(['last_accessed_at', 'hit_count']);
            $table->index(['api_source', 'resource_id', 'status']);
        });
        
        // Create GIN indexes for JSONB columns (PostgreSQL specific)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_api_cache_response_data_gin ON api_cache USING gin (response_data)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_api_cache_request_params_gin ON api_cache USING gin (request_params)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_cache', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn([
                'resource_type',
                'resource_id', 
                'response_hash',
                'response_size',
                'status',
                'api_call_cost',
                'cache_efficiency'
            ]);
            
            // Rename back to original column name
            $table->renameColumn('api_source', 'service');
            $table->renameColumn('last_accessed_at', 'last_accessed');
            
            // Add back is_demo_data column
            $table->boolean('is_demo_data')->default(false);
        });
        
        // Drop the composite indexes
        Schema::table('api_cache', function (Blueprint $table) {
            $table->dropIndex(['api_source', 'resource_type', 'status']);
            $table->dropIndex(['expires_at', 'status']);
            $table->dropIndex(['last_accessed_at', 'hit_count']);
            $table->dropIndex(['api_source', 'resource_id', 'status']);
        });
        
        // Drop GIN indexes
        DB::statement('DROP INDEX IF EXISTS idx_api_cache_response_data_gin');
        DB::statement('DROP INDEX IF EXISTS idx_api_cache_request_params_gin');
    }
};
