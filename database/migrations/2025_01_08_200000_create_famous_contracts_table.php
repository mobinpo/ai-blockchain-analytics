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
        Schema::create('famous_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('address', 42)->unique()->index();
            $table->string('network', 50)->index();
            $table->string('contract_type', 50)->index();
            $table->text('description');
            $table->date('deployment_date')->nullable();
            $table->bigInteger('total_value_locked')->default(0); // in wei/smallest unit
            $table->bigInteger('transaction_count')->default(0);
            $table->string('creator_address', 42)->nullable();
            $table->boolean('is_verified')->default(false)->index();
            $table->integer('risk_score')->default(50)->index(); // 0-100 scale
            $table->json('security_features')->nullable();
            $table->json('vulnerabilities')->nullable();
            $table->json('audit_firms')->nullable();
            $table->string('gas_optimization', 20)->default('Medium');
            $table->string('code_quality', 20)->default('Unknown');
            $table->json('exploit_details')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['network', 'contract_type']);
            $table->index(['risk_score', 'is_verified']);
            $table->index(['created_at', 'network']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('famous_contracts');
    }
};
