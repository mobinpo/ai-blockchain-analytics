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
        Schema::table('daily_sentiment_aggregates', function (Blueprint $table) {
            $table->string('keyword_category', 50)->nullable()->index()->after('keyword');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_sentiment_aggregates', function (Blueprint $table) {
            $table->dropColumn('keyword_category');
        });
    }
};
