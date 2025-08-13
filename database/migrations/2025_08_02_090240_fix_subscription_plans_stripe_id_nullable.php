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
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Make stripe_id nullable to fix seeding issues
            if (Schema::hasColumn('subscription_plans', 'stripe_id')) {
                $table->string('stripe_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Revert stripe_id back to not nullable
            if (Schema::hasColumn('subscription_plans', 'stripe_id')) {
                $table->string('stripe_id')->nullable(false)->change();
            }
        });
    }
};
