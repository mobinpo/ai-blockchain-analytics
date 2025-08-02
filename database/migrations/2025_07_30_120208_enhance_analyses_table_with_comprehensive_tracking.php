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
        Schema::table('analyses', function (Blueprint $table) {
            // Analysis type and scope
            $table->string('analysis_type')->default('full')->after('engine'); // full, quick, deep, custom
            $table->string('target_type')->default('contract')->after('analysis_type'); // contract, transaction, address, token
            $table->string('target_address')->nullable()->after('target_type'); // The address/contract being analyzed
            
            // Analysis configuration
            $table->json('configuration')->nullable()->after('target_address'); // Analysis parameters and settings
            $table->string('version')->default('1.0')->after('configuration'); // Analysis engine version
            $table->integer('priority')->default(5)->after('version'); // 1-10, higher = more priority
            
            // Execution tracking
            $table->timestamp('started_at')->nullable()->after('priority');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
            $table->integer('duration_seconds')->nullable()->after('failed_at');
            $table->text('error_message')->nullable()->after('duration_seconds');
            $table->json('error_details')->nullable()->after('error_message');
            
            // Results summary
            $table->integer('findings_count')->default(0)->after('error_details');
            $table->integer('critical_findings_count')->default(0)->after('findings_count');
            $table->integer('high_findings_count')->default(0)->after('critical_findings_count');
            $table->decimal('sentiment_score', 5, 2)->nullable()->after('high_findings_count');
            $table->decimal('risk_score', 5, 2)->nullable()->after('sentiment_score');
            
            // Performance metrics
            $table->bigInteger('gas_analyzed')->nullable()->after('risk_score'); // Gas usage analyzed
            $table->integer('transactions_analyzed')->nullable()->after('gas_analyzed');
            $table->integer('contracts_analyzed')->nullable()->after('transactions_analyzed');
            $table->bigInteger('bytes_analyzed')->nullable()->after('contracts_analyzed');
            
            // Analysis metadata
            $table->json('metadata')->nullable()->after('bytes_analyzed'); // Additional analysis data
            $table->json('tags')->nullable()->after('metadata'); // Analysis tags
            $table->string('triggered_by')->default('manual')->after('tags'); // manual, scheduled, webhook, api
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->after('triggered_by');
            
            // Quality and verification
            $table->boolean('verified')->default(false)->after('triggered_by_user_id');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->after('verified');
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
            $table->text('verification_notes')->nullable()->after('verified_at');
            
            // Archival and retention
            $table->boolean('archived')->default(false)->after('verification_notes');
            $table->timestamp('archived_at')->nullable()->after('archived');
            $table->timestamp('expires_at')->nullable()->after('archived_at');
            
            // Change status to include more states
            $table->string('status')->default('pending')->change(); // pending, running, completed, failed, cancelled, archived
            
            // Indexing for performance
            $table->index(['status', 'priority']);
            $table->index(['engine', 'analysis_type']);
            $table->index(['target_type', 'target_address']);
            $table->index(['started_at', 'completed_at']);
            $table->index(['triggered_by', 'triggered_by_user_id']);
            $table->index(['verified', 'archived']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropIndex(['status', 'priority']);
            $table->dropIndex(['engine', 'analysis_type']);
            $table->dropIndex(['target_type', 'target_address']);
            $table->dropIndex(['started_at', 'completed_at']);
            $table->dropIndex(['triggered_by', 'triggered_by_user_id']);
            $table->dropIndex(['verified', 'archived']);
            $table->dropIndex(['expires_at']);
            
            $table->dropForeign(['triggered_by_user_id']);
            $table->dropForeign(['verified_by_user_id']);
            
            $table->dropColumn([
                'analysis_type',
                'target_type',
                'target_address',
                'configuration',
                'version',
                'priority',
                'started_at',
                'completed_at',
                'failed_at',
                'duration_seconds',
                'error_message',
                'error_details',
                'findings_count',
                'critical_findings_count',
                'high_findings_count',
                'sentiment_score',
                'risk_score',
                'gas_analyzed',
                'transactions_analyzed',
                'contracts_analyzed',
                'bytes_analyzed',
                'metadata',
                'tags',
                'triggered_by',
                'triggered_by_user_id',
                'verified',
                'verified_by_user_id',
                'verified_at',
                'verification_notes',
                'archived',
                'archived_at',
                'expires_at'
            ]);
        });
    }
};
