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
        Schema::create('billing_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('period'); // 'monthly', 'yearly'
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('analysis_count')->default(0);
            $table->integer('api_calls_count')->default(0);
            $table->bigInteger('tokens_used')->default(0);
            $table->decimal('total_cost', 10, 4)->default(0);
            $table->json('breakdown')->nullable(); // Detailed breakdown of usage
            $table->string('subscription_name')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->boolean('is_overage')->default(false);
            $table->decimal('overage_cost', 10, 4)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'period_start', 'period']);
            $table->index(['user_id', 'period']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_usages');
    }
};
