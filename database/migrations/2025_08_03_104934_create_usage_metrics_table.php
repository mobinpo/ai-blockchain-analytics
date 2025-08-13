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
        Schema::create('usage_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('metric_type'); // 'analysis', 'api_call', 'tokens_used', etc.
            $table->integer('quantity')->default(1);
            $table->decimal('cost', 10, 4)->nullable(); // Cost in USD
            $table->json('metadata')->nullable(); // Additional data like model used, tokens, etc.
            $table->string('resource_type')->nullable(); // 'project', 'analysis', etc.
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->date('usage_date');
            $table->timestamps();

            $table->index(['user_id', 'usage_date']);
            $table->index(['user_id', 'metric_type']);
            $table->index(['usage_date', 'metric_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_metrics');
    }
};
