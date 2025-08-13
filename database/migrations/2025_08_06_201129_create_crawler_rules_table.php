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
        Schema::create('crawler_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('platforms'); // ['twitter', 'reddit', 'telegram']
            $table->json('keywords'); // ['bitcoin', 'ethereum', 'defi']
            $table->json('hashtags')->nullable(); // ['#btc', '#eth']
            $table->json('accounts')->nullable(); // specific accounts to monitor
            $table->integer('sentiment_threshold')->nullable(); // -100 to 100
            $table->integer('engagement_threshold')->default(0);
            $table->boolean('active')->default(true);
            $table->integer('priority')->default(1); // 1=high, 2=medium, 3=low
            $table->json('filters')->nullable(); // additional filtering rules
            $table->timestamps();
            
            $table->index('active');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawler_rules');
    }
};
