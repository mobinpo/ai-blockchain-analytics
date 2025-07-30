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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('stripe_id')->unique();
            $table->decimal('price', 8, 2);
            $table->string('currency', 3)->default('usd');
            $table->string('interval')->default('month'); // month, year
            $table->json('features')->nullable();
            $table->integer('analysis_limit')->default(-1); // -1 for unlimited
            $table->integer('project_limit')->default(-1); // -1 for unlimited
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
