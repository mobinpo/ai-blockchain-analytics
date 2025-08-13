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
        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->index(); // twitter, reddit, telegram
            $table->string('platform_id')->unique(); // unique ID from platform
            $table->string('author');
            $table->text('content');
            $table->json('matched_keywords'); // keywords that triggered crawling
            $table->json('metadata')->nullable(); // platform-specific data
            $table->decimal('sentiment_score', 3, 2)->nullable();
            $table->integer('engagement_count')->default(0); // likes, shares, etc
            $table->timestamp('platform_created_at');
            $table->timestamp('crawled_at');
            $table->timestamps();
            
            $table->index(['platform', 'platform_created_at']);
            $table->index('sentiment_score');
            $table->index('crawled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};
