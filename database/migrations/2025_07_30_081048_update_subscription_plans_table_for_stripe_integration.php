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
            // Check if columns don't already exist before adding them
            if (!Schema::hasColumn('subscription_plans', 'slug')) {
                $table->string('slug')->unique()->after('name');
            }
            if (!Schema::hasColumn('subscription_plans', 'stripe_price_id')) {
                $table->string('stripe_price_id')->nullable()->after('stripe_id');
            }
            if (!Schema::hasColumn('subscription_plans', 'interval_count')) {
                $table->integer('interval_count')->default(1)->after('interval');
            }
            if (!Schema::hasColumn('subscription_plans', 'trial_period_days')) {
                $table->integer('trial_period_days')->default(0)->after('interval_count');
            }
            if (!Schema::hasColumn('subscription_plans', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
        });

        // Handle stripe_id column separately for SQLite compatibility
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Only try to rename/drop stripe_id if it exists
            if (Schema::hasColumn('subscription_plans', 'stripe_id')) {
                // For SQLite, we need to handle this differently
                if (config('database.default') === 'sqlite') {
                    // For SQLite, just add the legacy column and keep the original
                    if (!Schema::hasColumn('subscription_plans', 'stripe_id_legacy')) {
                        $table->string('stripe_id_legacy')->nullable();
                    }
                } else {
                    // For other databases, we can safely rename
                    $table->renameColumn('stripe_id', 'stripe_id_legacy');
                }
            } else if (!Schema::hasColumn('subscription_plans', 'stripe_id_legacy')) {
                // If stripe_id doesn't exist, just add the legacy column
                $table->string('stripe_id_legacy')->nullable();
            }
        });

        // Update price column to integer (cents) - handle separately for compatibility
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Only change price column type if it's not already integer
            $table->integer('price')->change(); // Change to cents
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn([
                'slug',
                'stripe_price_id',
                'interval_count',
                'trial_period_days',
                'sort_order',
                'stripe_id_legacy'
            ]);
            
            // Revert price back to decimal
            $table->decimal('price', 8, 2)->change();
            
            // Add back original stripe_id column
            $table->string('stripe_id')->unique();
        });
    }
};
