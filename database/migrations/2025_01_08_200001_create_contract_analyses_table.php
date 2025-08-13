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
        Schema::create('contract_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('famous_contracts')->onDelete('cascade');
            $table->string('analysis_type', 50)->index(); // security_audit, gas_optimization, etc.
            $table->string('status', 20)->default('pending')->index(); // pending, running, completed, failed
            $table->integer('risk_score')->default(50);
            $table->json('findings')->nullable();
            $table->json('recommendations')->nullable();
            $table->timestamp('analysis_date')->nullable();
            $table->string('analyzer_version', 20)->default('1.0.0');
            $table->integer('execution_time_ms')->nullable(); // Analysis execution time
            $table->decimal('confidence_score', 5, 2)->default(0.00); // 0.00 to 100.00
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['contract_id', 'analysis_type']);
            $table->index(['status', 'analysis_date']);
            $table->index(['risk_score', 'confidence_score']);
            $table->index('analysis_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_analyses');
    }
};
