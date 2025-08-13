<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // onboarding, marketing, transactional
            $table->text('description')->nullable();
            $table->json('configuration')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['type', 'active']);
        });

        Schema::create('email_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_campaigns')->onDelete('cascade');
            $table->string('name');
            $table->string('template');
            $table->string('subject');
            $table->integer('delay_minutes')->default(0);
            $table->integer('order')->default(0);
            $table->boolean('active')->default(true);
            $table->json('conditions')->nullable(); // Conditions to send email
            $table->timestamps();
            
            $table->index(['campaign_id', 'order']);
        });

        Schema::create('user_email_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('marketing_emails')->default(true);
            $table->boolean('product_updates')->default(true);
            $table->boolean('security_alerts')->default(true);
            $table->boolean('onboarding_emails')->default(true);
            $table->boolean('weekly_digest')->default(true);
            $table->string('frequency')->default('normal'); // low, normal, high
            $table->timestamp('last_updated')->useCurrent();
            $table->timestamps();
            
            $table->unique('user_id');
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('sequence_id')->nullable()->constrained('email_sequences')->onDelete('set null');
            $table->string('message_id')->unique(); // Mailgun message ID
            $table->string('email');
            $table->string('subject');
            $table->string('template');
            $table->string('status'); // queued, sent, delivered, opened, clicked, bounced, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->json('mailgun_data')->nullable(); // Raw Mailgun response
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['message_id']);
            $table->index(['status', 'sent_at']);
        });

        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('step'); // welcome, getting_started, first_analysis, etc.
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->json('data')->nullable(); // Step-specific data
            $table->timestamps();
            
            $table->unique(['user_id', 'step']);
            $table->index(['user_id', 'completed']);
        });

        Schema::create('email_unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('type')->default('all'); // all, marketing, onboarding, etc.
            $table->string('reason')->nullable();
            $table->string('source')->default('email_link'); // email_link, user_settings, admin
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['email', 'type']);
        });

        Schema::create('email_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->constrained()->onDelete('cascade');
            $table->string('url');
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('clicked_at');
            $table->timestamps();
            
            $table->index(['email_log_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_clicks');
        Schema::dropIfExists('email_unsubscribes');
        Schema::dropIfExists('onboarding_progress');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('user_email_preferences');
        Schema::dropIfExists('email_sequences');
        Schema::dropIfExists('email_campaigns');
    }
};