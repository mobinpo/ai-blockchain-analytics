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
        Schema::table('projects', function (Blueprint $table) {
            // Blockchain network information
            $table->string('blockchain_network')->default('ethereum')->after('description'); // ethereum, polygon, bsc, etc.
            $table->string('project_type')->default('smart_contract')->after('blockchain_network'); // smart_contract, defi, nft, dao, etc.
            
            // Contract/Address information
            $table->json('contract_addresses')->nullable()->after('project_type'); // Array of contract addresses
            $table->string('main_contract_address')->nullable()->after('contract_addresses');
            $table->string('token_address')->nullable()->after('main_contract_address');
            $table->string('token_symbol')->nullable()->after('token_address');
            
            // Project metadata
            $table->json('metadata')->nullable()->after('token_symbol'); // Additional project data
            $table->string('website_url')->nullable()->after('metadata');
            $table->string('github_url')->nullable()->after('website_url');
            $table->json('social_links')->nullable()->after('github_url'); // Twitter, Discord, etc.
            
            // Analysis tracking
            $table->integer('analyses_count')->default(0)->after('social_links');
            $table->integer('critical_findings_count')->default(0)->after('analyses_count');
            $table->decimal('average_sentiment_score', 5, 2)->nullable()->after('critical_findings_count');
            $table->timestamp('last_analyzed_at')->nullable()->after('average_sentiment_score');
            
            // Project status and visibility
            $table->string('status')->default('active')->after('last_analyzed_at'); // active, archived, analyzing
            $table->boolean('is_public')->default(false)->after('status');
            $table->boolean('monitoring_enabled')->default(false)->after('is_public');
            $table->json('alert_settings')->nullable()->after('monitoring_enabled');
            
            // Risk assessment
            $table->string('risk_level')->nullable()->after('alert_settings'); // low, medium, high, critical
            $table->decimal('risk_score', 5, 2)->nullable()->after('risk_level');
            $table->timestamp('risk_updated_at')->nullable()->after('risk_score');
            
            // Tags and categorization
            $table->json('tags')->nullable()->after('risk_updated_at'); // Array of tags
            $table->string('category')->nullable()->after('tags'); // defi, gaming, infrastructure, etc.
            
            // Indexing for performance
            $table->index(['blockchain_network', 'project_type']);
            $table->index(['status', 'is_public']);
            $table->index(['risk_level']);
            $table->index(['last_analyzed_at']);
            $table->index(['main_contract_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['blockchain_network', 'project_type']);
            $table->dropIndex(['status', 'is_public']);
            $table->dropIndex(['risk_level']);
            $table->dropIndex(['last_analyzed_at']);
            $table->dropIndex(['main_contract_address']);
            
            $table->dropColumn([
                'blockchain_network',
                'project_type',
                'contract_addresses',
                'main_contract_address',
                'token_address',
                'token_symbol',
                'metadata',
                'website_url',
                'github_url',
                'social_links',
                'analyses_count',
                'critical_findings_count',
                'average_sentiment_score',
                'last_analyzed_at',
                'status',
                'is_public',
                'monitoring_enabled',
                'alert_settings',
                'risk_level',
                'risk_score',
                'risk_updated_at',
                'tags',
                'category'
            ]);
        });
    }
};
