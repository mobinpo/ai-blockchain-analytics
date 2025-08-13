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
        Schema::create('crawler_keyword_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('keywords'); // Array of keywords to search for
            $table->json('platforms'); // Array of platforms: twitter, reddit, telegram
            $table->json('conditions')->nullable(); // Advanced filtering conditions
            $table->json('sentiment_filter')->nullable(); // Sentiment filtering options
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->boolean('is_active')->default(true);
            $table->json('schedule')->nullable(); // Scheduling configuration
            $table->integer('max_posts_per_run')->default(100);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();

            $table->index(['is_active', 'priority']);
            $table->index(['created_at']);
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawler_keyword_rules');
    }
};