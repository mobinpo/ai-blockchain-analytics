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
        Schema::table('findings', function (Blueprint $table) {
            // Finding categorization
            $table->string('category')->default('security')->after('severity'); // security, performance, gas, logic, compliance
            $table->string('subcategory')->nullable()->after('category'); // reentrancy, overflow, access_control, etc.
            $table->string('finding_type')->default('vulnerability')->after('subcategory'); // vulnerability, optimization, warning, info
            
            // Vulnerability details
            $table->string('cwe_id')->nullable()->after('finding_type'); // Common Weakness Enumeration ID
            $table->string('owasp_category')->nullable()->after('cwe_id'); // OWASP Top 10 category
            $table->decimal('cvss_score', 3, 1)->nullable()->after('owasp_category'); // CVSS score 0.0-10.0
            $table->string('attack_vector')->nullable()->after('cvss_score'); // local, network, adjacent
            $table->string('attack_complexity')->nullable()->after('attack_vector'); // low, high
            
            // Blockchain-specific fields
            $table->string('contract_address')->nullable()->after('attack_complexity'); // Affected contract
            $table->string('function_name')->nullable()->after('contract_address'); // Affected function
            $table->string('function_signature')->nullable()->after('function_name'); // Function signature hash
            $table->unsignedInteger('line_start')->nullable()->after('line'); // Multi-line findings
            $table->unsignedInteger('line_end')->nullable()->after('line_start');
            $table->text('code_snippet')->nullable()->after('line_end'); // Relevant code
            
            // Gas and economics
            $table->bigInteger('gas_cost')->nullable()->after('code_snippet'); // Gas cost if relevant
            $table->decimal('economic_impact', 20, 2)->nullable()->after('gas_cost'); // Potential $ impact
            $table->string('currency')->default('USD')->after('economic_impact');
            
            // Risk and impact assessment
            $table->string('likelihood')->default('medium')->after('currency'); // low, medium, high
            $table->string('impact')->default('medium')->after('likelihood'); // low, medium, high, critical
            $table->text('exploitation_scenario')->nullable()->after('impact'); // How it could be exploited
            $table->text('business_impact')->nullable()->after('exploitation_scenario');
            
            // Remediation
            $table->text('recommendation')->nullable()->after('business_impact');
            $table->text('remediation_effort')->nullable()->after('recommendation'); // low, medium, high
            $table->json('remediation_resources')->nullable()->after('remediation_effort'); // Links, docs, etc.
            $table->text('fix_code')->nullable()->after('remediation_resources'); // Suggested fix
            
            // Detection and verification
            $table->string('detection_method')->default('static')->after('fix_code'); // static, dynamic, manual, ai
            $table->decimal('confidence_score', 5, 2)->default(100.00)->after('detection_method'); // 0-100
            $table->boolean('false_positive')->default(false)->after('confidence_score');
            $table->text('false_positive_reason')->nullable()->after('false_positive');
            
            // Status and workflow
            $table->string('status')->default('open')->after('false_positive_reason'); // open, confirmed, fixed, ignored, duplicate
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->after('status');
            $table->timestamp('acknowledged_at')->nullable()->after('assigned_to_user_id');
            $table->timestamp('fixed_at')->nullable()->after('acknowledged_at');
            $table->text('fix_notes')->nullable()->after('fixed_at');
            
            // References and external data
            $table->json('references')->nullable()->after('fix_notes'); // External references
            $table->json('tags')->nullable()->after('references'); // Finding tags
            $table->string('external_id')->nullable()->after('tags'); // External tool ID
            $table->json('tool_data')->nullable()->after('external_id'); // Tool-specific data
            
            // Duplicate and similarity tracking
            $table->foreignId('duplicate_of_id')->nullable()->constrained('findings')->after('tool_data');
            $table->string('similarity_hash')->nullable()->after('duplicate_of_id'); // For deduplication
            
            // Change metadata to be more specific
            $table->dropColumn('metadata');
            $table->json('analysis_metadata')->nullable()->after('similarity_hash'); // Analysis-specific metadata
            
            // Indexing for performance
            $table->index(['severity', 'category']);
            $table->index(['status', 'assigned_to_user_id']);
            $table->index(['contract_address', 'function_name']);
            $table->index(['false_positive', 'duplicate_of_id']);
            $table->index(['finding_type', 'subcategory']);
            $table->index(['cvss_score', 'likelihood', 'impact']);
            $table->index(['similarity_hash']);
            $table->index(['acknowledged_at', 'fixed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropIndex(['severity', 'category']);
            $table->dropIndex(['status', 'assigned_to_user_id']);
            $table->dropIndex(['contract_address', 'function_name']);
            $table->dropIndex(['false_positive', 'duplicate_of_id']);
            $table->dropIndex(['finding_type', 'subcategory']);
            $table->dropIndex(['cvss_score', 'likelihood', 'impact']);
            $table->dropIndex(['similarity_hash']);
            $table->dropIndex(['acknowledged_at', 'fixed_at']);
            
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropForeign(['duplicate_of_id']);
            
            $table->json('metadata')->nullable();
            
            $table->dropColumn([
                'category',
                'subcategory',
                'finding_type',
                'cwe_id',
                'owasp_category',
                'cvss_score',
                'attack_vector',
                'attack_complexity',
                'contract_address',
                'function_name',
                'function_signature',
                'line_start',
                'line_end',
                'code_snippet',
                'gas_cost',
                'economic_impact',
                'currency',
                'likelihood',
                'impact',
                'exploitation_scenario',
                'business_impact',
                'recommendation',
                'remediation_effort',
                'remediation_resources',
                'fix_code',
                'detection_method',
                'confidence_score',
                'false_positive',
                'false_positive_reason',
                'status',
                'assigned_to_user_id',
                'acknowledged_at',
                'fixed_at',
                'fix_notes',
                'references',
                'tags',
                'external_id',
                'tool_data',
                'duplicate_of_id',
                'similarity_hash',
                'analysis_metadata'
            ]);
        });
    }
};
