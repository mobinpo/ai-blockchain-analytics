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
        Schema::create('email_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->index();
            $table->string('user_email')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50)->index(); // delivered, opened, clicked, bounced, etc.
            $table->json('event_data')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('campaign_id', 100)->nullable()->index();
            $table->string('email_type', 50)->nullable()->index();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_email', 'event_type']);
            $table->index(['occurred_at', 'event_type']);
            $table->index(['campaign_id', 'event_type']);
            $table->index(['message_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_tracking');
    }
};
