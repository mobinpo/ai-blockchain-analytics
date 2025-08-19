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
        Schema::table('contract_analyses', function (Blueprint $table) {
            // Add fields expected by AnalysisController
            $table->string('contract_address', 42)->nullable()->after('contract_id');
            $table->string('network', 20)->nullable()->after('contract_address');
            $table->string('model', 50)->nullable()->after('network');
            $table->json('analysis_options')->nullable()->after('model');
            $table->string('triggered_by', 20)->default('manual')->after('analysis_options');
            $table->unsignedBigInteger('user_id')->nullable()->after('triggered_by');
            $table->integer('progress')->default(0)->after('status');
            $table->string('current_step')->nullable()->after('progress');
            $table->integer('findings_count')->default(0)->after('findings');
            $table->text('raw_response')->nullable()->after('recommendations');
            $table->integer('tokens_used')->nullable()->after('raw_response');
            $table->integer('processing_time_ms')->nullable()->after('tokens_used');
            $table->timestamp('started_at')->nullable()->after('analysis_date');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->text('error_message')->nullable()->after('completed_at');
            
            // Add indexes
            $table->index(['contract_address', 'network']);
            $table->index('user_id');
            $table->index(['status', 'created_at']);
            
            // Add foreign key for user_id if users table exists
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_analyses', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['user_id']);
            
            // Drop indexes
            $table->dropIndex(['contract_address', 'network']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status', 'created_at']);
            
            // Drop columns in reverse order
            $table->dropColumn([
                'error_message',
                'completed_at',
                'started_at',
                'processing_time_ms',
                'tokens_used',
                'raw_response',
                'findings_count',
                'current_step',
                'progress',
                'user_id',
                'triggered_by',
                'analysis_options',
                'model',
                'network',
                'contract_address'
            ]);
        });
    }
};
